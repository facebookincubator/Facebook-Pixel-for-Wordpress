/**
 * Meta Pixel for WordPress — Form Field Mapping admin tests.
 *
 * Runs against the latest release of every supported form plugin (see
 * .github/workflows/e2e-field-mapping.yml and
 * tests/e2e/scripts/seed-field-mapping.sh) and checks:
 *
 * - Each form is listed once, with its id, and its title links to the form in
 *   its plugin's own editor. The link must be the one the plugin itself uses:
 *   it is looked up on the plugin's own forms list, or compared with the
 *   plugin's edit-link helper, so a plugin changing its editor URL fails here.
 *   Opening the link must show that plugin's editor for that form.
 * - A mapping left behind by a deleted form is listed apart, without a link.
 * - The mapping editor opens in a popup that Escape and Cancel close, and
 *   saving from it updates the row.
 * - The table fits a phone screen: it scrolls inside its box rather than
 *   running off the page.
 * - The Lead event quality panel says when Meta reports no share for a
 *   parameter, or no parameters, rather than that none were received.
 * - The Lead event quality panel shows, to the right of the table, the score
 *   and parameters Meta's Dataset Quality API really returns for the Lead
 *   events this plugin sends (with the QA pixel and token), caches them for a
 *   day or until a mapping or the pixel or token changes, stays hidden when
 *   Meta rejects the token (on Facebook Login for Business, the plugin then
 *   disconnects), follows connection changes without a reload, and is hidden
 *   while a replacement is picked for a pixel the site lost access to.
 */

const fs = require('fs');
const { test, expect } = require('@playwright/test');
const { execWP } = require('../helpers/js/wordpress/exec');

const FORMS_FILE = process.env.FIELD_MAPPING_FORMS_FILE;
if (!FORMS_FILE || !fs.existsSync(FORMS_FILE)) {
  throw new Error(
    `FIELD_MAPPING_FORMS_FILE (${FORMS_FILE}) is missing: run tests/e2e/scripts/seed-field-mapping.sh first.`,
  );
}
const SEED = JSON.parse(fs.readFileSync(FORMS_FILE, 'utf8'));

const SETTINGS_PATH = 'options-general.php?page=facebook_pixel_options';

/**
 * What the test knows about each form plugin's admin:
 * - listPath: its forms list, whose own edit link for the form must equal ours.
 *   Mailchimp for WP Lite has a single form and no list, so its link is
 *   compared with its mc4wp_get_edit_form_url() helper instead.
 * - editor(form): an element only that plugin's editor for that form renders.
 */
const PLUGINS = {
  'contact-form-7': {
    listPath: 'admin.php?page=wpcf7',
    editor: (form) => `input#post_ID[value="${form.id}"]`,
  },
  'wpforms-lite': {
    listPath: 'admin.php?page=wpforms-overview',
    editor: (form) => `form#wpforms-builder-form[data-id="${form.id}"]`,
  },
  'ninja-forms': {
    listPath: 'admin.php?page=ninja-forms',
    // The builder is a script-rendered app: its container plus the form's
    // title (in its preloaded form data) identify the form.
    editor: () => '#nf-builder',
  },
  'formidable-lite': {
    listPath: 'admin.php?page=formidable',
    editor: (form) => `input#form_id[value="${form.id}"]`,
  },
  'mailchimp-for-wp': {
    listPath: null,
    editor: (form) => `input[name="mc4wp_form_id"][value="${form.id}"]`,
  },
};

function adminUrl(path) {
  return new URL(`/wp-admin/${path}`, process.env.WORDPRESS_URL).href;
}

// Same admin screen: same path and ?page= (other arguments may differ).
function isSameScreen(actual, expected) {
  const a = new URL(actual);
  const e = new URL(expected);
  return (
    a.pathname === e.pathname &&
    a.searchParams.get('page') === e.searchParams.get('page')
  );
}

// Form plugins may redirect the first admin visit to a welcome screen; one
// more visit then lands where asked.
async function gotoAdmin(page, path) {
  const url = adminUrl(path);
  for (let attempt = 0; attempt < 2; attempt++) {
    await page.goto(url, { waitUntil: 'domcontentloaded' });
    if (isSameScreen(page.url(), url)) {
      return;
    }
  }
  throw new Error(`Expected ${url}, but landed on ${page.url()}`);
}

async function login(page) {
  await page.goto('/wp-login.php', { waitUntil: 'domcontentloaded' });
  await page.locator('#user_login').fill(process.env.WP_USERNAME);
  await page.locator('#user_pass').fill(process.env.WP_PASSWORD);
  await Promise.all([
    page.waitForURL(/\/wp-admin\//),
    page.locator('#wp-submit').click(),
  ]);
}

async function openMappingTable(page) {
  await gotoAdmin(page, SETTINGS_PATH);
  await page.locator('a[data-fb-tab="configuration"]').click();
  await expect(page.locator('#fb-mapping-list tbody tr').first()).toBeVisible();
}

// The table row of a form: its plugin and its id.
function mappingRow(page, form) {
  return page
    .locator('#fb-mapping-list tbody tr')
    .filter({ hasText: form.label })
    .filter({
      has: page.locator('.fb-mapping-form-id', {
        hasText: new RegExp(`^#${form.id}$`),
      }),
    });
}

test.beforeEach(async ({ page }) => {
  await login(page);
});

test.describe('form editor links', () => {
  for (const form of SEED.forms) {
    test(`${form.label}: lists the form once and links to its editor`, async ({
      page,
    }) => {
      const plugin = PLUGINS[form.integration];
      expect(plugin, `no test data for ${form.integration}`).toBeTruthy();

      await openMappingTable(page);
      const row = mappingRow(page, form);
      await expect(row).toHaveCount(1);
      await expect(row.locator('td').nth(1)).toContainText(form.title);
      const link = row.locator('a.fb-mapping-form-link');
      await expect(link).toHaveAttribute('target', '_blank');
      const ourUrl = await link.evaluate((a) => a.href);

      // The plugin's own edit link for the form is the same URL.
      if ('helper' in form) {
        expect(
          form.helper_url,
          `${form.helper} is gone from ${form.label}: check its editor URL and update get_form_edit_url()`,
        ).toBeTruthy();
        expect(
          ourUrl,
          `${form.label}'s ${form.helper}() returns a different URL: has its editor URL changed? Update get_form_edit_url().`,
        ).toBe(new URL(form.helper_url).href);
      }
      if (plugin.listPath) {
        await gotoAdmin(page, plugin.listPath);
        await expect
          .poll(
            () => page.$$eval('a[href]', (links) => links.map((a) => a.href)),
            {
              message: `${form.label}'s forms list (${plugin.listPath}) has no link to ${ourUrl}: has its editor URL changed? Update get_form_edit_url().`,
              timeout: 30000,
            },
          )
          .toContain(ourUrl);
      }

      // The link opens that plugin's editor for that form.
      await page.goto(ourUrl, { waitUntil: 'domcontentloaded' });
      await expect(
        page.locator(plugin.editor(form)).first(),
        `${ourUrl} did not open ${form.label}'s editor for form #${form.id}`,
      ).toBeAttached();
      expect(await page.content()).toContain(form.title);
    });
  }
});

test('a mapping left by a deleted form is listed apart, without a link', async ({
  page,
}) => {
  await openMappingTable(page);

  const orphan = mappingRow(page, SEED.orphan);
  await expect(orphan).toHaveCount(1);
  await expect(orphan).toContainText('Saved (form not found)');
  await expect(orphan.locator('a.fb-mapping-form-link')).toHaveCount(0);

  // Every available form comes before the "No longer available" heading, the
  // leftover mapping after it, even though it has the same title as a live
  // form.
  const live = SEED.forms.find((form) => form.title === SEED.orphan.title);
  const order = await page
    .locator('#fb-mapping-list tbody tr')
    .evaluateAll((rows) =>
      rows.map((tr) =>
        tr.classList.contains('fb-mapping-group')
          ? 'group'
          : (tr.querySelector('.fb-mapping-form-id') || {}).textContent,
      ),
    );
  const group = order.indexOf('group');
  expect(group).toBeGreaterThan(0);
  expect(order.indexOf(`#${live.id}`)).toBeLessThan(group);
  expect(order.indexOf(`#${SEED.orphan.id}`)).toBeGreaterThan(group);
});

test('the mapping editor opens in a popup', async ({ page }) => {
  const form = SEED.popup;
  // Start unreviewed, also on a retry.
  await execWP(
    `(new \\FacebookPixelPlugin\\Core\\FormFieldMapper())->delete_form_mapping('${form.integration}', '${form.id}');`,
  );

  await openMappingTable(page);
  const row = mappingRow(page, form);
  const status = row.locator('td').nth(2);
  await expect(status).toHaveText('Automatic (not reviewed)');

  const dialog = page.locator('dialog#fb-mapping-editor');
  const fields = dialog.locator('#fb-mapping-fields tbody tr');
  const review = async () => {
    await row.locator('a.fb-mapping-edit').click();
    await expect(dialog).toBeVisible();
    await expect(fields).toHaveCount(2);
  };

  // A modal: the popup is open over the table and names the form.
  await review();
  expect(await dialog.evaluate((d) => d.open && d.matches(':modal'))).toBe(
    true,
  );
  await expect(dialog.locator('#fb-mapping-editor-title')).toHaveText(
    `${form.title} #${form.id} (${form.label})`,
  );

  // Save mapping and Reject on the left, Cancel at the right end.
  const box = async (selector) => dialog.locator(selector).boundingBox();
  const [save, reject, cancel, popup] = await Promise.all([
    box('#fb-mapping-save'),
    box('#fb-mapping-reject'),
    box('#fb-mapping-cancel'),
    dialog.boundingBox(),
  ]);
  expect(save.x).toBeLessThan(reject.x);
  expect(reject.x + reject.width).toBeLessThan(popup.x + popup.width / 2);
  expect(cancel.x).toBeGreaterThan(popup.x + popup.width / 2);
  expect(popup.x + popup.width - (cancel.x + cancel.width)).toBeLessThan(40);

  // Escape and Cancel close it without saving.
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
  await review();
  await dialog.locator('#fb-mapping-cancel').click();
  await expect(dialog).toBeHidden();
  await expect(status).toHaveText('Automatic (not reviewed)');

  // Saving closes it and updates the row.
  await review();
  await dialog.locator('#fb-mapping-save').click();
  await expect(dialog).toBeHidden();
  await expect(page.locator('#fb-mapping-status')).toHaveText('Mapping saved.');
  await expect(status).toHaveText('Saved');
});

test('the forms table fits a phone screen', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 800 });
  await openMappingTable(page);

  // The page does not scroll sideways: the table scrolls inside its box,
  // which stays within the screen.
  const widths = await page.evaluate(() => ({
    page: document.documentElement.scrollWidth,
    screen: window.innerWidth,
  }));
  expect(widths.page).toBeLessThanOrEqual(widths.screen);
  const main = page.locator('.fb-mapping-main');
  const box = await main.boundingBox();
  expect(box.x).toBeGreaterThanOrEqual(0);
  expect(box.x + box.width).toBeLessThanOrEqual(widths.screen);
  const table = await page.locator('#fb-mapping-list').boundingBox();
  if (table.x + table.width > box.x + box.width + 1) {
    // Wider than its box: scrolled inside it, not spilled over the page.
    expect(
      await main.evaluate(
        (element) => element.scrollWidth > element.clientWidth,
      ),
    ).toBe(true);
    expect(
      await main.evaluate((element) => getComputedStyle(element).overflowX),
    ).not.toBe('visible');
  }

  await test.info().attach('forms-table-mobile', {
    body: await page.locator('#fb-field-mapping').screenshot(),
    contentType: 'image/png',
  });
});

test('the Lead event quality panel says when Meta reports no coverage', async ({
  page,
}) => {
  // The panel's answer is made up here, so this runs without Meta: a score
  // whose parameters come without a share, then one with no parameters.
  let parameters = [
    { identifier: 'email', label: 'Email', coverage: 62.5 },
    { identifier: 'phone', label: 'Phone', coverage: null },
  ];
  await page.route(
    (url) =>
      url.searchParams.get('action') === 'facebook_get_lead_event_quality',
    (route) =>
      route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          msg: { status: 'ok', score: 6.4, parameters, message: '' },
        }),
      }),
  );
  await openMappingTable(page);
  const panel = page.locator('#fb-lead-quality');
  await expect(panel).toBeVisible();

  // A share Meta did not report is said to be unknown, without a bar that
  // would read as 0%.
  const phone = panel.locator(
    '.fb-lead-quality-parameter[data-identifier="phone"]',
  );
  await expect(phone).toHaveText(/^Phone\s*Not reported$/);
  await expect(phone.locator('.fb-lead-quality-bar')).toHaveCount(0);
  const email = panel.locator(
    '.fb-lead-quality-parameter[data-identifier="email"]',
  );
  await expect(email).toHaveText(/^Email\s*63%$/);
  await expect(email.locator('.fb-lead-quality-bar')).toHaveCount(1);

  // No parameters reported is not "no customer information received".
  parameters = [];
  await openMappingTable(page);
  await expect(panel.locator('.fb-lead-quality-none')).toHaveText(
    'Meta has not reported which parameters these events include.',
  );
});

/**
 * Lead event quality, read live from Meta: the plugin is connected with the QA
 * pixel and token (FB_PIXEL_ID / FB_ACCESS_TOKEN), whose pixel receives the
 * Lead events the Pixel/CAPI E2E sends through this plugin. Nothing is
 * answered for Meta; the test mu-plugin e2e-dataset-quality-log.php only logs
 * the URL of each request so they can be counted.
 */
test.describe('Lead event quality (live from Meta)', () => {
  const QUALITY_ACTION = 'facebook_get_lead_event_quality';
  const QUALITY_CACHE = 'facebook_lead_event_quality';
  const OPTIONS = '\\FacebookPixelPlugin\\Core\\FacebookWordpressOptions';
  const PLUGIN_CONFIG = '\\FacebookPixelPlugin\\Core\\FacebookPluginConfig';
  const INVALID_TOKEN = 'e2e-invalid-token';
  const BACKUP = 'e2e_saved_meta_settings';

  // Runs PHP that sets $result, and returns $result decoded.
  async function php(code) {
    const { stdout } = await execWP(
      `${code}\necho '<<<' . wp_json_encode($result) . '>>>';`,
    );
    return JSON.parse(stdout.match(/<<<([\s\S]*)>>>/)[1]);
  }

  // The Meta settings are backed up and restored inside PHP: the QA token
  // never passes through a command line, so a failed command cannot put it
  // in the test report. The first backup is kept until restoreSettings().
  const backupSettings = () =>
    execWP(`
      add_option('${BACKUP}', array(
        'mbe'   => get_option(${PLUGIN_CONFIG}::SETTINGS_KEY),
        'fbl4b' => get_option(${PLUGIN_CONFIG}::FBL4B_SETTINGS_KEY),
      ), '', 'no');
    `);

  // Puts back the settings backed up. The cached answer is left alone: the
  // plugin must notice the change by itself.
  const restoreSettings = () =>
    execWP(`
      $saved = get_option('${BACKUP}');
      if (is_array($saved)) {
        foreach (array('mbe' => ${PLUGIN_CONFIG}::SETTINGS_KEY, 'fbl4b' => ${PLUGIN_CONFIG}::FBL4B_SETTINGS_KEY) as $key => $option) {
          if (false === $saved[$key]) {
            delete_option($option);
          } else {
            update_option($option, $saved[$key]);
          }
        }
        delete_option('${BACKUP}');
      }
      delete_transient('facebook_pixel_connection_invalid');
    `);

  // Replaces the token the plugin sends events with, whichever connection
  // the site uses (Facebook Login for Business or the legacy one), with one
  // Meta rejects.
  async function invalidateToken() {
    await backupSettings();
    await execWP(`
      if ('fbl4b' === ${OPTIONS}::get_connection_type()) {
        $settings = get_option(${PLUGIN_CONFIG}::FBL4B_SETTINGS_KEY);
        $settings[${PLUGIN_CONFIG}::FBL4B_ACCESS_TOKEN_KEY] = ${OPTIONS}::encrypt_token('${INVALID_TOKEN}');
        update_option(${PLUGIN_CONFIG}::FBL4B_SETTINGS_KEY, $settings);
      } else {
        $settings = get_option(${PLUGIN_CONFIG}::SETTINGS_KEY);
        $settings[${PLUGIN_CONFIG}::ACCESS_TOKEN_KEY] = '${INVALID_TOKEN}';
        update_option(${PLUGIN_CONFIG}::SETTINGS_KEY, $settings);
      }
    `);
    // The token the plugin now uses is the invalid one.
    expect(
      await php(
        `$result = '${INVALID_TOKEN}' === ${OPTIONS}::get_active_access_token();`,
      ),
    ).toBe(true);
  }

  const FBL4B_BUSINESS = 'e2e-business';
  const ajaxAction = (action) => (url) =>
    url.searchParams.get('action') === action;
  const VALIDATE_TOKEN = ajaxAction('fbl4b_validate_token');
  const FETCH_PIXELS = ajaxAction('fbl4b_fetch_pixels');

  // Answers, in the page, Facebook Login for Business's own checks on load:
  // the token is valid, and the business has these pixels (by default, the
  // site's). The QA token is not a Facebook Login for Business one, so Meta
  // cannot answer them; the Lead event quality is still asked of Meta.
  // `before` holds the pixels answer back until it settles.
  async function routeFbl4b(page, { pixels = null, before = null } = {}) {
    const pixelId = await php(
      `$result = (string) ${OPTIONS}::get_active_pixel_id();`,
    );
    await page.route(VALIDATE_TOKEN, (route) =>
      route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: { valid: true, client_business_id: FBL4B_BUSINESS },
        }),
      }),
    );
    await page.route(FETCH_PIXELS, async (route) => {
      if (before) {
        await before;
      }
      await route.fulfill({
        contentType: 'application/json',
        body: JSON.stringify({
          success: true,
          data: { data: pixels || [{ id: pixelId, name: 'E2E pixel' }] },
        }),
      });
    });
  }

  // Connects the site through Facebook Login for Business only, as a site
  // set up with it has: the QA pixel and token move to its settings, and the
  // legacy ones are removed (restoreSettings() puts them back), so nothing
  // falls back to them once it disconnects. Its checks are answered in the
  // page.
  async function useFbl4b(page, options) {
    await backupSettings();
    // Events now go through Facebook Login for Business, with the QA token.
    expect(
      await php(`
        $mbe   = get_option(${PLUGIN_CONFIG}::SETTINGS_KEY);
        $token = (string) $mbe[${PLUGIN_CONFIG}::ACCESS_TOKEN_KEY];
        update_option(${PLUGIN_CONFIG}::FBL4B_SETTINGS_KEY, array(
          ${PLUGIN_CONFIG}::FBL4B_ACCESS_TOKEN_KEY => ${OPTIONS}::encrypt_token($token),
          ${PLUGIN_CONFIG}::FBL4B_PIXEL_ID_KEY     => (string) $mbe[${PLUGIN_CONFIG}::PIXEL_ID_KEY],
          ${PLUGIN_CONFIG}::FBL4B_PIXEL_NAME_KEY   => 'E2E pixel',
          ${PLUGIN_CONFIG}::FBL4B_BUSINESS_ID_KEY  => '${FBL4B_BUSINESS}',
        ));
        delete_option(${PLUGIN_CONFIG}::SETTINGS_KEY);
        $result = 'fbl4b' === ${OPTIONS}::get_connection_type()
          && '' !== $token
          && hash_equals($token, ${OPTIONS}::get_active_access_token());
      `),
    ).toBe(true);
    await routeFbl4b(page, options);
  }

  // Asks Meta directly, with the site's pixel and token, for the score of the
  // Lead events sent through this plugin: what the panel must show.
  const askMeta = () =>
    php(`
      $response = wp_remote_get(
        add_query_arg(
          array(
            'dataset_id' => rawurlencode(${OPTIONS}::get_active_pixel_id()),
            'agent_name' => 'wordpress',
            'fields'     => rawurlencode('web{event_name,event_match_quality{composite_score,match_key_feedback{identifier,coverage{percentage}}}}'),
          ),
          'https://graph.facebook.com/' . \\FacebookPixelPlugin\\Core\\FacebookPluginConfig::GRAPH_API_VERSION . '/dataset_quality'
        ),
        array(
          'headers' => array('Authorization' => 'Bearer ' . ${OPTIONS}::get_active_access_token()),
          'timeout' => 30,
        )
      );
      $result = is_wp_error($response)
        ? array('code' => 0, 'body' => array('error' => array('message' => $response->get_error_message())))
        : array('code' => wp_remote_retrieve_response_code($response), 'body' => json_decode(wp_remote_retrieve_body($response), true));
    `);

  // Whether the site is connected with real credentials, not the seed's
  // placeholders.
  const isLive = () =>
    php(
      `$token = (string) ${OPTIONS}::get_active_access_token(); $result = '' !== $token && 'e2e-placeholder-token' !== $token;`,
    );

  // The URLs of the Dataset Quality requests the plugin made.
  const requests = () =>
    php(`$result = get_option('e2e_dataset_quality_requests', array());`);

  // Nothing cached or logged, and the popup form unreviewed.
  const reset = () =>
    execWP(
      `(new \\FacebookPixelPlugin\\Core\\FormFieldMapper())->delete_form_mapping('${SEED.popup.integration}', '${SEED.popup.id}');` +
        `delete_option('e2e_dataset_quality_requests'); delete_transient('${QUALITY_CACHE}');`,
    );

  const escapeRegExp = (text) => text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  // Opens the table and returns the panel's AJAX answer.
  async function openWithQuality(page) {
    const answer = page.waitForResponse((response) =>
      response.url().includes(`action=${QUALITY_ACTION}`),
    );
    await openMappingTable(page);
    return (await answer).json();
  }

  test.beforeEach(async () => {
    if (await isLive()) {
      return;
    }
    if (process.env.META_CREDENTIALS_REQUIRED === 'true') {
      throw new Error(
        'The site is not connected with real Meta credentials: set FB_PIXEL_ID / FB_ACCESS_TOKEN (the QA secrets) before seeding.',
      );
    }
    test.skip(
      true,
      'Needs real Meta credentials (FB_PIXEL_ID / FB_ACCESS_TOKEN)',
    );
  });

  test("shows Meta's score for this plugin's Lead events beside the table", async ({
    page,
  }) => {
    // Asked first: reset() then clears it from the request log.
    const meta = await askMeta();
    await reset();
    expect(
      meta.code,
      `Meta refused the Dataset Quality request: ${JSON.stringify(meta.body)}`,
    ).toBe(200);
    const lead = (meta.body.web || []).find(
      (event) =>
        event.event_name === 'Lead' &&
        event.event_match_quality &&
        typeof event.event_match_quality.composite_score === 'number',
    );
    expect(
      lead,
      `Meta has no Lead score for agent_name=wordpress (events it scored: ${(meta.body.web || []).map((event) => event.event_name).join(', ') || 'none'}). The Pixel/CAPI E2E sends Lead events to this pixel: is agent_name=wordpress matched to the plugin's partner_agent?`,
    ).toBeTruthy();

    const answer = await openWithQuality(page);
    expect(answer.msg.status).toBe('ok');
    expect(answer.msg.score).toBeCloseTo(
      lead.event_match_quality.composite_score,
      1,
    );

    // The gauge shows the score.
    const score = answer.msg.score;
    const panel = page.locator('#fb-lead-quality');
    await expect(panel).toBeVisible();
    const gauge = panel.locator('.fb-lead-quality-gauge');
    await expect(gauge).toHaveAttribute('aria-valuenow', String(score));
    await expect(panel.locator('.fb-lead-quality-score')).toHaveText(
      score.toFixed(1),
    );
    await expect(gauge.locator('.fb-lead-quality-fill')).toHaveAttribute(
      'stroke-dasharray',
      `${Math.round(score * 100) / 10} 100`,
    );

    // Every parameter Meta reports, most included first.
    const reported = (lead.event_match_quality.match_key_feedback || [])
      .filter((key) => key.identifier)
      .map((key) => key.identifier);
    const items = panel.locator('.fb-lead-quality-parameter');
    const shown = await items.evaluateAll((elements) =>
      elements.map((element) => element.dataset.identifier),
    );
    expect([...shown].sort()).toEqual([...reported].sort());
    await expect(items).toHaveText(
      answer.msg.parameters.map(
        (parameter) =>
          new RegExp(
            `^${escapeRegExp(parameter.label)}\\s*${parameter.coverage === null ? 'Not reported' : Math.round(parameter.coverage) + '%'}$`,
          ),
      ),
    );
    const coverages = answer.msg.parameters.map((parameter) =>
      Number(parameter.coverage),
    );
    expect(coverages).toEqual([...coverages].sort((a, b) => b - a));

    // The score sits inside the arc, and the 0 and 10 below its ends: none
    // of them overlap. (An SVG path's box leaves out its stroke, which is 12
    // of the viewBox's 120 units wide.)
    const geometry = await gauge.evaluate((figure) => {
      const box = (element) => {
        const rect = element.getBoundingClientRect();
        return {
          left: rect.left,
          right: rect.right,
          top: rect.top,
          bottom: rect.bottom,
        };
      };
      const svg = figure.querySelector('svg');
      return {
        unit: svg.getBoundingClientRect().width / 120,
        arc: box(figure.querySelector('.fb-lead-quality-track')),
        caption: box(figure.querySelector('figcaption')),
        scale: [...figure.querySelectorAll('.fb-lead-quality-scale')].map(box),
      };
    });
    const overlaps = (a, b) =>
      a.left < b.right &&
      b.left < a.right &&
      a.top < b.bottom &&
      b.top < a.bottom;
    expect(geometry.scale).toHaveLength(2);
    for (const label of geometry.scale) {
      expect(overlaps(label, geometry.caption)).toBe(false);
      expect(label.top).toBeGreaterThanOrEqual(
        geometry.arc.bottom + 6 * geometry.unit,
      );
    }
    expect(geometry.caption.bottom).toBeLessThanOrEqual(
      geometry.arc.bottom + 1,
    );

    // For review in the report.
    await test.info().attach('lead-event-quality', {
      body: await page.locator('.fb-mapping-layout').screenshot(),
      contentType: 'image/png',
    });

    // To the right of the table, level with its top.
    const table = await page.locator('#fb-mapping-list').boundingBox();
    const box = await panel.boundingBox();
    expect(box.x).toBeGreaterThanOrEqual(table.x + table.width);
    expect(Math.abs(box.y - table.y)).toBeLessThan(20);

    // One request, for the site's pixel and this plugin's agent, with the
    // token in a header rather than the URL.
    const sent = await requests();
    expect(sent).toHaveLength(1);
    const url = new URL(sent[0]);
    expect(url.pathname).toMatch(/\/dataset_quality$/);
    expect(url.searchParams.get('dataset_id')).toBe(
      await php(`$result = (string) ${OPTIONS}::get_active_pixel_id();`),
    );
    expect(url.searchParams.get('agent_name')).toBe('wordpress');
    expect(url.searchParams.has('access_token')).toBe(false);
  });

  test('is cached for a day, until a form mapping changes', async ({
    page,
  }) => {
    await reset();
    const first = await openWithQuality(page);
    expect(first.msg.status).toBe('ok');
    const ttl = await php(
      `$result = (int) get_option('_transient_timeout_${QUALITY_CACHE}') - time();`,
    );
    expect(ttl).toBeGreaterThan(86400 - 300);
    expect(ttl).toBeLessThanOrEqual(86400);

    // The next visit uses the cache: Meta is not asked again.
    const second = await openWithQuality(page);
    expect(second.msg).toEqual(first.msg);
    expect(await requests()).toHaveLength(1);

    // Saving a mapping drops the cache: the next visit asks Meta again.
    const row = mappingRow(page, SEED.popup);
    await row.locator('a.fb-mapping-edit').click();
    const dialog = page.locator('dialog#fb-mapping-editor');
    await expect(dialog).toBeVisible();
    await dialog.locator('#fb-mapping-save').click();
    await expect(page.locator('#fb-mapping-status')).toHaveText(
      'Mapping saved.',
    );
    expect(
      await php(`$result = false === get_transient('${QUALITY_CACHE}');`),
    ).toBe(true);
    const third = await openWithQuality(page);
    expect(third.msg.status).toBe('ok');
    await expect(page.locator('#fb-lead-quality')).toBeVisible();
    expect(await requests()).toHaveLength(2);
  });

  const CONNECTIONS = {
    mbe: 'legacy connection',
    fbl4b: 'Facebook Login for Business',
  };
  for (const [connection, label] of Object.entries(CONNECTIONS)) {
    test(`is hidden when Meta rejects the token, and asked again once it is replaced (${label})`, async ({
      page,
    }) => {
      await reset();
      const panel = page.locator('#fb-lead-quality');
      try {
        if (connection === 'fbl4b') {
          await useFbl4b(page);
        }

        // A score, cached for a day.
        const first = await openWithQuality(page);
        expect(first.msg.status).toBe('ok');
        await expect(panel).toBeVisible();
        expect(await requests()).toHaveLength(1);

        // A replaced token is not answered from the cache: Meta is asked,
        // and its error hides the panel.
        await invalidateToken();
        let asked = 2;
        if (connection === 'mbe') {
          const rejected = await openWithQuality(page);
          expect(rejected.msg.status).toBe('error');
          expect(rejected.msg.message).not.toBe('');
          await expect(page.locator('#fb-mapping-list')).toBeVisible();
          await expect(panel).toBeHidden();
        } else {
          // Facebook Login for Business checks the token itself on load (for
          // real here): Meta rejects it, so the plugin disconnects and the
          // page reloads without the connected tabs. The panel's request may
          // reach the site before or after that.
          await page.unroute(VALIDATE_TOKEN);
          const answer = page.waitForResponse((response) =>
            response.url().includes(`action=${QUALITY_ACTION}`),
          );
          await gotoAdmin(page, SETTINGS_PATH);
          const rejected = await (await answer).json();
          expect(['error', 'not_connected']).toContain(rejected.msg.status);
          if (rejected.msg.status === 'not_connected') {
            asked = 1;
          }
          await expect(page.locator('#fb-settings')).not.toHaveClass(
            /fb-settings--connected/,
            { timeout: 30000 },
          );
          // Nothing is left to send events with, nor to show a score for.
          expect(
            await php(
              `$result = array(${OPTIONS}::get_connection_type(), '' === (string) ${OPTIONS}::get_active_access_token());`,
            ),
          ).toEqual(['none', true]);
          await expect(panel).toBeHidden();
        }
        expect(await requests()).toHaveLength(asked);

        // Repairing it shows the score again at once, not after the error's
        // hour in the cache.
        await restoreSettings();
        if (connection === 'fbl4b') {
          await useFbl4b(page);
        }
        const repaired = await openWithQuality(page);
        expect(repaired.msg.status).toBe('ok');
        expect(await requests()).toHaveLength(asked + 1);
        await expect(panel).toBeVisible();
      } finally {
        await restoreSettings();
        await execWP(`delete_transient('${QUALITY_CACHE}');`);
      }
    });
  }

  test('is hidden while a replacement is picked for a lost pixel (Facebook Login for Business)', async ({
    page,
  }) => {
    await reset();
    const panel = page.locator('#fb-lead-quality');
    try {
      // On load the plugin checks the pixel is still the business's: here it
      // no longer is. That answer waits for the panel's, so the lost pixel's
      // score is on screen first.
      const answer = page.waitForResponse((response) =>
        response.url().includes(`action=${QUALITY_ACTION}`),
      );
      await useFbl4b(page, {
        pixels: [{ id: '1000000000000001', name: 'E2E other pixel' }],
        before: answer,
      });
      const cleared = page.waitForResponse((response) =>
        response.url().includes('action=fbl4b_clear_pixel'),
      );
      await gotoAdmin(page, SETTINGS_PATH);
      expect((await (await answer).json()).msg.status).toBe('ok');
      await cleared;

      // The admin is asked to pick another pixel, and nothing of the lost
      // one's is left on screen meanwhile.
      await expect(page.locator('#fbl4b-pixel-select-inline')).toBeVisible();
      await expect(panel).toHaveClass(/\bhidden\b/);
      await expect(page.locator('#fb-settings')).not.toHaveClass(
        /fb-settings--connected/,
      );

      // Nor is its score kept for when the same pixel is picked again.
      expect(
        await php(`$result = false === get_transient('${QUALITY_CACHE}');`),
      ).toBe(true);
    } finally {
      await restoreSettings();
      await execWP(`delete_transient('${QUALITY_CACHE}');`);
    }
  });

  test('shows no score for the legacy pixel left once Facebook Login for Business disconnects', async ({
    page,
  }) => {
    await reset();
    const panel = page.locator('#fb-lead-quality');
    const OTHER_PIXEL = '1000000000000002';
    try {
      // The site moved from the legacy connection (the QA pixel, A) to
      // Facebook Login for Business with another pixel (B): the legacy
      // settings stay behind, marked as no longer installed, as picking B
      // leaves them. B's token has since been revoked.
      await backupSettings();
      expect(
        await php(`
          $mbe = get_option(${PLUGIN_CONFIG}::SETTINGS_KEY);
          $mbe[${PLUGIN_CONFIG}::IS_FBE_INSTALLED_KEY] = '0';
          update_option(${PLUGIN_CONFIG}::SETTINGS_KEY, $mbe);
          update_option(${PLUGIN_CONFIG}::FBL4B_SETTINGS_KEY, array(
            ${PLUGIN_CONFIG}::FBL4B_ACCESS_TOKEN_KEY => ${OPTIONS}::encrypt_token('${INVALID_TOKEN}'),
            ${PLUGIN_CONFIG}::FBL4B_PIXEL_ID_KEY     => '${OTHER_PIXEL}',
            ${PLUGIN_CONFIG}::FBL4B_PIXEL_NAME_KEY   => 'E2E pixel B',
            ${PLUGIN_CONFIG}::FBL4B_BUSINESS_ID_KEY  => '${FBL4B_BUSINESS}',
          ));
          $result = array(${OPTIONS}::get_connection_type(), ${OPTIONS}::get_active_pixel_id());
        `),
      ).toEqual(['fbl4b', OTHER_PIXEL]);

      // The page counts its loads, and which one each panel request is from.
      let loads = 0;
      const asked = [];
      page.on('request', (request) => {
        if (
          request.isNavigationRequest() &&
          request.frame() === page.mainFrame()
        ) {
          loads++;
        } else if (request.url().includes(`action=${QUALITY_ACTION}`)) {
          asked.push({ load: loads, request });
        }
      });

      // On load Facebook Login for Business checks its token (for real):
      // Meta rejects it, so the plugin disconnects and reloads the page.
      const deleted = page.waitForResponse((response) =>
        response.url().includes('action=delete_fbl4b_settings'),
      );
      await gotoAdmin(page, SETTINGS_PATH);
      await deleted;
      await expect.poll(() => loads, { timeout: 30000 }).toBe(2);
      await expect
        .poll(() => asked.filter((entry) => entry.load === 2).length)
        .toBe(1);

      // The active credentials still fall back to the legacy pixel...
      expect(
        await php(`
          $result = array(${OPTIONS}::get_connection_type(), ${OPTIONS}::is_connected(),
            (string) ${OPTIONS}::get_active_pixel_id() === (string) get_option(${PLUGIN_CONFIG}::SETTINGS_KEY)[${PLUGIN_CONFIG}::PIXEL_ID_KEY]);
        `),
      ).toEqual(['none', false, true]);

      // ...which the Connection tab does not show as connected: no score is
      // shown for it, nor the Configuration tab offered.
      const reloaded = asked.find((entry) => entry.load === 2).request;
      const answer = await (await reloaded.response()).json();
      expect(answer.msg.status).toBe('not_connected');
      await expect(page.locator('#fb-settings')).not.toHaveClass(
        /fb-settings--connected/,
      );
      await expect(page.locator('a[data-fb-tab="configuration"]')).toBeHidden();
      await expect(panel).toHaveClass(/\bhidden\b/);
    } finally {
      await restoreSettings();
      await execWP(`delete_transient('${QUALITY_CACHE}');`);
    }
  });

  test('follows connection changes without a reload', async ({ page }) => {
    await reset();
    const panel = page.locator('#fb-lead-quality');
    // What js/lib/fbe.js fires once a connection or pixel is saved, or the
    // plugin is disconnected; the panel must ask again for what is now
    // active.
    const connectionChanged = async (connected) => {
      const answer = connected
        ? page.waitForResponse((response) =>
            response.url().includes(`action=${QUALITY_ACTION}`),
          )
        : null;
      await page.evaluate(
        (value) =>
          jQuery(document).trigger('facebook-pixel:connection-changed', [
            value,
          ]),
        connected,
      );
      return answer ? (await answer).json() : null;
    };

    try {
      expect((await openWithQuality(page)).msg.status).toBe('ok');
      await expect(panel).toBeVisible();

      // Connected again with a token Meta rejects: the old score goes.
      await invalidateToken();
      expect((await connectionChanged(true)).msg.status).toBe('error');
      await expect(panel).toHaveClass(/\bhidden\b/);

      // Connected with a working token: the score comes back.
      await restoreSettings();
      expect((await connectionChanged(true)).msg.status).toBe('ok');
      await expect(panel).not.toHaveClass(/\bhidden\b/);
      expect(await requests()).toHaveLength(3);

      // The settings page moves to the Connection tab on a change.
      await page.locator('a[data-fb-tab="configuration"]').click();
      await expect(panel).toBeVisible();

      // Disconnected: nothing is shown, and Meta is not asked.
      await connectionChanged(false);
      await expect(panel).toHaveClass(/\bhidden\b/);
      expect(await requests()).toHaveLength(3);
    } finally {
      await restoreSettings();
      await execWP(`delete_transient('${QUALITY_CACHE}');`);
    }
  });
});

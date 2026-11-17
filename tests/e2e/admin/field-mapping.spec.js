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
 * - The Lead event quality panel shows, to the right of the table, the score
 *   and parameters the Dataset Quality API reports for Lead events (answered by
 *   the test mu-plugin tests/e2e/fixtures/mu-plugins/e2e-dataset-quality.php),
 *   caches them for a day or until a mapping changes, and stays hidden when
 *   there is no score.
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

test.describe('Lead event quality', () => {
  const QUALITY_ACTION = 'facebook_get_lead_event_quality';
  const QUALITY_CACHE = 'facebook_lead_event_quality';
  const PIXEL_ID = '1234567890123456';

  // What the Dataset Quality API answers: a Lead and a PageView event.
  const quality = (score) => ({
    code: 200,
    body: {
      web: [
        {
          event_name: 'PageView',
          event_match_quality: { composite_score: 3.1 },
        },
        {
          event_name: 'Lead',
          event_match_quality: {
            composite_score: score,
            match_key_feedback: [
              { identifier: 'email', coverage: { percentage: 98.2 } },
              { identifier: 'ip_address', coverage: { percentage: 100 } },
              { identifier: 'phone', coverage: { percentage: 41.3 } },
            ],
          },
        },
      ],
    },
  });

  // Runs PHP and returns what it echoes between the markers, decoded.
  async function phpJson(php) {
    const { stdout } = await execWP(
      `echo '<<<' . wp_json_encode(${php}) . '>>>';`,
    );
    return JSON.parse(stdout.match(/<<<([\s\S]*)>>>/)[1]);
  }

  // What the test mu-plugin answers (null: the request fails).
  async function answerWith(response) {
    const json = Buffer.from(JSON.stringify(response)).toString('base64');
    await execWP(
      response === null
        ? `delete_option('e2e_dataset_quality_response');`
        : `update_option('e2e_dataset_quality_response', json_decode(base64_decode('${json}'), true), false);`,
    );
  }

  // Starts with nothing cached or recorded.
  async function reset(response) {
    await execWP(
      `(new \\FacebookPixelPlugin\\Core\\FormFieldMapper())->delete_form_mapping('${SEED.popup.integration}', '${SEED.popup.id}');` +
        `delete_option('e2e_dataset_quality_requests'); delete_transient('${QUALITY_CACHE}');`,
    );
    await answerWith(response);
  }

  const requests = () =>
    phpJson(`get_option('e2e_dataset_quality_requests', array())`);

  // Opens the table and returns the panel's AJAX answer.
  async function openWithQuality(page) {
    const answer = page.waitForResponse((response) =>
      response.url().includes(`action=${QUALITY_ACTION}`),
    );
    await openMappingTable(page);
    return (await answer).json();
  }

  test('shows the Lead score from Meta beside the forms table', async ({
    page,
  }) => {
    await reset(quality(7.4));
    await openWithQuality(page);

    const panel = page.locator('#fb-lead-quality');
    await expect(panel).toBeVisible();
    const gauge = panel.locator('.fb-lead-quality-gauge');
    await expect(gauge).toHaveAttribute('aria-valuenow', '7.4');
    await expect(gauge).toHaveAttribute('aria-valuetext', '7.4 out of 10');
    await expect(panel.locator('.fb-lead-quality-score')).toHaveText('7.4');
    // The score fills 74 of the arc's 100, over the red-to-green scale.
    await expect(gauge.locator('.fb-lead-quality-fill')).toHaveAttribute(
      'stroke-dasharray',
      '74 100',
    );
    // Most included first.
    await expect(panel.locator('.fb-lead-quality-parameter')).toHaveText([
      /IP address\s*100%/,
      /Email\s*98%/,
      /Phone\s*41%/,
    ]);

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

    // One request, for the pixel, with the token in its header.
    const sent = await requests();
    expect(sent).toHaveLength(1);
    const url = new URL(sent[0].url);
    expect(url.pathname).toMatch(/\/dataset_quality$/);
    expect(url.searchParams.get('dataset_id')).toBe(PIXEL_ID);
    expect(url.searchParams.get('agent_name')).toBe('wordpress');
    expect(url.searchParams.get('fields')).toContain(
      'event_match_quality{composite_score,match_key_feedback{identifier,coverage{percentage}}}',
    );
    expect(url.searchParams.has('access_token')).toBe(false);
    expect(sent[0].authorization).toBe('Bearer e2e-placeholder-token');
  });

  test('is cached for a day, until a form mapping changes', async ({
    page,
  }) => {
    await reset(quality(7.4));
    await openWithQuality(page);
    const score = page.locator('#fb-lead-quality .fb-lead-quality-score');
    await expect(score).toHaveText('7.4');
    const ttl = await phpJson(
      `(int) get_option('_transient_timeout_${QUALITY_CACHE}') - time()`,
    );
    expect(ttl).toBeGreaterThan(86400 - 300);
    expect(ttl).toBeLessThanOrEqual(86400);

    // Meta's score changes, but the cached one is shown.
    await answerWith(quality(4.2));
    await openWithQuality(page);
    await expect(score).toHaveText('7.4');
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
    await openWithQuality(page);
    await expect(score).toHaveText('4.2');
    expect(await requests()).toHaveLength(2);
  });

  for (const [name, response, status] of [
    [
      'Meta returns an error',
      {
        code: 400,
        body: { error: { message: 'Unsupported get request.', code: 100 } },
      },
      'error',
    ],
    [
      'there is no Lead score',
      {
        code: 200,
        body: {
          web: [
            {
              event_name: 'PageView',
              event_match_quality: { composite_score: 6 },
            },
          ],
        },
      },
      'no_data',
    ],
    ['the request fails', null, 'error'],
  ]) {
    test(`is hidden when ${name}`, async ({ page }) => {
      await reset(response);
      const answer = await openWithQuality(page);

      expect(answer.msg.status).toBe(status);
      await expect(page.locator('#fb-mapping-list')).toBeVisible();
      await expect(page.locator('#fb-lead-quality')).toBeHidden();
    });
  }
});

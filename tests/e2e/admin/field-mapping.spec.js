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
        expect(ourUrl).toBe(new URL(form.helper_url).href);
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

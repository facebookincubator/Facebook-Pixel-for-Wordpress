/**
 * Lead form helpers, one entry per supported form integration.
 *
 * The Meta Pixel for WordPress "Lead" event is dual-channel: when a form plugin
 * reports a successful submit, its integration (integration/class-facebookwordpress*.php)
 * tracks a server-side (CAPI) Lead and emits the matching Pixel code with the
 * SAME event_id, so PixelCapture sees the /tr hit and the two channels deduplicate.
 * How the Pixel code reaches the browser depends on how the plugin submits:
 *
 * - AJAX (Contact Form 7, WPForms, Ninja Forms): the code rides the AJAX response
 *   and the plugin's injected footer listener evals it.
 * - Full-page POST (Formidable Forms, Mailchimp for WP): the code is printed
 *   inline on the re-rendered page.
 *
 * Each form and page is seeded by tests/e2e/scripts/seed-store.sh.
 */

const { TIMEOUTS } = require('../constants/timeouts');
const { execWP } = require('../wordpress/exec');

const DEFAULT_LEAD = {
  name: 'Jane E2E Doe',
  // How integrations that split a full name report it (first token / rest), and
  // what first-name-only fields are filled with.
  firstName: 'Jane',
  lastName: 'E2E Doe',
  email: 'jane.e2e@example.com',
  phone: '+15555550123',
  message: 'E2E lead message',
};

/**
 * Per-integration fixture: Playwright tag (each integration's CI workflow runs
 * only its tag), page path, form/field selectors, the success state that marks
 * the point where the plugin delivers the Lead, and the CAPI name keys the
 * integration derives from the submitted name (`nameKeys`). Every field must be
 * on the form unless listed in `optionalFields`.
 */
const LEAD_FORMS = {
  'contact-form-7': {
    label: 'Contact Form 7',
    tag: '@lead-contact-form-7',
    path: process.env.TEST_LEAD_FORM_URL || '/e2e-lead/',
    form: 'form.wpcf7-form',
    fields: {
      name: 'input[name="your-name"]',
      email: 'input[name="your-email"]',
      phone: 'input[name="your-phone"]',
    },
    optionalFields: ['phone'],
    // A text field whose name contains "name" is split into fn/ln.
    nameKeys: ['fn', 'ln'],
    submit: '.wpcf7-submit',
    // CF7 5.x adds `.sent` to the <form>; 4.x sets `.wpcf7-mail-sent-ok` on the
    // response output. Either is the state that triggers `wpcf7mailsent`.
    success: 'form.wpcf7-form.sent, .wpcf7-response-output.wpcf7-mail-sent-ok',
  },
  wpforms: {
    label: 'WPForms Lite',
    tag: '@lead-wpforms',
    path: '/e2e-lead-wpforms/',
    form: 'form.wpforms-form',
    fields: {
      name: 'input[name="wpforms[fields][1]"]',
      email: 'input[name="wpforms[fields][2]"]',
    },
    // A "simple" format Name field is split into fn/ln.
    nameKeys: ['fn', 'ln'],
    submit: 'button.wpforms-submit',
    success: '.wpforms-confirmation-container-full',
  },
  'ninja-forms': {
    label: 'Ninja Forms',
    tag: '@lead-ninja-forms',
    path: '/e2e-lead-ninja-forms/',
    // Rendered client-side (Backbone) after page load.
    form: '.nf-form-cont form',
    fields: {
      name: 'input[name$="-textbox"]',
      email: 'input[type="email"]',
      message: 'textarea',
    },
    // A field keyed name* is split into fn/ln.
    nameKeys: ['fn', 'ln'],
    submit: 'input[type="submit"]',
    success: '.nf-response-msg:not(:empty)',
  },
  formidable: {
    label: 'Formidable Forms',
    tag: '@lead-formidable',
    path: '/e2e-lead-formidable/',
    form: 'form.frm-show-form',
    fields: {
      firstName: '#field_e2e_lead_name',
      email: '#field_e2e_lead_email',
    },
    // The seeded Name field has description "First", so its whole value is fn.
    nameKeys: ['fn'],
    submit: 'button.frm_button_submit',
    success: '.frm_message',
  },
  'mailchimp-for-wp': {
    label: 'Mailchimp for WP',
    tag: '@lead-mailchimp-for-wp',
    path: '/e2e-lead-mailchimp/',
    form: 'form.mc4wp-form',
    fields: {
      firstName: 'input[name="FNAME"]',
      email: 'input[name="EMAIL"]',
    },
    // FNAME maps to fn as-is; the fixture has no LNAME field.
    nameKeys: ['fn'],
    submit: 'input[type="submit"]',
    success: 'form.mc4wp-form-success',
    // MC4WP's anti-spam rejects submits less than 2s after the form rendered.
    minFormAgeMs: 3000,
  },
};

/**
 * Check the pixel's cached matching settings before submitting a Lead. Name
 * fields disabled in Events Manager are filtered out even when extraction works.
 */
async function assertLeadMatchingSettings(formKey) {
  const fixture = LEAD_FORMS[formKey];
  if (!fixture) throw new Error(`Unknown lead form: ${formKey}`);

  const { stdout } = await execWP(`
    $settings = \\FacebookPixelPlugin\\Core\\FacebookWordpressOptions::get_aam_settings();
    echo json_encode(array(
      'enabled' => $settings ? (bool) $settings->getEnableAutomaticMatching() : false,
      'fields' => $settings ? array_values((array) $settings->getEnabledAutomaticMatchingFields()) : array(),
    ));
  `);
  const settings = JSON.parse(stdout.trim());
  const required = ['em', ...fixture.nameKeys];
  const missing = required.filter(key => !settings.enabled || !settings.fields.includes(key));
  if (missing.length > 0) {
    throw new Error(
      `${fixture.label} Lead prerequisite failed. Enable automatic matching for ${required.join(', ')} `
      + `on the test pixel in Meta Events Manager and refresh the plugin's cached matching settings. `
      + `Missing: ${missing.join(', ')}`
    );
  }
}

/**
 * Fill and submit a seeded lead form, waiting for the plugin's success state
 * (which is when the integration fires the Lead).
 *
 * @param {import('@playwright/test').Page} page
 * @param {keyof typeof LEAD_FORMS} formKey  Which integration's form to submit.
 * @param {{name?: string, firstName?: string, lastName?: string, email?: string, phone?: string, message?: string}} [lead]
 */
async function submitLeadForm(page, formKey, lead = {}) {
  const fixture = LEAD_FORMS[formKey];
  if (!fixture) throw new Error(`Unknown lead form: ${formKey}`);
  const data = { ...DEFAULT_LEAD, ...lead };

  await page.goto(fixture.path, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });

  const form = page.locator(fixture.form).first();
  await form.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });
  const renderedAt = Date.now();

  for (const [field, selector] of Object.entries(fixture.fields)) {
    const input = form.locator(selector).first();
    if ((await input.count()) > 0) {
      await input.fill(data[field]);
    } else if (!(fixture.optionalFields || []).includes(field)) {
      // A missing required field would otherwise surface as an unclear
      // success-state timeout (or a Lead without the data under test).
      throw new Error(`${fixture.label} form at ${fixture.path} has no "${field}" field (${selector})`);
    }
  }

  const remainingAgeMs = (fixture.minFormAgeMs || 0) - (Date.now() - renderedAt);
  if (remainingAgeMs > 0) {
    await page.waitForTimeout(remainingAgeMs);
  }

  // Focus first so the last field's blur handlers run before the click. WPForms'
  // mailcheck inserts a "Did you mean ...?" hint on blur, which pushes the button
  // down between mousedown and mouseup and swallows the click; click() then waits
  // for the button to be stable in its new position.
  const submit = form.locator(fixture.submit).first();
  await submit.focus();
  await submit.click();

  await page
    .locator(fixture.success)
    .first()
    .waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  return data;
}

module.exports = { assertLeadMatchingSettings, submitLeadForm, LEAD_FORMS, DEFAULT_LEAD };

/**
 * Contact Form 7 Lead helpers.
 *
 * The Meta Pixel for WordPress "Lead" event is dual-channel: on `wpcf7_submit`
 * the CF7 integration (integration/class-facebookwordpresscontactform7.php) tracks
 * a server-side (CAPI) Lead via FacebookServerSideEvent::track(), and returns the
 * matching Pixel code in the AJAX response under `fb_pxl_code`. The plugin's
 * injected `wpcf7mailsent` listener `eval`s that code, firing `fbq('track','Lead',
 * ..., {eventID})` with the SAME event_id — so PixelCapture sees the /tr hit and
 * the two channels deduplicate.
 *
 * The seeded form (see tests/e2e/scripts/seed-store.sh) exposes:
 *   [text* your-name] [email* your-email] [tel your-phone]
 */

const { TIMEOUTS } = require('../constants/timeouts');

const DEFAULT_LEAD = {
  name: 'Jane E2E Doe',
  email: 'jane.e2e@example.com',
  phone: '+15555550123',
};

/**
 * Fill and submit the seeded Contact Form 7 form, waiting for the successful
 * `wpcf7mailsent` outcome (which is when the plugin fires the Lead pixel).
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} url  Absolute URL of the page embedding the CF7 shortcode.
 * @param {{name?: string, email?: string, phone?: string}} [lead]
 */
async function submitLeadForm(page, url, lead = {}) {
  const data = { ...DEFAULT_LEAD, ...lead };

  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });

  const form = page.locator('form.wpcf7-form');
  await form.waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  await form.locator('input[name="your-name"]').fill(data.name);
  await form.locator('input[name="your-email"]').fill(data.email);

  const phoneInput = form.locator('input[name="your-phone"]');
  if ((await phoneInput.count()) > 0) {
    await phoneInput.fill(data.phone);
  }

  await form.locator('.wpcf7-submit').click();

  // CF7 submits via AJAX and toggles a status class on the response output.
  // `.wpcf7-mail-sent-ok` is the success state that triggers `wpcf7mailsent`.
  await form
    .locator('.wpcf7-response-output.wpcf7-mail-sent-ok, form.sent .wpcf7-response-output')
    .first()
    .waitFor({ state: 'visible', timeout: TIMEOUTS.LONG });

  return data;
}

/**
 * @param {import('@playwright/test').Page} page
 * @param {string} url
 * @returns {Promise<boolean>} Whether a CF7 form is present on the page.
 */
async function isLeadFormAvailable(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: TIMEOUTS.MAX });
  return (await page.locator('form.wpcf7-form').count()) > 0;
}

module.exports = { submitLeadForm, isLeadFormAvailable, DEFAULT_LEAD };

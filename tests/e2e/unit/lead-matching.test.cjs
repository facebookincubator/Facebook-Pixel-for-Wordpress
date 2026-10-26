const assert = require('node:assert/strict');
const { test } = require('node:test');
const wordpress = require('../helpers/js/wordpress/exec');

// Stub only the WordPress boundary. No local site or real pixel is accessed.
let settings;
let reads = 0;
const originalExecWP = wordpress.execWP;
wordpress.execWP = async () => {
  reads++;
  return { stdout: JSON.stringify(settings), stderr: '' };
};
const { assertLeadMatchingSettings } = require('../helpers/js/events/lead-form');
wordpress.execWP = originalExecWP;

test('split-name integrations accept email, first-name, and last-name matching', async () => {
  settings = { enabled: true, fields: ['em', 'fn', 'ln'] };
  for (const key of ['contact-form-7', 'wpforms', 'ninja-forms']) {
    await assertLeadMatchingSettings(key);
  }
});

test('email-only matching fails with actionable missing-name diagnostics', async () => {
  settings = { enabled: true, fields: ['em'] };
  await assert.rejects(assertLeadMatchingSettings('contact-form-7'), error => {
    assert.match(error.message, /Contact Form 7/);
    assert.match(error.message, /fn, ln/);
    assert.match(error.message, /Events Manager/);
    assert.match(error.message, /cached matching settings/);
    return true;
  });
});

test('first-name-only integrations do not require last-name matching', async () => {
  settings = { enabled: true, fields: ['em', 'fn'] };
  await assertLeadMatchingSettings('formidable');
  await assertLeadMatchingSettings('mailchimp-for-wp');
  await assert.rejects(assertLeadMatchingSettings('wpforms'), /Missing: ln/);
});

test('matching disabled or unavailable rejects even if fields are listed', async () => {
  settings = { enabled: false, fields: ['em', 'fn', 'ln'] };
  await assert.rejects(assertLeadMatchingSettings('wpforms'), /Missing: em, fn, ln/);
  settings = { enabled: false, fields: [] };
  await assert.rejects(assertLeadMatchingSettings('formidable'), /Missing: em, fn/);
});

test('name matching does not replace the existing email prerequisite', async () => {
  settings = { enabled: true, fields: ['fn', 'ln'] };
  await assert.rejects(assertLeadMatchingSettings('ninja-forms'), /Missing: em/);
});

test('unknown integrations fail before accessing WordPress', async () => {
  const previousReads = reads;
  await assert.rejects(assertLeadMatchingSettings('unknown'), /Unknown lead form/);
  assert.equal(reads, previousReads);
});

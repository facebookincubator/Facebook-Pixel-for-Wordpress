#!/usr/bin/env bash
#
# Seed the local WordPress install (WORDPRESS_PATH) for the Form Field Mapping
# admin E2E spec (tests/e2e/admin/field-mapping.spec.js).
#
# - When the plugin is not connected, marks it as connected with a placeholder
#   pixel id and token: the Form Field Mapping section only needs both to be
#   set, and admin pages send no events, so no real Meta credentials are
#   needed. An existing connection (either connection flow) is left as is, and
#   only empty settings are filled, so an existing pixel id is never replaced.
# - Creates one form in each supported form plugin (Contact Form 7, WPForms
#   Lite, Ninja Forms, Formidable Forms, Mailchimp for WP), plus a second
#   Contact Form 7 form for the editor popup test.
# - Adds a mapping for a Contact Form 7 form id that does not exist, titled
#   like the live form, as a deleted form leaves behind. Other saved mappings
#   are kept.
# - Writes the forms to FIELD_MAPPING_FORMS_FILE (JSON) for the spec, with the
#   URLs Formidable's and Mailchimp for WP's own edit-link helpers return.
# - Installs the mu-plugin tests/e2e/fixtures/mu-plugins/e2e-dataset-quality-log.php,
#   which logs the URL of each Dataset Quality API request (it changes nothing),
#   so the spec can count them.
#
# Every form plugin must be active: a missing one fails the seed, so the spec
# never passes without checking it.
#
# Run it against a disposable test site: the forms it creates are not removed.
#
# Required env:
#   WORDPRESS_PATH, FIELD_MAPPING_FORMS_FILE
# Optional env:
#   FB_PIXEL_ID, FB_ACCESS_TOKEN  real Meta credentials, for the Lead event
#                                 quality tests (CI uses the QA ones)
set -euo pipefail

: "${WORDPRESS_PATH:?WORDPRESS_PATH must be set (path to the WordPress install)}"
: "${FIELD_MAPPING_FORMS_FILE:?FIELD_MAPPING_FORMS_FILE must be set (where to write the seeded forms)}"

WP_CLI="${WP_CLI_PATH:-wp}"

# Optional real Meta credentials (the QA ones in CI), trimmed and checked. The
# PHP below reads them from the environment: they are never written into the
# command, where a failure or a process listing could show them unmasked.
FB_PIXEL_ID="$(printf '%s' "${FB_PIXEL_ID:-}" | tr -d '[:space:]')"
FB_ACCESS_TOKEN="$(printf '%s' "${FB_ACCESS_TOKEN:-}" | tr -d '[:space:]')"
if [ -n "$FB_PIXEL_ID" ] && ! printf '%s' "$FB_PIXEL_ID" | grep -Eq '^[0-9]+$'; then
  echo "❌ FB_PIXEL_ID is not all digits after trimming — check the QA secret value." >&2
  exit 1
fi
if [ -n "$FB_ACCESS_TOKEN" ] && ! printf '%s' "$FB_ACCESS_TOKEN" | grep -Eq '^[A-Za-z0-9_-]+$'; then
  echo "❌ FB_ACCESS_TOKEN has unexpected characters — check the QA secret value." >&2
  exit 1
fi
export FB_PIXEL_ID FB_ACCESS_TOKEN
mkdir -p "$(dirname "$FIELD_MAPPING_FORMS_FILE")"
FORMS_FILE="$(cd "$(dirname "$FIELD_MAPPING_FORMS_FILE")" && pwd)/$(basename "$FIELD_MAPPING_FORMS_FILE")"

read -r -d '' PHP_SEED <<PHP || true
\$missing = array();
foreach (array(
  'Contact Form 7'   => class_exists('WPCF7_ContactForm'),
  'WPForms Lite'     => function_exists('wpforms_encode'),
  'Ninja Forms'      => function_exists('Ninja_Forms'),
  'Formidable Forms' => class_exists('FrmForm') && class_exists('FrmAppController'),
  'Mailchimp for WP' => function_exists('mc4wp_get_form'),
) as \$plugin => \$active) {
  if (!\$active) { \$missing[] = \$plugin; }
}
if (\$missing) {
  fwrite(STDERR, 'Form plugins not active: ' . implode(', ', \$missing) . "\n");
  exit(1);
}

// --- Connected with the given Meta pixel and token (FB_PIXEL_ID,
// FB_ACCESS_TOKEN: the QA ones in CI); without them, an existing connection is
// kept, or placeholder credentials fill the empty settings (the Lead event
// quality tests then skip) ---
\$options_class = '\\FacebookPixelPlugin\\Core\\FacebookWordpressOptions';
\$config        = get_option('facebook_business_extension_config', array());
\$config        = is_array(\$config) ? \$config : array();
\$pixel_id      = (string) getenv('FB_PIXEL_ID');
\$access_token  = (string) getenv('FB_ACCESS_TOKEN');
if ('' !== \$pixel_id && '' !== \$access_token) {
  \$config['facebook_pixel_id']         = \$pixel_id;
  \$config['facebook_access_token']     = \$access_token;
  \$config['facebook_is_fbe_installed'] = '1';
  update_option('facebook_business_extension_config', \$config);
  echo "Meta connection set from FB_PIXEL_ID / FB_ACCESS_TOKEN.\n";
} elseif (class_exists(\$options_class) && '' !== (string) \$options_class::get_active_access_token()) {
  echo "Meta connection already set: left unchanged.\n";
} else {
  foreach (array(
    'facebook_pixel_id'         => '1234567890123456',
    'facebook_access_token'     => 'e2e-placeholder-token',
    'facebook_is_fbe_installed' => '1',
  ) as \$key => \$placeholder) {
    if (empty(\$config[\$key])) {
      \$config[\$key] = \$placeholder;
    }
  }
  update_option('facebook_business_extension_config', \$config);
  echo "Meta connection not set: placeholder credentials added.\n";
}

// --- Skip the form plugins' first-run welcome redirects ---
delete_transient('wpforms_activation_redirect');
delete_option('wpforms_activation_redirect');
delete_transient('frm_activation_redirect');

\$forms = array();
\$add = function (\$integration, \$label, \$id, \$title, \$extra = array()) use (&\$forms) {
  \$forms[] = array_merge(array(
    'integration' => \$integration,
    'label'       => \$label,
    'id'          => (string) \$id,
    'title'       => (string) \$title,
  ), \$extra);
};

// --- Contact Form 7: one form per test, so they don't share state ---
\$cf7_form = function (\$title) {
  \$form = WPCF7_ContactForm::get_template(array('title' => \$title));
  \$form->set_properties(array('form' => "<label>Your name [text* your-name]</label>\n"
    . "<label>Your email [email* your-email]</label>\n"
    . "[submit \"Send\"]"));
  return \$form->save();
};
\$add('contact-form-7', 'Contact Form 7', \$cf7_form('E2E Mapping CF7'), 'E2E Mapping CF7');
\$popup_id = \$cf7_form('E2E Mapping Popup');

// --- WPForms Lite ---
\$wpf_id = wp_insert_post(array(
  'post_title'   => 'E2E Mapping WPForms',
  'post_status'  => 'publish',
  'post_type'    => 'wpforms',
  'post_content' => '{}',
));
wp_update_post(array('ID' => \$wpf_id, 'post_content' => wpforms_encode(array(
  'id'       => \$wpf_id,
  'field_id' => 3,
  'fields'   => array(
    '1' => array('id' => '1', 'type' => 'name', 'label' => 'Name', 'format' => 'simple', 'size' => 'medium'),
    '2' => array('id' => '2', 'type' => 'email', 'label' => 'Email', 'size' => 'medium'),
  ),
  'settings' => array('form_title' => 'E2E Mapping WPForms', 'submit_text' => 'Submit'),
))));
\$add('wpforms-lite', 'WPForms', \$wpf_id, 'E2E Mapping WPForms');

// --- Ninja Forms: its "Contact Me" template ---
\$nf_id = Ninja_Forms()->form()->import_form(Ninja_Forms::template('formtemplate-contactform.nff', array(), true));
\$add('ninja-forms', 'Ninja Forms', \$nf_id, Ninja_Forms()->form(\$nf_id)->get()->get_setting('title'));

// --- Formidable Forms: its tables are created on first wp-admin load, so
// run the installer first ---
FrmAppController::install();
\$frm_id = FrmForm::create(array('name' => 'E2E Mapping Formidable', 'description' => '', 'form_key' => 'e2e_mapping_' . wp_generate_password(6, false, false)));
FrmField::create(array_merge(FrmFieldsHelper::setup_new_vars('email', \$frm_id), array('name' => 'Email', 'field_key' => 'e2e_mapping_email_' . \$frm_id)));
\$add('formidable-lite', 'Formidable Forms', \$frm_id, 'E2E Mapping Formidable', array(
  'helper'     => 'FrmForm::get_edit_link',
  'helper_url' => method_exists('FrmForm', 'get_edit_link') ? FrmForm::get_edit_link(\$frm_id) : null,
));

// --- Mailchimp for WP: the markup is raw inputs, which kses would strip ---
kses_remove_filters();
\$mc4wp_id = wp_insert_post(array(
  'post_title'   => 'E2E Mapping Mailchimp',
  'post_status'  => 'publish',
  'post_type'    => 'mc4wp-form',
  'post_content' => "<p><label>Email address <input type=\"email\" name=\"EMAIL\" required></label></p>\n"
    . "<p><input type=\"submit\" value=\"Sign up\"></p>",
));
\$add('mailchimp-for-wp', 'Mailchimp for WP', \$mc4wp_id, 'E2E Mapping Mailchimp', array(
  'helper'     => 'mc4wp_get_edit_form_url',
  'helper_url' => function_exists('mc4wp_get_edit_form_url') ? mc4wp_get_edit_form_url(\$mc4wp_id) : null,
));

// --- A mapping left behind by a deleted Contact Form 7 form, with the same
// title as the live one, added to the saved mappings ---
\$orphan_id = '999999';
\$mappings  = get_option('facebook_form_field_mappings', array());
\$mappings  = is_array(\$mappings) ? \$mappings : array();
if (!isset(\$mappings['contact-form-7']) || !is_array(\$mappings['contact-form-7'])) {
  \$mappings['contact-form-7'] = array();
}
\$mappings['contact-form-7'][\$orphan_id] = array(
  'form_title' => 'E2E Mapping CF7',
  'rejected'   => false,
  'fields'     => array('your-email' => array('included' => true, 'target' => 'email', 'label' => 'your-email')),
);
update_option('facebook_form_field_mappings', \$mappings, false);

\$data = array(
  'forms'  => \$forms,
  'popup'  => array('integration' => 'contact-form-7', 'label' => 'Contact Form 7', 'id' => (string) \$popup_id, 'title' => 'E2E Mapping Popup'),
  'orphan' => array('integration' => 'contact-form-7', 'label' => 'Contact Form 7', 'id' => \$orphan_id, 'title' => 'E2E Mapping CF7'),
);
file_put_contents('${FORMS_FILE}', json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo json_encode(\$data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
PHP

# --- Log Dataset Quality API requests (the Lead event quality panel) ---
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
mkdir -p "$WORDPRESS_PATH/wp-content/mu-plugins"
cp "$SCRIPT_DIR/../fixtures/mu-plugins/e2e-dataset-quality-log.php" "$WORDPRESS_PATH/wp-content/mu-plugins/"

ENCODED="$(printf '%s' "$PHP_SEED" | base64 | tr -d '\n')"

echo "🌱 Seeding Form Field Mapping forms..."
"$WP_CLI" eval "eval(base64_decode('${ENCODED}'));" --path="$WORDPRESS_PATH" --allow-root
echo "✅ Seed complete: $FORMS_FILE"

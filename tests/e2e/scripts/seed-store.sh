#!/usr/bin/env bash
#
# Seed the local WordPress install (WORDPRESS_PATH) for the Pixel/CAPI E2E suite.
#
# Configures: pretty permalinks, a WooCommerce store (base country, currency,
# Cash-on-Delivery gateway), the Meta pixel config (pixel id + access token +
# FBE-installed + CAPI enabled), a logged-in customer with billing/shipping meta,
# a deterministic simple product, and one form + page per supported lead-form
# plugin (Contact Form 7, WPForms Lite, Ninja Forms, Formidable Forms, Mailchimp
# for WP) for the Lead tests. Each form plugin is optional: its fixture is only
# created when the plugin is active.
#
# All of the above is applied by a single base64-encoded `wp eval` so we never
# fight shell quoting of the payload, regardless of how wp is invoked.
#
# Required env:
#   FB_PIXEL_ID, FB_ACCESS_TOKEN
# Optional env (defaults shown):
#   WP_CUSTOMER_USERNAME=customer  WP_CUSTOMER_PASSWORD=customerpass
#   WP_CUSTOMER_EMAIL=customer@example.com
set -euo pipefail

: "${FB_PIXEL_ID:?FB_PIXEL_ID must be set (Meta pixel id)}"
: "${FB_ACCESS_TOKEN:?FB_ACCESS_TOKEN must be set (Meta CAPI access token)}"
: "${WORDPRESS_PATH:?WORDPRESS_PATH must be set (path to the WordPress install)}"

# Defensively strip whitespace: a secret pasted with a trailing newline/space
# would otherwise make the pixel id fail ctype_digit() and the plugin would
# inject no pixel at all. Pixel ids are all-digits; tokens have no whitespace.
FB_PIXEL_ID="$(printf '%s' "$FB_PIXEL_ID" | tr -d '[:space:]')"
FB_ACCESS_TOKEN="$(printf '%s' "$FB_ACCESS_TOKEN" | tr -d '[:space:]')"
if ! printf '%s' "$FB_PIXEL_ID" | grep -Eq '^[0-9]+$'; then
  echo "❌ FB_PIXEL_ID is not all digits after trimming — check the QA secret value." >&2
  exit 1
fi

CUSTOMER_USERNAME="${WP_CUSTOMER_USERNAME:-customer}"
CUSTOMER_PASSWORD="${WP_CUSTOMER_PASSWORD:-customerpass}"
CUSTOMER_EMAIL="${WP_CUSTOMER_EMAIL:-customer@example.com}"
WP_CLI="${WP_CLI_PATH:-wp}"
MAILCHIMP_MOCK_SRC="$(cd "$(dirname "${BASH_SOURCE[0]}")/../helpers/php" && pwd)/mailchimp-api-mock.php"

read -r -d '' PHP_SEED <<PHP || true
// --- Pretty permalinks (product/shop URLs) ---
global \$wp_rewrite;
\$wp_rewrite->set_permalink_structure('/%postname%/');
update_option('rewrite_rules', '');
\$wp_rewrite->flush_rules(false);

// --- WooCommerce store basics ---
update_option('woocommerce_store_address', '60 29th Street');
update_option('woocommerce_store_city', 'San Francisco');
update_option('woocommerce_default_country', 'US:CA');
update_option('woocommerce_store_postcode', '94110');
update_option('woocommerce_currency', 'USD');
update_option('woocommerce_allow_tracking', 'no');
update_option('woocommerce_coming_soon', 'no');
// Cash on Delivery so checkout can complete without a real gateway.
update_option('woocommerce_cod_settings', array(
  'enabled'      => 'yes',
  'title'        => 'Cash on delivery',
  'description'  => 'Pay with cash upon delivery.',
  'instructions' => 'Pay with cash upon delivery.',
  'enable_for_methods' => array(),
  'enable_for_virtual' => 'yes',
));

// --- Meta pixel / CAPI configuration ---
update_option('facebook_business_extension_config', array(
  'facebook_pixel_id'         => '${FB_PIXEL_ID}',
  'facebook_access_token'     => '${FB_ACCESS_TOKEN}',
  'facebook_is_fbe_installed' => '1',
));
update_option('facebook_capi_integration_status', '1');
// Keep PageView OUT of the CAPI events filter so CAPI PageView (via OpenBridge)
// is enabled — this is the default, set explicitly for determinism.
update_option('facebook_capi_integration_events_filter', 'Microdata,SubscribedButtonClick');
// CAPI Gateway on (default) — required for the OpenBridge / optinMetaEnabledCapi path.
update_option('facebook_capig', '1');

// --- Customer user (matches WP_CUSTOMER_USERNAME used by global-setup) ---
\$customer = get_user_by('login', '${CUSTOMER_USERNAME}');
if (!\$customer) {
  \$customer_id = wp_insert_user(array(
    'user_login' => '${CUSTOMER_USERNAME}',
    'user_pass'  => '${CUSTOMER_PASSWORD}',
    'user_email' => '${CUSTOMER_EMAIL}',
    'role'       => 'customer',
    'first_name' => 'Casey',
    'last_name'  => 'Customer',
  ));
} else {
  \$customer_id = \$customer->ID;
}
if (!is_wp_error(\$customer_id)) {
  \$meta = array(
    'billing_first_name' => 'Casey', 'billing_last_name' => 'Customer',
    'billing_email' => '${CUSTOMER_EMAIL}', 'billing_phone' => '+15555550123',
    'billing_address_1' => '60 29th Street', 'billing_city' => 'San Francisco',
    'billing_state' => 'CA', 'billing_postcode' => '94110', 'billing_country' => 'US',
    'shipping_first_name' => 'Casey', 'shipping_last_name' => 'Customer',
    'shipping_address_1' => '60 29th Street', 'shipping_city' => 'San Francisco',
    'shipping_state' => 'CA', 'shipping_postcode' => '94110', 'shipping_country' => 'US',
  );
  foreach (\$meta as \$k => \$v) { update_user_meta(\$customer_id, \$k, \$v); }
}

// --- Deterministic simple product at /product/testp/ ---
if (function_exists('wc_get_product') && !get_page_by_path('testp', OBJECT, 'product')) {
  \$product = new WC_Product_Simple();
  \$product->set_name('E2E Test Product');
  \$product->set_slug('testp');
  \$product->set_sku('E2E-TESTP');
  \$product->set_regular_price('12.34');
  \$product->set_status('publish');
  \$product->set_catalog_visibility('visible');
  \$product->save();
  echo 'PRODUCT_ID=' . \$product->get_id() . "\n";
}

// --- Force wp_mail() success so Contact Form 7 reports mail_sent in CI ---
// (no SMTP in the test env; the Lead event only fires on a 'mail_sent' status).
\$mu_dir = defined('WPMU_PLUGIN_DIR') ? WPMU_PLUGIN_DIR : WP_CONTENT_DIR . '/mu-plugins';
if (!is_dir(\$mu_dir)) { wp_mkdir_p(\$mu_dir); }
// wpcf7_skip_mail makes CF7 report 'mail_sent' without actually sending (the
// canonical CI approach); pre_wp_mail is a belt-and-suspenders fallback.
file_put_contents(\$mu_dir . '/e2e-mailer.php', "<?php add_filter('wpcf7_skip_mail', '__return_true'); add_filter('pre_wp_mail', '__return_true');\n");

// --- Contact Form 7 form + page for Lead tests ---
if (class_exists('WPCF7_ContactForm')) {
  \$existing = get_posts(array('post_type' => 'wpcf7_contact_form', 'title' => 'E2E Lead Form', 'posts_per_page' => 1, 'post_status' => 'any'));
  if (empty(\$existing)) {
    \$form_markup = "<label>Your name [text* your-name]</label>\n"
      . "<label>Your email [email* your-email]</label>\n"
      . "<label>Your phone [tel your-phone]</label>\n"
      . "[submit \"Send\"]";
    \$cf7 = WPCF7_ContactForm::get_template(array('title' => 'E2E Lead Form'));
    \$cf7->set_properties(array('form' => \$form_markup));
    \$form_id = \$cf7->save();
    // Modern CF7 shortcodes resolve by hash; fall back to the numeric id.
    \$form_ref = method_exists(\$cf7, 'hash') && \$cf7->hash() ? \$cf7->hash() : \$form_id;
    \$page_id = wp_insert_post(array(
      'post_title'   => 'E2E Lead',
      'post_name'    => 'e2e-lead',
      'post_status'  => 'publish',
      'post_type'    => 'page',
      'post_content' => '[contact-form-7 id="' . \$form_ref . '" title="E2E Lead Form"]',
    ));
    echo 'LEAD_FORM_ID=' . \$form_id . ' LEAD_PAGE=' . get_permalink(\$page_id) . "\n";
  }
}

// --- Lead pages for the other form integrations (one page per plugin) ---
\$e2e_lead_page = function (\$slug, \$title, \$shortcode) {
  \$page_id = wp_insert_post(array(
    'post_title'   => \$title,
    'post_name'    => \$slug,
    'post_status'  => 'publish',
    'post_type'    => 'page',
    'post_content' => \$shortcode,
  ));
  echo 'LEAD_PAGE=' . get_permalink(\$page_id) . "\n";
};

// WPForms Lite: AJAX submit (the Lead pixel rides the admin-ajax response).
// Anti-spam off so the submit isn't gated on token timing.
if (function_exists('wpforms_encode') && !get_page_by_path('e2e-lead-wpforms')) {
  \$wpf_id = wp_insert_post(array(
    'post_title'   => 'E2E Lead Form',
    'post_status'  => 'publish',
    'post_type'    => 'wpforms',
    'post_content' => '{}',
  ));
  \$wpf_data = array(
    'id'       => \$wpf_id,
    'field_id' => 3,
    'fields'   => array(
      '1' => array('id' => '1', 'type' => 'name', 'label' => 'Name', 'format' => 'simple', 'required' => '1', 'size' => 'medium'),
      '2' => array('id' => '2', 'type' => 'email', 'label' => 'Email', 'required' => '1', 'size' => 'medium'),
    ),
    'settings' => array(
      'form_title'             => 'E2E Lead Form',
      'submit_text'            => 'Submit',
      'submit_text_processing' => 'Sending...',
      'ajax_submit'            => '1',
      'antispam'               => '0',
      'antispam_v3'            => '0',
      'notification_enable'    => '0',
      'confirmations'          => array(
        '1' => array('type' => 'message', 'message' => 'Thanks, E2E lead received.', 'message_scroll' => '1'),
      ),
    ),
  );
  wp_update_post(array('ID' => \$wpf_id, 'post_content' => wpforms_encode(\$wpf_data)));
  \$e2e_lead_page('e2e-lead-wpforms', 'E2E Lead WPForms', '[wpforms id="' . \$wpf_id . '"]');
}

// Ninja Forms: reuse the "Contact Me" form (name/email/message + success
// message action) it creates on activation; import that template if missing.
if (function_exists('Ninja_Forms') && !get_page_by_path('e2e-lead-ninja-forms')) {
  \$nf_forms = Ninja_Forms()->form()->get_forms();
  \$nf_form_id = empty(\$nf_forms)
    ? Ninja_Forms()->form()->import_form(Ninja_Forms::template('formtemplate-contactform.nff', array(), true))
    : reset(\$nf_forms)->get_id();
  \$e2e_lead_page('e2e-lead-ninja-forms', 'E2E Lead Ninja Forms', '[ninja_form id="' . \$nf_form_id . '"]');
}

// Formidable Forms: non-AJAX submit (the Lead pixel prints inline on the
// re-rendered page). Formidable creates its tables on first wp-admin load, not
// on activation, so run its installer first.
if (class_exists('FrmForm') && !get_page_by_path('e2e-lead-formidable')) {
  FrmAppController::install();
  \$frm_form_id = FrmForm::create(array('name' => 'E2E Lead Form', 'description' => '', 'form_key' => 'e2e_lead_form'));
  \$frm_fields = array(
    // Name + "First" description is what the integration maps to first_name.
    array('type' => 'text', 'name' => 'Name', 'description' => 'First', 'field_key' => 'e2e_lead_name'),
    array('type' => 'email', 'name' => 'Email', 'description' => '', 'field_key' => 'e2e_lead_email'),
  );
  foreach (\$frm_fields as \$frm_field) {
    FrmField::create(array_merge(FrmFieldsHelper::setup_new_vars(\$frm_field['type'], \$frm_form_id), \$frm_field, array('required' => 1)));
  }
  \$e2e_lead_page('e2e-lead-formidable', 'E2E Lead Formidable', '[formidable id="' . \$frm_form_id . '"]');
}

// Mailchimp for WP: non-AJAX submit against the mocked Mailchimp API (see
// tests/e2e/helpers/php/mailchimp-api-mock.php). The form markup is raw HTML
// inputs, which kses would strip without a privileged user.
if (function_exists('mc4wp_get_form') && !get_page_by_path('e2e-lead-mailchimp')) {
  copy('${MAILCHIMP_MOCK_SRC}', \$mu_dir . '/e2e-mailchimp-api-mock.php');
  update_option('mc4wp', array_merge((array) get_option('mc4wp', array()), array('api_key' => 'e2e_mock_key-us1')));
  kses_remove_filters();
  \$mc4wp_id = wp_insert_post(array(
    'post_title'   => 'E2E Lead Form',
    'post_status'  => 'publish',
    'post_type'    => 'mc4wp-form',
    'post_content' => "<p><label>First name <input type=\"text\" name=\"FNAME\"></label></p>\n"
      . "<p><label>Email address <input type=\"email\" name=\"EMAIL\" required></label></p>\n"
      . "<p><input type=\"submit\" value=\"Sign up\"></p>",
  ));
  update_post_meta(\$mc4wp_id, '_mc4wp_settings', array('lists' => array('e2e-list'), 'double_optin' => 0));
  \$e2e_lead_page('e2e-lead-mailchimp', 'E2E Lead Mailchimp', '[mc4wp_form id="' . \$mc4wp_id . '"]');
}

echo 'SEED_OK pixel=' . \FacebookPixelPlugin\Core\FacebookWordpressOptions::get_active_pixel_id() . "\n";
PHP

ENCODED="$(printf '%s' "$PHP_SEED" | base64 | tr -d '\n')"

echo "🌱 Seeding store + pixel config..."
"$WP_CLI" eval "eval(base64_decode('${ENCODED}'));" --path="$WORDPRESS_PATH" --allow-root
echo "✅ Seed complete."

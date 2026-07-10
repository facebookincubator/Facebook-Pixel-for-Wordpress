#!/usr/bin/env bash
#
# Seed the local WordPress install (WORDPRESS_PATH) for the Pixel/CAPI E2E suite.
#
# Configures: pretty permalinks, a WooCommerce store (base country, currency,
# Cash-on-Delivery gateway), the Meta pixel config (pixel id + access token +
# FBE-installed + CAPI enabled), a logged-in customer with billing/shipping meta,
# a deterministic simple product, and a Contact Form 7 form + page for Lead tests.
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
file_put_contents(\$mu_dir . '/e2e-mailer.php', "<?php add_filter('pre_wp_mail', '__return_true');\n");

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
    \$page_id = wp_insert_post(array(
      'post_title'   => 'E2E Lead',
      'post_name'    => 'e2e-lead',
      'post_status'  => 'publish',
      'post_type'    => 'page',
      'post_content' => '[contact-form-7 id="' . \$form_id . '" title="E2E Lead Form"]',
    ));
    echo 'LEAD_FORM_ID=' . \$form_id . ' LEAD_PAGE=' . get_permalink(\$page_id) . "\n";
  }
}

echo 'SEED_OK pixel=' . \FacebookPixelPlugin\Core\FacebookWordpressOptions::get_active_pixel_id() . "\n";
PHP

ENCODED="$(printf '%s' "$PHP_SEED" | base64 | tr -d '\n')"

echo "🌱 Seeding store + pixel config..."
"$WP_CLI" eval "eval(base64_decode('${ENCODED}'));" --path="$WORDPRESS_PATH" --allow-root
echo "✅ Seed complete."

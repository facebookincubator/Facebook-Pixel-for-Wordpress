=== Meta pixel for WordPress ===
Contributors: facebook
Tags: Facebook, Facebook Conversion Pixel, Facebook Pixel, Facebook Pixel Events, Conversions API, facebook retargeting, facebook standard events, Meta, Meta pixel, Meta Conversion API,
Requires at least: 4.4
Tested up to: 6.0
Requires PHP: 5.6
Stable tag: 3.0.7
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Grow your business with Meta for WordPress!

== Description ==
This plugin will install a Meta Pixel for your page so you can capture the actions people take when they interact with your page, such as Lead, ViewContent, AddToCart, InitiateCheckout and Purchase events. It also includes support for the Conversions API.

You’ll be able to see when customers took an action after seeing your ad on Facebook and Instagram, which can help you with retargeting. And when you use the Conversions API alongside the Pixel, it creates a more reliable connection that helps the delivery system decrease your costs. [Learn More](https://www.facebook.com/business/learn/facebook-ads-pixel)

The Conversions API is designed to create a direct connection between your marketing data and the Meta systems, which help optimise ad targeting, decrease cost per action and measure results across Meta technologies. [Learn More](https://www.facebook.com/business/help/2041148702652965?id=818859032317965)

== Installation ==
__To install from your WordPress site__ <br />
1. Log in to your WordPress dashboard, navigate to the Plugins menu and click Add New. <br />
2. In the search field, type 'Facebook for WordPress' and click 'Search Plugins'. Select the plugin authored by 'Facebook'. You can install it by simply clicking 'Install Now'. <br />

__To download and install plugin from Facebook Events Manager__ <br />
[Facebook Help Page](https://www.facebook.com/business/help/881403525362441) <br />

__Configure plugin for first use__ <br />
After plugin installed: <br />
1. Go to settings page of the plugin. <br />
2. Click Get Started. <br />
3. Complete the Facebook Business Extension flow. <br />
4. Agree to share your access token with your site. <br />

__Enable sending events via conversion API__ <br />
You must complete the Facebook Business Extension flow before enabling sending events via conversions API.
1. Go to settings page of the plugin. <br />
2. Under, Meta Advanced Configuration, toggle the checkbox to enable/disable send website events to Meta using Conversions API

== Frequently Asked Questions ==
= Where can I find more information on Facebook Pixel? =
You can find more information on the [Facebook Pixel](https://www.facebook.com/business/learn/facebook-ads-pixel).

= Where can I find more information on Conversions API? =
You can find more information on the [Conversions API](https://www.facebook.com/business/help/2041148702652965).

= Where can I find more information on Facebook for WordPress plugin? =
You can refer to [this page](https://www.facebook.com/business/help/881403525362441?helpref=faq_content)

= Where can I find support? =
If you get stuck, or have any questions, you can ask for help in the [Facebook for WordPress plugin forum](https://wordpress.org/support/plugin/official-facebook-pixel).

= I am a developer. Can I help improve the plugin? =
Of course! This plugin is open sourced on the Facebook Incubator GitHub. You can find the code and contribution instructions in the [plugin repository](https://github.com/facebookincubator/Facebook-Pixel-for-WordPress).

== Changelog ==
= 2022-10-31 version 3.0.8 =
* Fix PII drop when converting Pixel payload to Conversion API payload
* Rebranded the extension from Facebook pixel to Meta pixel

= 2022-08-02 version 3.0.7 =
* Update Facebook Business SDK to v12.0.0
* Added the ability to send website events using conversion API and OpenBridge Javascript. This can be enabled/disabled from the settings page.
* Added ads metrics and insights on the settings page.

= 2021-08-30 version 3.0.6 =
* Update Facebook Business SDK to v11.0.0

= 2021-03-10 version 3.0.5 =
* Support for WordPress 5.7
* Fixing pixel events for Contact Form 7 in WP >= 5.7

= 2021-02-17 version 3.0.4 =
* Update Facebook Business SDK to v9.0.4
* Validating, sanitizing and escaping plugin settings

= 2021-02-12 version 3.0.3 =
* Adding nonce parameter to requests changing plugin settings

= 2021-02-09 version 3.0.2 =
* Removing Guzzle dependency

= 2021-01-28 version 3.0.1 =
* Support for WordPress 5.6
* Adding banner for plugin review
* Adding action_source parameter to Conversions API events
* Update Business SDK to v9.0.3

= 2021-01-06 version 3.0.0 =
* Adding Facebook Business Extension based configuration
* Renaming to Facebook for WordPress

= 2020-12-08 version 2.2.2 =
* Update Business SDK to v9.0.1

= 2020-11-04 version 2.2.1 =
* Stop sending events for internal users
* Fix Contact Form 7 integration bug, send events only on form submit success
* Update Facebook Business SDK to v8.0.2
* Requires PHP 5.6 or greater
* Sending ViewContent Conversions API event from WooCommerce
* Support for WooCommerce through Pixel and Conversions API

= 2020-08-14 version 2.2.0 =
* Support for WordPress 5.5
* Improved Conversions API event quality
* Sending AddToCart and ViewContent events from Easy Digitial Downloads
* New filter added before the Conversions API event is sent

= 2020-06-18 version 2.1.0 =
* Support for WooCommerce through the Conversions API

= 2020-04-23 version 2.0.2 =
* Support for WordPress 5.4
* Fixing an Illegal string offset error with WP Forms
* Fixing the event source url for Conversions API events

= 2020-03-23 version 2.0.1 =
* Fixing an Undefined index error

= 2020-03-09 version 2.0.0 =
* Added support for Conversions API [Learn More](https://developers.facebook.com/docs/marketing-api/conversions-api)

= 2019-12-02 version 1.8.0 =
* Support for WordPress 5.3
* Fix Gravity Forms confirmation redirect

= 2019-02-18 version 1.7.25 =
* remove get_called_class from the codebase

= 2019-02-10 version 1.7.24 =
* Fix for PHP 5.3
* Fix the Util function
* Fix Ninja Form

= 2019-01-29 version 1.7.23 =
* Add Gravity Forms
* Add Caldera Form
* Add Formidable Form

= 2019-01-20 version 1.7.22 =
* fix css asset error

= 2018-11-30 version 1.7.21 =
* fix abstract static function

= 2018-11-28 version 1.7.20 =
* Change plugin file name, Add Supports for MailChimp for WordPress and WP eCommerce

= 2018-11-20 version 1.7.19 =
* Support php 5.3 onwards

= 2018-11-09 version 1.7.18 =
* Fix translation and set the advanced matching on by default

= 2018-11-09 version 1.7.17 =
* Fix Lead event

= 2018-11-02 version 1.7.16 =
* Fix advance matching

== Upgrade Notice ==
By upgrading to latest version you will have built in support to fire lower funnel events: Lead, ViewContent, AddToCart, InitiateCheckout and Purchase out of the most popular plugins.

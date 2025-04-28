=== Meta pixel for WordPress ===
Contributors: facebook
Tags: Facebook, Meta, Conversions API, Pixel, Meta Ads
Requires at least: 5.7
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 4.1.3
License: GPLv2
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Grow your business with Meta for WordPress!

== Description ==
This plugin will install a Meta Pixel for your page so you can capture the actions people take when they interact with your page, such as Lead, ViewContent, AddToCart, InitiateCheckout and Purchase events. It also includes support for the Conversions API.

You’ll be able to see when customers took an action after seeing your ad on Facebook and Instagram, which can help you with retargeting. And when you use the Conversions API alongside the Pixel, it creates a more reliable connection that helps the delivery system decrease your costs. [Learn More](https://www.facebook.com/business/learn/facebook-ads-pixel)

The Conversions API is designed to create a direct connection between your marketing data and the Meta systems, which help optimise ad targeting, decrease cost per action and measure results across Meta technologies. [Learn More](https://www.facebook.com/business/help/2041148702652965?id=818859032317965)

You can find more information about our Privacy Policy [here](https://www.facebook.com/privacy/policy/?entry_point=data_policy_redirect&entry=0).

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
= 2025-04-23 version 4.1.3 =
* Added the OB script to the same block as Pixel init
* Minor improvements to the plugin's overall functionality
* Added a template for PRs

= 2025-03-20 version 4.1.2 =
* Add back CORS headers on the openbridge calls
* Updated the wordpress.org directory tags

= 2025-02-06 version 4.1.1 =
* Fix an issue that caused fatal error when upgrading to 4.1.0

= 2025-02-05 version 4.1.0 =
* Added Test CAPI functionality
* Fixed an issue with the MBE where user could not onboard
* Improved the way FBP & FBC are retrieved
* Minor improvements to the Plugin Settings page
* Move the noscript tag to body element
* improve the check for product categories

= 2024-09-24 version 4.0.1 =
* Updated the readme.txt to point to Meta Privacy Policy

== Upgrade Notice ==
By upgrading to latest version you will have built in support to fire lower funnel events: Lead, ViewContent, AddToCart, InitiateCheckout and Purchase out of the most popular plugins.

# Facebook Pixel for WordPress

Grow your business with Facebook for WordPress! This plugin will install a Facebook Pixel for your page. There is also built in support for other WordPress plugins, such as Contact Form 7, Easy Digital Downloads, Ninja Forms, and WPForms. The Facebook Pixel for WordPress allows you to fire lower funnel events: Lead, ViewContent, AddToCart, InitiateCheckout and Purchase. Tracking lower funnel events can help you understand the actions people are taking on your website. You can then use this information to make adjustments accordingly in your advertising campaigns.

# Requirements

Facebook Pixel for WordPress requires
* WordPress 4.4+ or higher
* PHP 5.6 or greater

# Get started

Clone this repo with the following command:

`$ git clone https://github.com/facebookincubator/wordpress-messenger-customer-chat-plugin.git`

Initiate the development environment:

1. Install Composer
2. Run the command to install the necessary package: `$ composer install`

Build the project and create the zip
Run the command to run tests and create the zip under build directory.

`$ vendor/bin/phing`

# Full Documentation

https://www.facebook.com/business/help/881403525362441

# How to integrate your plugins

1. Add your integration class under `integration/` folder
2. Extend the class from `FacebookWordpressIntegrationBase`
3. Define class variable `PLUGIN_FILE` to be your plugin PHP file
4. Define class variable `TRACKING_NAME` for tracking purpose, put this value under 'fb_wp_tracking' as a parameter in the pixel event
5. Define a public static function `injectPixelCode()` to inject pixel at your page
6. Add your unit test class under `tests/` folder
7. Extend the test class from `FacebookWordpressTestBase`
8. After the classes development, run tests by `$ vendor/bin/phing`

You can reference to integration/FacebookWordpressContactForm7.php and tests/FacebookWordpressContactForm7Test.php as an example

# Contributing

See the CONTRIBUTING file for how to help out

# License

Facebook Pixel for WordPress is GPLv2-licensed

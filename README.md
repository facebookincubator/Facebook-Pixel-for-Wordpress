# Facebook Pixel for Wordpress

Grow your business with Facebook for Wordpress! This plugin will install a Facebook Pixel for your page. There is also built in support for other Wordpress plugins, such as Contact Form 7, Easy Digital Downloads, Ninja Forms, and WPForms. The Facebook Pixel for Wordpress allows you to fire lower funnel events: Lead, ViewContent, AddToCart, InitiateCheckout and Purchase. Tracking lower funnel events can help you understand the actions people are taking on your website. You can then use this information to make adjustments accordingly in your advertising campaigns.

# Requirements

Facebook Pixel for Wordpress requires
* Wordpress 4.4+ or higher
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
4. Define a public static function `injectPixelCode()` to inject pixel at your page
5. Add your integration test class under `tests/` folder
6. Extend the test class from `FacebookWordpressTestBase`
7. After the classes development, run tests by `$ vendor/bin/phing`

You can reference to integration/FacebookWordpressContactForm7.php and tests/FacebookWordpressContactForm7Test.php as an example

# Contributing

See the CONTRIBUTING file for how to help out

# License

Facebook Pixel for Wordpress is GPLv2-licensed

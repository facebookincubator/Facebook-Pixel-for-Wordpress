# Contributing to Facebook for WordPress
We want to make contributing to this project as easy and transparent as
possible.

## Pull Requests
We actively welcome your pull requests.

1. Fork the repo and create your branch from `master`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Make sure your code lints.
5. If you haven't already, complete the Contributor License Agreement ("CLA").

### Get started

Clone this repo with the following command:

`$ git clone https://github.com/facebookincubator/facebook-pixel-for-wordpress.git`

Initiate the development environment:

1. Install Composer
2. Run the command to install the necessary package: `$ composer install`

Build the project and create the zip
Run the command to run tests and create the zip under build directory.

`$ vendor/bin/phing`

### How to integrate your plugins

1. Add your integration class under `integration/` folder
2. Extend the class from `FacebookWordpressIntegrationBase`
3. Define class variable `PLUGIN_FILE` to be your plugin PHP file
4. Define class variable `TRACKING_NAME` for tracking purpose, put this value under 'fb_wp_tracking' as a parameter in the pixel event
5. Define a public static function `injectPixelCode()` to inject pixel at your page
6. Add your unit test class under `tests/` folder
7. Extend the test class from `FacebookWordpressTestBase`
8. After the classes development, run tests by `$ vendor/bin/phing`

You can reference to integration/FacebookWordpressContactForm7.php and tests/FacebookWordpressContactForm7Test.php as an example


## Contributor License Agreement ("CLA")
In order to accept your pull request, we need you to submit a CLA. You only need
to do this once to work on any of Facebook's open source projects.

Complete your CLA here: <https://code.facebook.com/cla>

## Issues
We use GitHub issues to track public bugs. Please ensure your description is
clear and has sufficient instructions to be able to reproduce the issue.

Facebook has a [bounty program](https://www.facebook.com/whitehat/) for the safe
disclosure of security bugs. In those cases, please go through the process
outlined on that page and do not file a public issue.

## Coding Style  
* 4 spaces for indentation rather than tabs
* 80 character line length

## License
By contributing to Facebook for Wordpress, you agree that your contributions
will be licensed under the LICENSE file in the root directory of
this source tree.

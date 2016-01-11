# EDD - Sucks to be EU #

**Donate:** https://store.halfelf.org/donate/

**License:** GPLv2 or later  

**License URI:** http://www.gnu.org/licenses/gpl-2.0.html

Prevents customer from being able to checkout if they're from the EU.

[Download on WordPress.org](https://wordpress.org/plugins/edd-prevent-eu-checkout/)

## Description ##

This plugin is an attempt to comply with the 2015 changes to VAT and the EU, this plugin prevents a customer from being able to checkout if they're from the EU.

The official home of the plugin is on WordPress.org as [EDD Prevent EU Checkout](https://wordpress.org/plugins/edd-prevent-eu-checkout/) but all dev work is done here. Pull requests and issues welcome!

## Changelog ##

= 1.2 =
* Improved i18n
* Solving [Github issue #2](https://github.com/Ipstenu/edd-prevent-eu-checkout/issues/2) - Billing address is now checked for EU-ness.
* Clean up functions
* Documentation

= 1.1.2 =
* Solving [GitHub Issue #13](https://github.com/Ipstenu/edd-prevent-eu-checkout/issues/13) where payment gateways showed without needing to.

= 1.1.1 =
* Correct error where, in some cases, it couldn't tell EDD was active.

= 1.1 =
* Better error if EDD isn't around.
* Move checkout fields to `edd_purchase_form_user_info_fields`

= 1.0.8 =
* Speed up hostip checks with timeouts and surpress errors (props @maurisrx)

= 1.0.7 =
* Hide payment selection too, if you're in the EU (props @tctc91)

= 1.0.6 =
* Removed Mediawiki - Slow and weird results in the long term.

= 1.0.5 =
* Adding in another failsafe switch via MediaWiki
* Allowing people to manually install GeoIP DBs
* If country cannot be determined, kill Buy Now.

= 1.0.4 =
* Removing South Africa ([Per KPMG](http://www.kpmg.com/global/en/issuesandinsights/articlespublications/vat-gst-essentials/pages/south-africa.aspx) the threshold is R50,000)
* Filtering purchase buttons (nice catch @StephenCronin)

= 1.0.3 =
* Small date check improvement on checkout.

= 1.0.2 =
* Dates were wrong. You're supposed to CHECK if it's before or after Jan 1, 2015... TARDIS.

= 1.0.1 =
* '/" mixup (thanks @macmanx)
* Better handling of failures.

= 1.0 =
* Initial release
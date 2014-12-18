# EDD - Sucks to be EU #

**Donate:** https://store.halfelf.org/donate/

**License:** GPLv2 or later  

**License URI:** http://www.gnu.org/licenses/gpl-2.0.html
  
Prevents customer from being able to checkout if they're from the EU.

## Description ##

This plugin requires [Easy Digital Downloads](http://wordpress.org/extend/plugins/easy-digital-downloads/ "Easy Digital Downloads"). 

In an attempt to comply with the 2015 changes to VAT and the EU, this plugin prevents a customer from being able to checkout if they're from the EU. It does this by checking that the IP is not in an EU country based on data from one of two places:

1. GeoIP, if it's installed for PHP: http://php.net/manual/en/book.geoip.php
2. Otherwise, it uses HostIP.Info: http://www.hostip.info

In addition, it adds a *required* checkbox that has the customer confirm they're not from the EU.

* Credit to [Michelle](http://thegiddyknitter.com/2014/11/19/wip-wednesday-solutions-digital-businesses-eu-vat) for the idea
* Forked from [EDD Prevent Checkout](http://sumobi.com/shop/edd-prevent-checkout/) by Sumobi

## Changelog ##

~Current Version:1.0.2~

### 1.0.2 ###
* Dates were wrong. You're supposed to CHECK if it's before or after Jan 1, 2015... TARDIS.


### 1.0.1 ###
* '/" mixup (thanks @macmanx)
* Better handling of failures.

### 1.0 ###
* Initial release
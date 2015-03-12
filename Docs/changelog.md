# Changelog #

*These logs may be outdated or incomplete.*

## 1.6.10 ##

* Includes changes from 1.6.2 - 1.6.9
* Added `forceRefresh` option for `CacheableBehavior::cache()`
* Added WordPress RSS feed image support to `FeedSource`
* Added `SluggableBehavior::slugExists` method [[#22](https://github.com/milesj/utility/issues/22)]
* Fixed a bug where `AutoLoginComponent` was throwing a 302 instead of a 301
* Fixed a bug in `BaseInstallShell` where the driver prefix was not being used
* Fixed bugs in `CacheableBehavior` where empty results would be cached incorrectly
* Increased the uniqueness of `SluggableBehavior` slug generation [[#21](https://github.com/milesj/utility/issues/21)]
* Improved the efficiency of `CacheableBehavior` all around

## 1.6.1 ##

* Updated routes for sitemap because of recent CakePHP changes [[#17](https://github.com/milesj/utility/issues/17)]

## 1.6.0 ##

* Updated for interface changes in CakePHP 2.4 [[#15](https://github.com/milesj/utility/issues/15)]
* Added `Sanitize` to `FilterableBehavior` to fix missing class errors [[#16](https://github.com/milesj/utility/issues/16)]

## 1.5.5 ##

* Added a `cacheForceRefresh` option to query options that will force refresh the cache for a specific key

## 1.5.4 ##

* Pass extra arguments to `CacheableBehavior::cache()` [[#12](https://github.com/milesj/utility/issues/12)]

## 1.5.3 ##

* Includes changes from 1.5.2
* Added new IP and email checking rules to `SpamBlockerBehavior`
* Added email template support to `SpamBlockerBehavior` when `sendEmail` is a string (name of the template)

## 1.5.1 ##

* Added a `storeEmpty` setting to `CacheableBehavior` to allow caching of empty results [[#10](https://github.com/milesj/Utility/issues/10)]
* Added `EnumerableBehavior.validateEnum()` to allow for enum validation in the model layer [[#11](https://github.com/milesj/Utility/issues/11)]

## 1.5.0 ##

* Includes changes from previous minor versions
* Removed `Decoda` and `Titon\Utility` as direct dependencies
* Updated `Decoda.js` to v1.3.0
* Updated `CacheKill` with multiple commands and APC clearing support
* Updated shell colors to be easier to read

## 1.4.3 ##

* Updated `Decoda` [code] template

## 1.4.2 ##

* Added ordering to `CacheableBehavior` get methods
* Updated `SpamBlocker` to not trigger on record updates

## 1.4.1 ##

* Added `Controller::constructClasses()` to sitemap generation
* Added `UtilityHelper::gravatar()`

## 1.4.0 ##

* Added a BaseInstallShell and BaseUpgradeShell to handle plugin installs/upgrades
* Added the SluggableBehavior instance as a second argument to the beforeSlug() and afterSlug() callbacks
* Added a unique option to SluggableBehavior
* Added append() and prepend() to BreadcrumbHelper
* Added getCount as primary caching method in CacheableBehavior
* Added default rule messaging fallbacks to ValidateableBehavior
* Added getAll(), getList(), getCount(), getById() and getBySlug() to CacheableBehavior which can be called from the Model layer
* Added Decoda configuration to Configure via plugin bootstrap
* Added UtilityHelper to handle all purpose view functionality
* Fixed a bug with SluggableBehavior wildcard behaving incorrectly
* Fixed a bug where HTML is not stripped from breadcrumbs
* Updated AutoLoginComponent to use the referrer as the auth login redirect
* Updated SluggableBehavior to not sluggify a record if the slug is manually set in the data
* Updated ValidateableBehavior to grab $validate and use as the default validation set
* Updated EnumerableBehavior format setting to be APPEND by default instead of REPLACE
* Updated Decoda to v6.0.0

## 1.3.3 ##

* Added DecodaHelper::getDecoda() to return the raw instance
* Changed DecodaHelper::parse() 3rd argument to toggle the wrapping div
* Fixed the Decoda JS editor not displaying icons

## 1.3.2 ##

* Added multibyte check to composer
* Added ` to Decoda code blocks

## 1.3.1 ##

* Pass Model as argument to CacheableBehavior::cache() Closure
* Updated to use Multibyte

## 1.3.0 ##

* Moved cake-decoda files to cake-utility
* Updated to Decoda v5.0.0
* Added filters and hooks settings for DecodaHelper
* Removed TypeConverter for Titon\Utility\Converter
* Fixed feed default date sorting
* Updated to allow no limits on feeds
* Updated to use SPL exceptions

## 1.2.2 ##

* Added $ns to OpenGraphHelper::has()
* Changed isset() to array_key_exists() in SitemapController

## 1.2.1 ##

* Fixed sitemap lastmod bugs
* Use getById/Slug if it exists during CacheableBehavior::afterSave()

## 1.2.0 ##

* PHP 5.4 fixes
* Fixed OpenGraph::locale() bug
* Don't trigger AutoLogin on error pages
* Added Sitemap generation support

## 1.1.1 ##

* Changed url() to uri() because of 5.4 errors
* Updated locale() to convert locales
* Added has() to check to see if a tag is set

## 1.1.0 ##

* Added the OpenGraphHelper
* Updated BreadcrumbHelper to use the OpenGraphHelper

## 1.0.0 ##

* Initial release of Utility

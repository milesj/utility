# Utility v1.6.2 #

A collection of CakePHP utility components, behaviors, datasources, models, helpers and more all packaged into a single plugin.

## Requirements ##

* PHP 5.3.0
    * Multibyte
* CakePHP 2
* Composer

## Dependencies ##

* `Decoda` - https://github.com/milesj/decoda (DecodaHelper)
* `Titon\Utility` - https://github.com/titon/utility (AjaxHandlerComponent, FeedSource)

## Libraries ##

**Components**
* `AjaxHandler` - Provides support for AJAX request and response
* `AutoLogin` - An auth auto-login and persistent remember me

**Controllers**
* `Sitemap` - Generates a sitemap.xml for search engines

**Models**
* `Aggregator` - Convenience model that uses the FeedSource

**Behaviors**
* `Cacheable` - Automatic database query caching
* `Convertable` - Converts between types before and after database queries
* `Enumerable` - Provides enumerable support for database columns
* `Filterable` - Apply automatic filters and escaping to fields
* `Sluggable` - Generate a slug based off another field
* `SpamBlocker` - Validates comments against a point system and flags as spam
* `Validateable` - Allows for multiple sets of validation rules as well as message translation

**Datasources**
* `Feed` - RSS, RDF, Atom and XML parser through the model layer

**Helpers**
* `Breadcrumb` - Basic breadcrumb and sitemap generation
* `OpenGraph` - Generate meta tags for the OpenGraph protocol
* `Decoda` - BBcode markup parsing with the Decoda library
* `Utility` - Provides utility helper methods that integrate with the plugins other classes

**Shells**
* `CacheKill` - Clear cache from the command line
* `BaseInstall` - An abstract shell that provides installation functionality for plugins
* `BaseUpgrade` - An abstract shell that provides version upgrade migration functionality for plugins

## Documentation ##

Thorough documentation can be found here: http://milesj.me/code/cakephp/utility

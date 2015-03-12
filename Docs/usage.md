# Utility #

*Documentation may be outdated or incomplete as some URLs may no longer exist.*

*Warning! This codebase is deprecated and will no longer receive support; excluding critical issues.*

A collection of CakePHP utility components, behaviors, datasources, models, helpers and more all packaged into a single plugin.

* **AjaxHandler** - Provides support for AJAX request and response
* **AutoLogin** - An auth auto-login and persistent remember me
* **Sitemap** - Generates a sitemap.xml for search engines
* **Aggregator** - Convenience model that uses the FeedSource
* **Cacheable** - Automatic database query caching
* **Convertable** - Converts between types before and after database queries
* **Enumerable** - Provides enumerable support for database columns
* **Filterable** - Apply automatic filters and escaping to fields
* **Sluggable** - Generate a slug based off another field
* **SpamBlocker** - Validates comments against a point system and flags as spam
* **Validateable** - Allows for multiple sets of validation rules
* **Feed** - RSS, RDF, Atom and XML parser through the model layer
* **Breadcrumb** - Basic breadcrumb and sitemap generation
* **OpenGraph** - Generate meta tags for the OpenGraph protocol
* **Decoda** - BBcode markup parsing with the Decoda library
* **CacheKill** - Clear cache from the command line

## Installation ##

The plugin must use [Composer](http://getcomposer.org/) for installation so that all dependencies are also installed. [Learn more about using Composer in CakePHP](http://milesj.me/blog/read/using-composer-in-cakephp). The plugin uses [Decoda](https://github.com/milesj/Decoda) and [Titon](https://github.com/titon/Utility) internally.

```javascript
{
    "config": {
        "vendor-dir": "Vendor"
    },
    "require": {
        "mjohnson/utility": "1.*",
        "mjohnson/decoda": "*",
        "titon/utility": "*"
    }
}
```

 Include the Decoda dependency if you are using the DecodaHelper. Include the Titon\Utility dependency if you are using the FeedSource or AjaxHandlerComponent.

Be sure to enable Composer at the top of `Config/core.php`.

```php
require_once dirname(__DIR__) . '/Vendor/autoload.php';
```

And to load the plugin.

```php
CakePlugin::load('Utility', array('bootstrap' => true, 'routes' => true));
```

## Query Caching ##

Caching the results of database queries can turn into quite a lengthy and complex implementation. The `CacheableBehavior` provides automatic caching out of the box. When `Model::find()` is called and the cache parameter is passed, the data will be written. When `Model::create()` or `Model::update()` is called, the cache key with the associated `Model::$id` will be written. When `Model::delete()` is called, the cache key with the associated `Model::$id` will be deleted. Also supports the ability to batch reset/delete cached items by providing a mapping of method and argument hooks.

### Usage ###

Enable the behavior in the respective model.

```php
public $actsAs = array('Utility.Cacheable');
```

To cache the results of a query, pass a `cache` option to `Model::find()`. This option will be the name of the cache key, so name it wisely. One can pass an array of values to also generate a cache key.

```php
public function getById($id) {
    return $this->find('first', array(
        'conditions' => array('id' => $id),
        'cache' => array(__METHOD__, $id)
    ));
}
```

To change the duration of the cache on a per item basis, pass a `cacheExpires` option.

```php
public function getAll() {
    return $this->find('all', array(
        'cache' => __METHOD__,
        'cacheExpires' => '+24 hours'
    ));
}
```

Or to force the refresh of an existent cache, use the `cacheForceRefresh` option.

```php
public function getAll() {
    return $this->find('all', array(
        'cache' => __METHOD__,
        'cacheForceRefresh' => true
    ));
}
```

Many convenience methods are now available at the model layer. The behavior uses the same methods internally.

```php
$this->Model->readCache($key);
$this->Model->writeCache($key, $value, $expires);
$this->Model->deleteCache($key);
$this->Model->clearCache();
```

Furthermore, `resetCache($id)` will delete all cache based on an ID, as well as every cache key within the `resetHooks` option.

### Settings ###

The following filters are supported. They can be set through the behavior initialization. 

* `cacheConfig` (string:sql) - Cache configuration to use
* `dbConfig` (string:shim) - Database configuration to use in the model
* `expires` (string:+5 minutes) - Default expiration time for all cache items
* `prefix` (string) - String to prepend to all cache keys
* `appendKey` (string:Cacheable) - Should we append the cache key and expires to the results
* `storeEmpty` (bool:false) - Allow empty results to be cached
* `methodKeys` (array) - Mapping of primary cache keys to methods
* `events` (array) - Toggle cache reset events for specific conditions
* `resetHooks` (array) - Mapping of cache keys and arguments to reset with

### Automatic Caching ###

Automatic caching refers to the process of caching data immediately after an insert or update, or deleting cache when a record is deleted. These processes are referred to as events, and can easily be toggled through the `events` settings. The following event defaults are below.

```php
public $actsAs = array(
    'Utility.Cacheable' => array(
        'events' => array(
            'onCreate' => false,
            'onUpdate' => true,
            'onDelete' => true
        )
    )
);
```

### Primary Methods ###

Every model has methods that are used the most. They usually consist of getting all records, getting a list of records, or getting a record based on ID. For automatic caching (mentioned above), specific method names should be used; the defaults are getAll, getList, getCount, getById and getBySlug. These names can be changed through the `methodKeys` setting.

```php
public $actsAs = array(
    'Utility.Cacheable' => array(
        'methodKeys' => array(
            'getAll' => 'all',
            'getList' => 'list',
            'getCount' => 'count',
            'getById' => 'byId',
            'getBySlug' => 'bySlug'
        )
    )
);
```

And then triggered when that method is called and the cache option is defined.

```php
$this->Model->byId($id);
```

### Advanced Caching ###

There are scenarios where caching the results of a query is not desirable. Perhaps the results need to be filtered or modified before being cached? This can be achieved by using a Closure callback and the `cache($key, $callback, $expires)` method.

```php
public function someComplexMethod($id) {
    return $this->cache(array(__METHOD__, $id), function($model) {
        $results = $model->find('all', array());
        
        if ($results) {
            foreach ($results as $result) {
                // Modify result
            }
        }
        
        return $results;
    }, '+24 hours');
}
```

## Data Conversion ##

Since databases do not support arrays or objects, one must convert the value before and after each query. This can easily be achieved with the `ConvertableBehavior`. The behavior will convert a value to a specific format before an insert query, and convert it back after a select query.

### Usage ###

Enable the behavior and define the conversions. Every index in the array should relate to a database field.

```php
public $actsAs = array(
    'Utility.Convertable' => array(
        'fieldOne' => 'base64',
        'fieldTwo' => 'serialize'
    )
);
```

With this in place, all fields will have the conversions triggered before `Model::save()` and after `Model::find()`.

### Settings ###

The following conversions are supported. They can be set through the behavior initialization. 

* `serialize` - Serialize arrays
* `json` - JSON serialize arrays (optional settings: object)
* `html` - Convert HTML entities (optional settings: decode, encoding, flags)
* `base64` - Encode strings in base64
* `utf8` - Encode strings with UTF-8
* `url` - Encode URLs
* `rawurl` - Encode raw URLs

Some conversions have optional sub-settings. These can be set by defining an array of settings for each field.

```php
public $actsAs = array(
    'Utility.Convertable' => array(
        'fieldOne' => array(
            'engine' => 'json',
            'object' => true // Return as object
        )
        'fieldTwo' => array(
            'engine' => 'html',
            'decode' => true,
            'flags' => ENT_QUOTES
        )
    )
);
```

## Data Filtering ##

There are times where you want to filter data before it reaches the database. The `FilterableBehavior` provides pre-query data sanitization and works in a similar fashion to the `ConvertableBehavior`. The behavior will filter (or sanitize) a value before a `Model::save()`.

### Usage ###

Enable the behavior and define the filters. Every index in the array should relate to a database field.

```php
public $actsAs = array(
    'Utility.Filterable' => array(
        'fieldOne' => array(
            'html' => true,
            'paranoid' => true
        ),
        'fieldTwo' => array(
            'strip' => true
        )
    )
);
```

With this in place, all fields will have specific sanitization methods triggered before `Model::save()`.

### Settings ###

The following filters are supported. They can be set through the behavior initialization. 

* `html` - Escapes HTML and entities (optional settings: double, encoding, flags)
* `strip` - Removes HTML tags (optional settings: allowed)
* `paranoid` - Removes any non-alphanumeric characters (optional settings: allowed)
* `escape` - Escapes SQL queries

Some filters have optional sub-settings. These can be set by defining an array of settings for each field.

```php
public $actsAs = array(
    'Utility.Filterable' => array(
        'fieldOne' => array(
            'paranoid' => array(
                'allowed' => array('.', '-', '_') // allow those characters
            ),
            'strip' => array(
                'allowed' => '<a><b>'
            )
        )
        'fieldTwo' => array(
            'html' => array(
                'encoding' => 'UTF-8',
                'flags' => ENT_QUOTES
            )
        )
    )
);
```

## Enumerable Fields ##

Ever want to use an enumerable set of values for a database field? With the `EnumerableBehavior`, this can be achieved quite easily. The behavior is an alternative to MySQL enum fields (and other engines) as well as a more advanced implementation of class constants. Furthermore, every database field in a query result that has an enum defined will be replaced with a string representation of the enum for easier readability. 

### Usage ###

Begin by enabling the behavior in the `AppModel` as this behavior does not have model specific configuration.

```php
class AppModel extends Model {
    public $actsAs = array('Utility.Enumerable');
}
```

The next step is to define a mapping of key value pairs for each enumerable field.

```php
class User extends AppModel {
    const PENDING = 0;
    const ACTIVE = 1;
    const INACTIVE = 2;
    
    public $enum = array(
        'status' => array(
            self::PENDING => 'PENDING',
            self::ACTIVE => 'ACTIVE',
            self::INACTIVE => 'INACTIVE'
        )
    );
}
```

And for consistency and convenience purposes, the constants should be used during find and save operations.

```php
$this->User->find('all', array(
    'conditions' => array('status' => User::ACTIVE)
));
```

Now in every query result array, the mapped fields will have their values replaced with their enum equivalent; for example, 0 will become PENDING, 1 will be ACTIVE, so on and so forth. 

```php
array(
    'id' => 1337,
    'username' => 'gearvOsh',
    'status' => 'ACTIVE',
    'status_enum' => 1 // original value
);
```

This is disabled during a model update (unless `onUpdate` is true) so that the true values aren't conflicted. You can use a mixture of the format, persist and suffix settings to mess with how enum replacement works.

Lastly, you can retrieve the enum mappings at any time by using `enum()`. This is useful for outputting values in the view.

```php
echo $this->User->enum('status');
echo $this->User->enum(); // all mappings
```

### Settings ###

The following settings are supported. They can be set through the behavior initialization.

* `persist` (boolean:true) - Persist the original value alongside the enum value
* `format` (string:replace) - How to format the enum value in the array (accepts replace, append and boolean false to disable)
* `onUpdate` (boolean:false) - Enable enum value replacement for model updates
* `suffix` (string:_enum) - Suffix to append to the enum key in the array 

### Reversing Values ###

By default the behavior will replace fields with the enum equivalent. If this is not desired functionality, one may change the format setting to "append" instead of "replace". This will reverse the formatting.

```php
array(
    'id' => 1337,
    'username' => 'gearvOsh',
    'status' => 1,
    'status_enum' => 'ACTIVE' // enum value
)
```

## Slug Generation ##

Prefer using slugs in URLs instead of IDs? The `SluggableBehavior` solves this problem! The behavior automatically creates a slug based version of a field (usually a title or name) before a record is created or updated.

### Usage ###

Enable the behavior in models that will make use of slugs. By default, all title fields will have slugs generated in the slug field.

```php
public $actsAs = array('Utility.Sluggable');
```

Simply call `Model::save()` to trigger the slugging process.

### Settings ###

The following filters are supported. They can be set through the behavior initialization. 

* `field` (string:title) - The column to base the slug on
* `slug` (string:slug) - The column to write the slug to
* `separator` (string:-) - The separating character between words
* `scope` (array) - Additional query conditions when finding duplicates
* `length` (int:255) - The max length of a slug
* `onUpdate` (boolean:true) - Will update the slug when a record is updated
* `unique` (boolean:true) - Whether to make the slug unique or not

### Callbacks ###

There are times when one would like to modify the slug before or after the converting process. This can be achieved by creating callbacks in the model, `beforeSlug` and `afterSlug`. The current Sluggable instance is also passed as the second argument.

```php
public function beforeSlug($slug, ModelBehavior $behavior) {
    return $slug . date('Y-m-d');
}
```

## Spam Protection ##

No one likes comment spam, so why not have an automated system to filter them out? That's exactly what the `SpamBlockerBehavior` does. Each comment is tested upon a point system to determine and classify it. If a comment has more then 1 point it is automatically approved, if it has 0 points it continues pending, and if it is in the negative point range, it is either marked as spam or deleted entirely.

### Usage ###

The behavior is meant to work with a comments system, but could easily be adapted for other needs. The following database structure is recommended.

```sql
CREATE TABLE `comments` (
    `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `article_id` INT NOT NULL,
    `status` SMALLINT NOT NULL DEFAULT 0,
    `points` INT NOT NULL,
    `name` VARCHAR(50) NOT NULL,
    `email` VARCHAR(50) NOT NULL,
    `website` VARCHAR(50) NOT NULL,
    `content` TEXT NOT NULL,
    `created` DATETIME NULL DEFAULT NULL,
    `modified` DATETIME NULL DEFAULT NULL,
    INDEX (`entry_id`)
) ENGINE = InnoDB CHARACTER SET utf8 COLLATE utf8_general_ci;
```

To block spam, just enable the behavior on the respective model.

```php
class Comment extends AppModel {
    public $actsAs = array('Utility.SpamBlocker');
}
```

### Settings ###

The following settings are supported. They can be set through the behavior initialization.

* `model` (string:article) - The model comments are attached to
* `link` (string) - Absolute URL to the article, {id} and {slug} will be replaced
* `useSlug` (boolean:false) - Use slugs over IDs in the link
* `savePoints` (boolean:true) - Save the point score in the database
* `sendEmail` (boolean:true) - Send an email for every comment
* `deletion` (int:-10) - How many negative points a comment must receive before being deleted
* `blockMessage` (string) - The message sent to the client when their comment is denied
* `keywords` (array) - List of keywords to blacklist within the author and content
* `blacklist` (array) - List of characters to blacklist within the website and URLs
* `columnMap` (array) - Mapping of field names to database columns
* `statusMap` (array) - Mapping of status codes to database values

### Overrides ###

If you already have an existing comments table with different column names, you have the option of defining overrides. To do this, pass an array of column names to overwrite the defaults. The available mappings are: foreignKey, id, author, content, email, website, slug, title, status and points.

```php
public $actsAs = array(
    'Utility.SpamBlocker' => array(
        'columnMap' => array(
            'author' => 'username',
            'content' => 'comment',
            'foreignKey' => 'article_id'
        )
    )
);
```

The same type of mapping exists for the status field. The available statuses are: pending, approved, deleted and spam.

```php
public $actsAs = array(
    'Utility.SpamBlocker' => array(
        'statusMap' => array(
            'pending' => 'pending',
            'approved' => 'approved',
            'delete' => 'denied',
            'spam' => 'garbage'
        )
    )
);
```

## Enhanced Validations ##

Ever want to define multiple validation sets in the model, instead of being restricted to a single set? Well the `ValidateableBehavior` is here to help. The behavior allows for multiple validation sets to exist and the ability to toggle which set should be used for validation before each `Model::save()`. This allows for simple validations to be created, instead of the complex ones that come from having only one set.

### Usage ###

Enable the behavior within `AppModel` to provide support to all models.

```php
class AppModel extends Model {
    public $actsAs = array('Utility.Validateable');
}
```

And then define all your validation sets within `Model::$validations`. The name of each set should be the array key.

```php
public $validations = array(
    'setOne' => array(
        'password' => array(
            'rule' => array('minLength', '8'),
            'message' => 'Minimum 8 characters long'
        )
    ),
    'setTwo' => array(
        'email' => 'email',
        'required' => true
    )
);
```

And before each model save, define which set should be used.

```php
$this->Model->validate('setOne')->save($data, true);
```

### Settings ###

The following settings are supported. They can be set through the behavior initialization.

* `defaultSet` (string:default) - The default validation set to use if none defined
* `resetAfter` (boolean:true) - Should Model::$validate be reset after validation
* `useDefaults` (boolean:true) - Use default messages for every validation rule

### Using Default Error Messages ###

By default CakePHP does not provide error messages for different validation rules, instead it simply returns "This field cannot be left blank". When the `ValidateableBehavior` is attached with the `useDefaults` setting to true, all validation rules that do not have a message defined will be replaced with a context specific message. [The list of messages can be found here](https://github.com/milesj/Utility/blob/master/Model/Behavior/ValidateableBehavior.php#L51).

## Remote Feed Parsing ##

Tired of trying to parse all the RSS, Atom and RDF feed formats? With the `Aggregator` model and the `FeedSource` datasource, one may aggregate feeds with ease. The datasource will attempt to parse multiple feed formats, from multiple URLs, and order them accordingly.

### Usage ###

Enable the datasource within the database configuration.

```php
public $feed = array('datasource' => 'Utility.FeedSource');
```

Include the `Aggregator` model in your controller (or create your own model for feed parsing).

```php
public $uses = array('Utility.Aggregator');
```

Execute the `find()` method with an array of conditions that map to feed URLs.

```php
$results = $this->Aggregator->find('all', array(
    'conditions' => array(
        'CNN Top Stories' => 'http://rss.cnn.com/rss/cnn_topstories.rss',
        'IGN News' => 'http://feeds.ign.com/ign/news'
    )
));
```

The results will contain parsed arrays of every feed within conditions, including the title, description, date, author, etc. These results will be ordered in ascending order by date by default.

### Settings ###

Like regular `find()` operations, the limit, order and fields options are available. The limit option works like one would expect. The order option works in a similar fashion too, except the database field would be an element in the feed document.

```php
$results = $this->Aggregator->find('all', array(
    'conditions' => array(),
    'order' => array('title' => 'DESC'), // order by <title>
    'limit' => 25
));
```

The fields option is rather complex and powerful. It allows one to extract values from the feed that are not part of the defaults. The current default mappings are as such (below). The array index will be the key returned in the results, and the array values are where to extract that value from in the feed (the element names).

```php
$elements = array(
    'title' => array('title'),
    'guid' => array('guid', 'id'),
    'date' => array('date', 'pubDate', 'published', 'updated'),
    'link' => array('link', 'origLink'),
    'image' => array('image', 'thumbnail'),
    'author' => array('author', 'writer', 'editor', 'user'),
    'source' => array('source'),
    'description' => array('description', 'desc', 'summary', 'content', 'text')
);
```

Alongside the default find options, the feed has its own options. One can pass an array of feed options that contain the following.

* `cache` (boolean:false) - Toggle caching for the "feed" cache configuration
* `expires` (string:+1 hour) - Duration for cached items
* `root` (string) - Custom name for the element that contains all the item elements

```php
$results = $this->Aggregator->find('all', array(
    'conditions' => array(),
    'feed' => array(
        'cache' => true,
        'expires' => '+24 hours',
        'root' => 'items' // <items>
    )
));
```

## Open Graph Integration ##

Looking for Open Graph support? The `OpenGraphHelper` and `BreadcrumbHelper` can be used to achieve this. The `OpenGraphHelper` can be used to generate OG/FB specific meta tags, while the `BreadcrumbHelper` can be used to generate breadcrumb trails that tie into the `OpenGraphHelper`.

### Usage ###

Enable the helpers through the controller.

```php
public $helpers = array('Utility.OpenGraph', 'Utility.Breadcrumb');
```

Define the basic meta tags within the layout.

```php
<?php
echo $this->Html->docType();
echo $this->OpenGraph->html(); ?>
<head>
    <?php echo $this->Html->charset('UTF-8'); ?>
    <title><?php echo $this->Breadcrumb->pageTitle('Site Name'); ?></title>
    <?php
    $this->OpenGraph->name('Site Name');
    $this->OpenGraph->appId(1234567890);
    $this->OpenGraph->locale('en_US');
    echo $this->OpenGraph->fetch(); ?>
</head>
<body></body>
</html>
```

And define more tags within each specific view template.

```php
$this->OpenGraph->title($post['Post']['title']);
$this->OpenGraph->description($post['Post']['description']);
```

The `BreadcrumbHelper` can be used to automatically set the OG title and url.

```php
$this->Breadcrumb->add('Forums', array('action' => 'index'));
$this->Breadcrumb->add($topic['Topic']['title'], array('action' => 'topic', $topic['Topic']['id']));
```

### Methods ###

The `OpenGraphHelper` supports the following methods.

* `html($options, $ns)` - Create <html> tag including namespaces
* `appId($id)` - Create fb:appId tag
* `admins($id)` - Create fb:admins tag
* `name($value, $ns)` - Create og:site_name tag
* `title($value, $ns)` - Create og:title tag
* `type($value, $ns)` - Create og:type tag
* `uri($value, $ns)` - Create og:url tag
* `description($value, $ns)` - Create og:description tag
* `locale($value, $ns)` - Create og:locale tag
* `image($value, $options, $ns)` - Create og:image tag
* `video($value, $options, $ns)` - Create og:video tag
* `ns($key, $url)` - Add a custom namespace

### Namespaces ###

By default the OG and FB namespaces are registered. To add additional namespaces, one can use the `ns()` method (before `html()` is called) or supply an array of namespaces as the second argument to `html()`.

```php
$this->OpenGraph->ns('game', 'http://ogp.me/ns/game#');
$this->OpenGraph->html(array(), array(
    'game' => 'http://ogp.me/ns/game#'
));
```

The individual methods also accept the namespace key as the second argument (third argument for `image()` and `video()`).

```php
$this->OpenGraph->title('Starcraft 2', 'game');
```

### Media Attributes ###

The helper also provides support for multiple images or videos, as well as attributes like width and height. Simply pass an array of attributes as the second argument.

```php
$this->OpenGraph->image($path, array(
    'width' => 100,
    'height' => 100
));
```

Calling the same method multiple times will generate multiple meta tags.

## Decoda & BBCode Integration ##

Looking for BBCode support? The [Decoda](http://milesj.me/code/php/decoda) library is heavily integrated and can be used via the `DecodaHelper`. This helper will parse any string that contains BBCode style syntax and output an HTML formatted version. Be sure to read about Decoda and all its features.

### Usage ###

Enable the helper through the controller.

```php
public $helpers = array('Utility.Decoda');
```

And once in the view, you can use the helper to parse strings.

```php
echo $this->Decoda->parse($string);
```

You can also use `strip()` to remove BBcode tags instead of replacing them with HTML.

```php
echo $this->Decoda->strip($string);
```

### Settings ###

The following settings are supported. They can be set through the helper initialization.

* `open` (string:[) - The opening code bracket
* `close` (string:]) - The closing code bracket
* `locale` (string:eng) - The locale to use for messages
* `shorthandLinks` (boolean:false) - Use shorthand format for links
* `xhtmlOutput` (boolean:false) - Toggle between HTML and XHTML
* `escapeHtml` (boolean:true) - Escape HTML before parsing
* `strictMode` (boolean:true) - Require double quotes around attributes
* `maxNewlines` (int:3) - Max limit on new line characters
* `paths` (array) - List of paths for custom configurations
* `whitelist` (array) - List of tags to allow
* `blacklist` (array) - List of tags to deny
* `filters` (array) - List of filters to use (empty applies all)
* `hooks` (array) - List of hooks to use (empty applies all)

### Customization ###

If there is ever a need to interact with the `Decoda` object itself, you can create a method called `setupDecoda()` within the `AppHelper`. This method accepts the `Decoda` instance as its first argument. From here, you can customize as you wish.

```php
class AppHelper extends Helper {
    public function setupDecoda($decoda) {
        $decoda->addFilter(new CustomFilter());
    }
}
```

### Global Configuration ###

To set global defaults, use the `Configure` class. Be sure to merge with the plugin defaults or settings will be lost.

```php
Configure::write('Decoda.config', Configure::read('Decoda.config') + array(
    'locale' => 'fr-fr', // default to french
    'escapeHtml' => true
));
```

## Sitemap Generation ##

Looking for an easy way to generate Google Webmaster sitemaps? The `SitemapController` will automatically support this. The controller will loop through every application controller, execute a method, and use those results as the basis for the sitemap.

### Usage ###

In every application controller that will display sitemap links, create a public method called `_generateSitemap()`. This method will return an array of links that will be rendered in the XML. If no such method exists in a controller or the links are empty, the controller will be skipped.

```php
class ForumController extends AppController {
    public $uses = array('Forum');
    
    public function _generateSitemap() {
        $sitemap = array(
            array(
                'loc' => array('controller' => 'forums', 'action' => 'index'),
                'changefreq' => 'hourly',
                'priority' => '0.5'
            )
        );

        if ($results = $this->Forum->getActiveForums()) {
            foreach ($results as $result) {
                $sitemap[] = array(
                    'loc' => array('controller' => 'forums', 'action' => 'category', $result['Forum']['id']),
                    'lastmod' => $result['Forum']['modified'],
                    'changefreq' => 'daily',
                    'priority' => '0.9'
                );
            }
        }

        return $sitemap;
    }
}
```

Going to domain.com/sitemap.xml in your browser will then render the appropriate sitemap. The sitemap can also be accessed as JSON by visiting sitemap.json instead of sitemap.xml.

## Persistent Login ##

Like most web applications, a user may want to stay persistently logged in; this can be achieved by using the `AutoLoginComponent`. The component will trigger on every page load and attempt to login the user if a specific cookie exists. If the user is already logged in, the process will exit early. The cookie will automatically be set when a successful login occurs and a marked checkbox exists. 

### Usage ###

Simply initialize the component alongside the `AuthComponent`.

```php
class AppController extends Controller {
    public $components = array(
        'Auth', 
        'Utility.AutoLogin' => array(
            'cookieName' => 'rememberMe',
            'expires' => '+4 weeks'
        )
    );
}
``` 

And create a checkbox within the login form (or disable requirePrompt).

```php
echo $this->Form->input('auto_login', array('type' => 'checkbox', 'label' => 'Remember me?'));
```

### Settings ###

The following settings are supported. They can be set through the component initialization or controller `beforeFilter()`.

* `model` (string:User) - The users model
* `username` (string:username) - The database username field
* `password` (string:password) - The database password field
* `plugin` (string) - Plugin name if component is used within one
* `controller` (string:users) - The users controller
* `loginAction` (string:login) - The login action within the controller
* `logoutAction` (string:logout) - The logout action within the controller
* `cookieName` (string:autoLogin) - The name of the cookie
* `expires` (string:+2 weeks) - The length of the cookie
* `redirect` (boolean:true) - Force a redirect after a successful login
* `requirePrompt` (boolean:true) - Require a checkbox in the login form

### Callbacks ###

If you need to do additional logging and updating that is not initially in the default (for example updating a users last login time), you can place this extra code in a method called `_autoLogin()` within your `AppController`. If login fails, you can do some error logging and reporting by creating a method called `_autoLoginError()`. An array of user data will be passed as the 1st argument to the callbacks.

### Debugging ###

It can be quite difficult to debug this component if something is not working, simply because this component is automatically ran on each page load and during the login process. You can enable an internal debug system that will email you cookie and user information at specific events so that you may figure out which step the script is dying on. The debug settings take an email (where the debug will be sent) and an array of IPs (emails will only be sent if you are browsing with these IPs). If an email is not set, the output will be sent to the logs.

```php
Configure::write('AutoLogin', array(
    'email' => 'email@domain.com',
    'ips' => array('127.0.0.1')
));
```

Additionally, you can restrict the scope in which the debug emails are delivered. The following scopes are available: login, loginFail, loginCallback, logout, logoutCallback, cookieSet, cookieFail, hashFail, custom (for you to trigger manually).

```php
Configure::write('AutoLogin.scope', array('login', 'logout'));
```

## Breadcrumbs ##

The `BreadcrumbHelper` provides easy low-level support for page content breadcrumb trails. The helper works in a similar fashion to CakePHP's default breadcrumbs, with the following differences. No HTML is generated from the helper; this allows for easier customization. The helper also ties into the `OpenGraphHelper`, allowing titles and URLs to be set more easily.

### Usage ###

Enable the helper through the controller.

```php
public $helpers = array('Utility.Breadcrumb');
```

Define crumbs within the views.

```php
$this->Breadcrumb->add('Forums', array('action' => 'index'));
$this->Breadcrumb->add($topic['Topic']['title'], array('action' => 'topic', $topic['Topic']['id']));
```

And finally output the breadcrumb trail.

```php
<nav class="breadcrumbs">
    <ol>
        <?php foreach ($this->Breadcrumb->get() as $crumb) { ?>
            <li>
                <?php echo $this->Html->link($crumb['title'], $crumb['url'], $crumb['options'] + array('class' => 'crumb')); ?>
            </li>
        <?php } ?>
    </ol>
</nav>
```

### Methods ###

The `BreadcrumbHelper` supports the following methods.

* `add($title, $url, $options)` - Add a crumb (alias for append())
* `append($title, $url, $options)` - Append a crumb
* `prepend($title, $url, $options)` - Prepend a crumb
* `get($key)` - Return all crumbs
* `first($key)` - Return the first crumb
* `last($key)` - Return the last crumb
* `pageTitle($base, $options)` - Return the crumbs as a string

The `$key` parameter for `get()`, `first()` and `last()` will return the value of key in the array, instead of the whole array.

### Generating Page Titles ###

The `pageTitle()` method is useful for generating strings that output the order of the breadcrumb titles. It supports the following options.

* `reverse` (boolean:false) - Reverse the order
* `depth` (int:3) - How many crumbs to display (always displays first crumb)
* `separator` (string: - ) - The separator between crumbs

For example, lets create the following breadcrumbs.

```php
$this->Breadcrumb->add('Game', array('action' => 'index'));
$this->Breadcrumb->add('Terran', array('action' => 'race', 'terran'));
$this->Breadcrumb->add('Units', array('action' => 'units', 'terran'));
$this->Breadcrumb->add('Ghost', array('action' => 'unit', 'ghost'));
```

And then use `pageTitle()` to output the order.

```php
// Game - Units - Ghost
echo $this->Breadcrumb->pageTitle();

// Starcraft 2 - Game - Units - Ghost
echo $this->Breadcrumb->pageTitle('Starcraft 2');

// Starcraft 2 - Game - Terran - Units - Ghost
echo $this->Breadcrumb->pageTitle('Starcraft 2', array(
    'depth' => 4
));

// Starcraft 2 - Ghost - Units - Terran - Game
echo $this->Breadcrumb->pageTitle('Starcraft 2', array(
    'depth' => 4,
    'reverse' => true
));

// Starcraft 2 > Ghost > Units > Terran > Game
echo $this->Breadcrumb->pageTitle('Starcraft 2', array(
    'depth' => 4,
    'reverse' => true,
    'separator' => ' > '
));
```

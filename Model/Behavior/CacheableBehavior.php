<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');
App::uses('Folder', 'Utility');

/**
 * A CakePHP Behavior that will automatically read, write and delete cache for Model database queries.
 * When Model::find() is called and the cache parameter is passed, the data will be written.
 * When Model::create() or Model::update() is called, the cache key with the associated Model::$id will be written.
 * When Model::delete() is called, the cache key with the associated Model::$id will be deleted.
 * Also supports the ability to batch reset/delete cached items by providing a mapping of method and argument hooks.
 *
 * {{{
 *      class User extends AppModel {
 *          public $actsAs = array('Utility.Cacheable');
 *
 *          // Cache the result record
 *          public function getById($id) {
 *              return $this->find('first', array(
 *                  'conditions' => array('id' => $id),
 *                  'cache' => array(__METHOD__, $id)
 *              ));
 *          }
 *
 *          // Cache the result of all records
 *          public function getList() {
 *              return $this->find('all', array(
 *                  'cache' => __METHOD__,
 *                  'cacheExpires' => '+1 hour'
 *              ));
 *          }
 *      }
 * }}}
 */
class CacheableBehavior extends ModelBehavior {

    /**
     * Default settings.
     *
     * @type array {
     *      @type string $cacheConfig   Cache configuration to use
     *      @type string $dbConfig      Database configuration to use in the model
     *      @type string $expires       Default expiration time for all cache items
     *      @type string $prefix        String to prepend to all cache keys
     *      @type bool $appendKey       Should we append the cache key and expires to the results
     *      @type bool $storeEmpty      Will cache empty results
     *      @type array $methodKeys     Mapping of primary cache keys to methods
     *      @type array $events         Toggle cache reset events for specific conditions
     *      @type array $resetHooks     Mapping of cache keys and arguments to reset with
     * }
     */
    protected $_defaults = array(
        'cacheConfig' => 'sql',
        'dbConfig' => 'shim',
        'expires' => '+5 minutes',
        'prefix' => '',
        'appendKey' => false,
        'storeEmpty' => false,
        'methodKeys' => array(
            'getAll' => 'getAll',
            'getList' => 'getList',
            'getCount' => 'getCount',
            'getById' => 'getById',
            'getBySlug' => 'getBySlug'
        ),
        'events' => array(
            'onCreate' => false,
            'onUpdate' => true,
            'onDelete' => true
        ),
        'resetHooks' => array(
            'getAll' => true,
            'getList' => true,
            'getCount' => true,
            'getById' => array('id'),
            'getBySlug' => array('slug')
        )
    );

    /**
     * Is the current query attempting to cache.
     *
     * @type bool
     */
    protected $_isCaching = false;

    /**
     * The key and expires parameters for the current query.
     *
     * @type array
     */
    protected $_currentQuery = array();

    /**
     * The Model's DB config before caching begins.
     *
     * @type string
     */
    protected $_previousDbConfig;

    /**
     * Cached results for the current request.
     *
     * @type array
     */
    protected $_cached = array();

    /**
     * Merge settings.
     *
     * @param Model $model
     * @param array $settings
     */
    public function setup(Model $model, $settings = array()) {
        $settings = Hash::merge($this->_defaults, $settings);

        $this->settings[$model->alias] = $settings;

        // Set cache config if it doesn't exist
        if (Cache::config($settings['cacheConfig']) === false) {
            $cachePath = CACHE . $settings['cacheConfig'] . DS;

            if (!file_exists($cachePath)) {
                $folder = new Folder();
                $folder->create($cachePath, 0777);
            }

            Cache::config($settings['cacheConfig'], array(
                'engine'    => 'File',
                'serialize'    => true,
                'prefix'    => '',
                'path'        => $cachePath,
                'duration'    => $settings['expires']
            ));
        }
    }

    /**
     * When this behavior is unloaded, delete all associated cache.
     *
     * @param Model $model
     */
    public function cleanup(Model $model) {
        if ($model->id) {
            $this->resetCache($model, $model->id);
        }

        foreach ($this->_cached as $key => $value) {
            $this->deleteCache($model, $key);
        }
    }

    /**
     * Before a query is executed, look for the cache parameter.
     * If the cache param exists, generate a cache key and fetch the results from the cache.
     * If the result is empty or the cache doesn't exist, replace the current datasource
     * with a dummy shim datasource, allowing us to pull in cached results.
     *
     * @param Model $model
     * @param array $query
     * @return array|boolean
     */
    public function beforeFind(Model $model, $query) {
        if (Configure::read('Cache.disable') || !isset($query['cache'])) {
            $this->_isCaching = false;

            return true;
        }

        // Grab the cache key and expiration
        $key = $query['cache'];
        $expires = isset($query['cacheExpires']) ? $query['cacheExpires'] : null;
        $forceRefresh = isset($query['cacheForceRefresh']) ? $query['cacheForceRefresh'] : false;

        if ($key === true) {
            $key = array($model->alias, md5(json_encode($query)));

        } else if (is_array($key)) {
            if (isset($key['expires'])) {
                $expires = $key['expires'];
            }

            if (isset($key['key'])) {
                $key = $key['key'];
            }
        }

        $key = $this->cacheKey($model, $key, false);
        $expires = $this->getExpiration($model, $expires);

        $this->_isCaching = true;
        $this->_currentQuery = array(
            'key' => $key,
            'expires' => $expires,
            'forceRefresh' => $forceRefresh
        );

        // Are results already cached?
        if ($forceRefresh) {
            $results = false;
            $this->deleteCache($model, $key);
        } else {
            $results = $this->readCache($model, $key);
        }

        // Begin caching by replacing with ShimSource
        if ($results !== false) {
            $this->_previousDbConfig = $model->useDbConfig;

            // Create DataSource config if it doesn't exist
            $dbConfig = $this->settings[$model->alias]['dbConfig'];

            ConnectionManager::create($dbConfig, array(
                'datasource' => 'Utility.ShimSource',
                'database' => null
            ));

            $model->useDbConfig = $dbConfig;
        }

        return true;
    }

    /**
     * If caching was enabled in beforeFind(), return the cached results.
     * If the cache is empty, write a new cache with the results from the find().
     *
     * @param Model $model
     * @param mixed $results
     * @param bool $primary
     * @return mixed
     */
    public function afterFind(Model $model, $results, $primary = false) {
        if ($this->_isCaching) {
            $settings = $this->settings[$model->alias];
            $query = $this->_currentQuery;

            // Pull from cache if this was decided in beforeFind
            if ($model->useDbConfig === $this->settings[$model->alias]['dbConfig']) {
                $model->useDbConfig = $this->_previousDbConfig;

                $results = $this->_cached[$query['key']];

            // Write the new results if it has data
            } else if ($results) {
                if ($key = $settings['appendKey']) {
                    foreach ($results as &$result) {
                        $result[$key] = $query;
                    }
                }

                $this->writeCache($model, $query['key'], $results, $query['expires']);

            // Store empty result sets
            } else if ($settings['storeEmpty']) {
                $this->writeCache($model, $query['key'], $results, $query['expires']);

            }

            $this->_isCaching = false;
            $this->_currentQuery = null;
            $this->_previousDbConfig = null;
        }

        return $results;
    }

    /**
     * Once a record has been updated or created, cache the results if the specific events allow it.
     *
     * @param Model $model
     * @param bool $created
     * @param array $options
     * @return bool
     */
    public function afterSave(Model $model, $created, $options = array()) {
        $id = $model->id;
        $settings = $this->settings[$model->alias];
        $events = $settings['events'];

        // Use slug if that's the primary
        if ($model->primaryKey === 'slug') {
            $method = $settings['methodKeys']['getBySlug'];
        } else {
            $method = $settings['methodKeys']['getById'];
        }

        // Refresh the cache during update/create
        if ($id && $method && (($created && $events['onCreate']) || (!$created && $events['onUpdate']))) {
            $cacheKey = array($model->alias . '::' . $method, $id);

            if (method_exists($model, $method)) {
                $this->deleteCache($model, $cacheKey);
                call_user_func(array($model, $method), $id);

            } else {
                $this->writeCache($model, $cacheKey, array($model->read(null, $id)));
            }
        }

        if ($getList = $settings['methodKeys']['getList']) {
            $this->deleteCache($model, array($model->alias . '::' . $getList));
        }

        if ($getCount = $settings['methodKeys']['getCount']) {
            $this->deleteCache($model, array($model->alias . '::' . $getCount));
        }

        return true;
    }

    /**
     * Once a record has been deleted, remove the cached result.
     *
     * @param Model $model
     * @return bool
     */
    public function afterDelete(Model $model) {
        if ($this->settings[$model->alias]['events']['onDelete']) {
            $this->resetCache($model, $model->id);
        }

        return true;
    }

    /**
     * Cache data by using a Closure callback to generate the result set.
     * This method can be used within other methods in place the of the find() approach.
     *
     * @param Model $model
     * @param array|string $keys
     * @param Closure $callback
     * @param string $expires
     * @param array $options
     * @return mixed
     * @throws InvalidArgumentException
     */
    public function cache(Model $model, $keys, Closure $callback, $expires = null, $options = array()) {
        $options = $options + array('forceRefresh' => false);

        if (Configure::read('Cache.disable')) {
            return $callback($model, $keys, $expires);
        }

        $key = $this->cacheKey($model, $keys);
        $results = null;

        if (!$options['forceRefresh']) {
            $results = $this->readCache($model, $key);
        }

        if ($results === null || $results === false) {
            $results = $callback($model, $keys, $expires);

            $this->writeCache($model, $key, $results, $expires);
        }

        return $results;
    }

    /**
     * Generate a cache key. The first index should be the method name, the other indices should be unique values.
     *
     * @param Model $model
     * @param array|string $keys
     * @param bool $prefix
     * @return string
     */
    public function cacheKey(Model $model, $keys, $prefix = true) {

        // TranslateBehavior support
        if (!empty($model->locale)) {
            $keys = array_merge((array) $model->locale, (array) $keys);
        }

        if (is_array($keys)) {
            $key = array_shift($keys);

            if ($keys) {
                foreach ($keys as $value) {
                    if (is_array($value)) {
                        $key .= '-' . md5(json_encode($value));
                    } else if ($value) {
                        $key .= '-' . $value;
                    }
                }
            }
        } else {
            $key = (string) $keys;
        }

        if ($prefix) {
            $key = (string) $this->settings[$model->alias]['prefix'] . $key;
        }

        // Replace AppModel with the current Model so we don't run into conflicts
        $key = str_replace(array('AppModel', '::'), array($model->alias, '_'), $key);

        return $key;
    }

    /**
     * Convenience model method for returning all records.
     *
     * @param Model $model
     * @return array
     */
    public function getAll(Model $model) {
        return $model->find('all', array(
            'order' => array($model->alias . '.' . $model->displayField => 'ASC'),
            'cache' => $model->alias . '::getAll',
            'cacheExpires' => $this->getExpiration($model)
        ));
    }

    /**
     * Convenience model method for returning all records as a list.
     *
     * @param Model $model
     * @return array
     */
    public function getList(Model $model) {
        return $model->find('list', array(
            'order' => array($model->alias . '.' . $model->displayField => 'ASC'),
            'cache' => $model->alias . '::getList',
            'cacheExpires' => $this->getExpiration($model)
        ));
    }

    /**
     * Convenience model method for returning a count of all records.
     *
     * @param Model $model
     * @return array
     */
    public function getCount(Model $model) {
        return $model->find('count', array(
            'cache' => $model->alias . '::getCount',
            'cacheExpires' => $this->getExpiration($model)
        ));
    }

    /**
     * Convenience model method for returning a record by ID.
     *
     * @param Model $model
     * @param int $id
     * @return array
     */
    public function getById(Model $model, $id) {
        return $model->find('first', array(
            'conditions' => array($model->alias . '.' . $model->primaryKey => $id),
            'contain' => array_keys($model->belongsTo),
            'cache' => array($model->alias . '::getById', $id),
            'cacheExpires' => $this->getExpiration($model)
        ));
    }

    /**
     * Convenience model method for returning a record by slug.
     *
     * @param Model $model
     * @param string $slug
     * @return array
     */
    public function getBySlug(Model $model, $slug) {
        return $model->find('first', array(
            'conditions' => array($model->alias . '.slug' => $slug),
            'contain' => array_keys($model->belongsTo),
            'cache' => array($model->alias . '::getBySlug', $slug),
            'cacheExpires' => $this->getExpiration($model)
        ));
    }

    /**
     * Return the expiration time for cache. Either used the passed value, or the settings default.
     *
     * @param Model $model
     * @param mixed $expires
     * @return int|string
     */
    public function getExpiration(Model $model, $expires = null) {
        if (!$expires) {
            if ($time = $this->settings[$model->alias]['expires']) {
                $expires = $time;
            } else {
                $expires = '+5 minutes';
            }
        }

        return $expires;
    }

    /**
     * Read data from the cache.
     *
     * @param Model $model
     * @param array|string $keys
     * @return mixed
     */
    public function readCache(Model $model, $keys) {
        $key = $this->cacheKey($model, $keys);

        if (isset($this->_cached[$key])) {
            $results = $this->_cached[$key];
        } else {
            $this->_cached[$key] = $results = Cache::read($key, $this->settings[$model->alias]['cacheConfig']);
        }

        return $results;
    }

    /**
     * Write data to the cache. Be sure to parse the cache key and validate the config and expires.
     *
     * @param Model $model
     * @param array|string $keys
     * @param mixed $value
     * @param int|string $expires
     * @return bool
     */
    public function writeCache(Model $model, $keys, $value, $expires = null) {
        $key = $this->cacheKey($model, $keys);

        Cache::set('duration', $this->getExpiration($model, $expires), $this->settings[$model->alias]['cacheConfig']);

        $this->_cached[$key] = $value;

        return Cache::write($key, $value, $this->settings[$model->alias]['cacheConfig']);
    }

    /**
     * Delete a cached item based on the defined key(s).
     *
     * @param Model $model
     * @param array|string $keys
     * @return bool
     */
    public function deleteCache(Model $model, $keys) {
        $key = $this->cacheKey($model, $keys);

        unset($this->_cached[$key]);

        return Cache::delete($key, $this->settings[$model->alias]['cacheConfig']);
    }

    /**
     * Global function to reset specific cache keys within each model. By default, reset the getById and getList method keys.
     * If the ID passed is an array of IDs, run through each hook and reset those caches only if each field exists.
     *
     * @param Model $model
     * @param string|array $id
     * @return bool
     */
    public function resetCache(Model $model, $id = null) {
        $alias = $model->alias;

        if ($getList = $this->settings[$alias]['methodKeys']['getList']) {
            $this->deleteCache($model, array($alias . '::' . $getList));
        }

        if ($getCount = $this->settings[$alias]['methodKeys']['getCount']) {
            $this->deleteCache($model, array($alias . '::' . $getCount));
        }

        if (!$id) {
            return false;

        } else if (!is_array($id)) {
            $id = array('id' => $id);
        }

        $resetHooks = $this->settings[$alias]['resetHooks'];

        if ($resetHooks) {
            foreach ($resetHooks as $key => $args) {
                $continue = true;
                $keys = array($alias . '::' . $key);

                if (is_array($args)) {
                    foreach ($args as $field) {
                        if (isset($id[$field])) {
                            $keys[] = $id[$field];
                        } else {
                            $continue = false;
                            break;
                        }
                    }
                }

                if ($continue) {
                    $this->deleteCache($model, $keys);
                }
            }
        }

        return true;
    }

    /**
     * Clear all the currently cached items.
     *
     * @param Model $model
     * @return bool
     */
    public function clearCache(Model $model) {
        if ($this->_cached) {
            foreach ($this->_cached as $key => $value) {
                $this->deleteCache($model, $key);
            }
        }

        return Cache::clear(false, $this->settings[$model->alias]['cacheConfig']);
    }

}

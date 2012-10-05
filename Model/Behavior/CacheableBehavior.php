<?php
/**
 * CacheableBehavior
 *
 * A CakePHP Behavior that will automatically read, write and delete cache for Model database queries.
 * When Model::find() is called and the cache parameter is passed, the data will be written.
 * When Model::create() or Model::update() is called, the cache key with the associated Model::$id will be written.
 * When Model::delete() is called, the cache key with the associated Model::$id will be deleted.
 * Also supports the ability to batch reset/delete cached items by providing a mapping of method and argument hooks.
 *
 * {{{
 * 		class User extends AppModel {
 *			public $actsAs = array('Utility.Cacheable');
 *
 * 			// Cache the result record
 * 			public function getById($id) {
 *				return $this->find('first', array(
 *					'conditions' => array('id' => $id),
 *					'cache' => array(__METHOD__, $id)
 * 				));
 * 			}
 *
 * 			// Cache the result of all records
 * 			public function getList() {
 *				return $this->find('all', array(
 *					'cache' => __METHOD__,
 * 					'cacheExpires' => '+1 hour'
 * 				));
 * 			}
 * 		}
 * }}}
 *
 * @version		1.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');
App::uses('Folder', 'Utility');

class CacheableBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 * 	cacheConfig		- Cache configuration to use
	 * 	dbConfig		- Database configuration to use in the model
	 * 	expires			- Default expiration time for all cache items
	 * 	prefix			- String to prepend to all cache keys
	 * 	appendKey		- Should we append the cache key and expires to the results
	 * 	methodKeys		- Mapping of primary cache keys to methods
	 * 	events			- Toggle cache reset events for specific conditions
	 * 	resetHooks		- Mapping of cache keys and arguments to reset with
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaults = array(
		'cacheConfig' => 'sql',
		'dbConfig' => 'shim',
		'expires' => '+5 minutes',
		'prefix' => '',
		'appendKey' => 'Cacheable',
		'methodKeys' => array(
			'getAll' => 'getAll',
			'getList' => 'getList',
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
			'getById' => array('id'),
			'getBySlug' => array('slug')
		)
	);

	/**
	 * Is the current query attempting to cache.
	 *
	 * @access protected
	 * @var boolean
	 */
	protected $_isCaching = false;

	/**
	 * The key and expires parameters for the current query.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_currentQuery = array();

	/**
	 * The Model's DB config before caching begins.
	 *
	 * @access protected
	 * @var string
	 */
	protected $_previousDbConfig;

	/**
	 * Cached results for the current request.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_cached = array();

	/**
	 * Merge settings.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 * @return void
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
				'engine'	=> 'File',
				'serialize'	=> true,
				'prefix'	=> '',
				'path'		=> $cachePath,
				'duration'	=> $settings['expires']
			));
		}
	}

	/**
	 * When this behavior is unloaded, delete all associated cache.
	 *
	 * @access public
	 * @param Model $model
	 * @return void
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
	 * @access public
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
			'expires' => $expires
		);

		// Are results already cached?
		$results = null;

		if (!empty($this->_cached[$key])) {
			$results = $this->_cached[$key];

		} else if ($fromCache = $this->readCache($model, $key)) {
			$results = $fromCache;
		}

		// Begin caching by replacing with ShimSource
		if ($results) {
			$this->_cached[$key] = $results;
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
	 * @access public
	 * @param Model $model
	 * @param mixed $results
	 * @param boolean $primary
	 * @return mixed
	 */
	public function afterFind(Model $model, $results, $primary) {
		if ($this->_isCaching) {
			$query = $this->_currentQuery;

			// Pull from cache
			if (!empty($this->_cached[$query['key']])) {
				$model->useDbConfig = $this->_previousDbConfig;

				$results = $this->_cached[$query['key']];

			// Write the new results if it has data
			} else if ($results) {
				if ($key = $this->settings[$model->alias]['appendKey']) {
					foreach ($results as &$result) {
						$result[$key] = $query;
					}
				}

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
	 * @access public
	 * @param Model $model
	 * @param boolean $created
	 * @return boolean
	 */
	public function afterSave(Model $model, $created = true) {
		$id = $model->id;
		$key = $this->settings[$model->alias]['methodKeys']['getById'];
		$events = $this->settings[$model->alias]['events'];

		if ($id && $key && (($created && $events['onCreate']) || (!$created && $events['onUpdate']))) {
			$this->writeCache($model, array($model->alias . '::' . $key, $id), array($model->read(null, $id)));
		}

		if ($getList = $this->settings[$model->alias]['methodKeys']['getList']) {
			$this->deleteCache($model, array($model->alias . '::' . $getList));
		}

		return $created;
	}

	/**
	 * Once a record has been deleted, remove the cached result.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
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
	 * @access public
	 * @param Model $model
	 * @param array|string $keys
	 * @param Closure $callback
	 * @param string $expires
	 * @return mixed
	 * @throws Exception
	 */
	public function cache(Model $model, $keys, $callback, $expires = null) {
		if (!($callback instanceof Closure)) {
			throw new Exception(sprintf('A Closure is required for %s.', __METHOD__));
		}

		if (Configure::read('Cache.disable')) {
			return $callback($this);
		}

		$key = $this->cacheKey($model, $keys);
		$results = $this->readCache($model, $key);

		if (!$results) {
			$results = $callback($this);

			$this->writeCache($model, $key, $results, $expires);
		}

		return $results;
	}

	/**
	 * Generate a cache key. The first index should be the method name, the other indices should be unique values.
	 *
	 * @access public
	 * @param Model $model
	 * @param array|string $keys
	 * @param boolean $prefix
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
	 * Return the expiration time for cache. Either used the passed value, or the settings default.
	 *
	 * @access public
	 * @param Model $model
	 * @param mixed $expires
	 * @return int|string
	 */
	public function getExpiration(Model $model, $expires = null) {
		if (!$expires) {
			if ($this->settings[$model->alias]['expires']) {
				$expires = $this->settings[$model->alias]['expires'];
			} else {
				$expires = '+5 minutes';
			}
		}

		return $expires;
	}

	/**
	 * Read data from the cache.
	 *
	 * @access public
	 * @param Model $model
	 * @param array|string $keys
	 * @return mixed
	 */
	public function readCache(Model $model, $keys) {
		return Cache::read($this->cacheKey($model, $keys), $this->settings[$model->alias]['cacheConfig']);
	}

	/**
	 * Write data to the cache. Be sure to parse the cache key and validate the config and expires.
	 *
	 * @access public
	 * @param Model $model
	 * @param array|string $keys
	 * @param mixed $value
	 * @param int|string $expires
	 * @return boolean
	 */
	public function writeCache(Model $model, $keys, $value, $expires = null) {
		Cache::set('duration', $this->getExpiration($model, $expires), $this->settings[$model->alias]['cacheConfig']);

		return Cache::write($this->cacheKey($model, $keys), $value, $this->settings[$model->alias]['cacheConfig']);
	}

	/**
	 * Delete a cached item based on the defined key(s).
	 *
	 * @access public
	 * @param Model $model
	 * @param array|string $keys
	 * @return boolean
	 */
	public function deleteCache(Model $model, $keys) {
		return Cache::delete($this->cacheKey($model, $keys), $this->settings[$model->alias]['cacheConfig']);
	}

	/**
	 * Global function to reset specific cache keys within each model. By default, reset the getById and getList method keys.
	 * If the ID passed is an array of IDs, run through each hook and reset those caches only if each field exists.
	 *
	 * @access public
	 * @param Model $model
	 * @param string|array $id
	 * @return boolean
	 */
	public function resetCache(Model $model, $id = null) {
		$alias = $model->alias;

		if ($getList = $this->settings[$alias]['methodKeys']['getList']) {
			$this->deleteCache($model, array($alias . '::' . $getList));
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
	 * @access public
	 * @param Model $model
	 * @return boolean
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
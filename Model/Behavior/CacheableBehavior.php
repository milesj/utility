<?php
/**
 * CacheableBehavior
 *
 * A CakePHP Behavior that will automatically read, write and delete cache for Model database queries.
 * When Model::find() is called and the cache parameter is passed, the data will be written.
 * When Model::create() or Model::update() is called, the cache key with the associated Model::$id will be written.
 * When Model::delete() is called, the cache key with the associated Model::$id will be deleted.
 * All supports the ability to batch reset/delete cached items by providing a mapping of method and argument hooks.
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
 * @author		Miles Johnson - http://milesj.me
 * @copyright	Copyright 2006+, Miles Johnson, Inc.
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');
App::uses('Set', 'Utility');

class CacheableBehavior extends ModelBehavior {

	/**
	 * Cache configuration to use.
	 *
	 * @access public
	 * @var string
	 */
	public $cacheConfig = 'default';

	/**
	 * Database configuration to use in the model.
	 * Should use the Utility.ShimSource datasource.
	 *
	 * @access public
	 * @var string
	 */
	public $dbConfig = 'cacheable';

	/**
	 * Default expiration time for all cache items.
	 *
	 * @access public
	 * @var string
	 */
	public $expires = '+5 minutes';

	/**
	 * String to prepend to all cache keys.
	 *
	 * @access public
	 * @var string
	 */
	public $prefix = '';

	/**
	 * Should we append the cache key and expires to the results.
	 * Will add a "Cacheable" index to each array index.
	 *
	 * @access public
	 * @var boolean
	 */
	public $appendKey = true;

	/**
	 * Mapping of primary cache keys to methods.
	 *
	 * @access public
	 * @var array
	 */
	public $methodKeys = array(
		'getById' => 'getById',
		'getList' => 'getList'
	);

	/**
	 * Toggle cache reset events for specific conditions.
	 *
	 * @access public
	 * @var array
	 */
	public $events = array(
		'onCreate' => true,
		'onUpdate' => true,
		'onDelete' => true
	);

	/**
	 * Mapping of cache keys and arguments.
	 * Will be looped through and reset using resetCache().
	 *
	 * @access public
	 * @var array
	 */
	public $resetHooks = array(
		'getById' => array('id')
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
	 */
	public function setup(Model $model, $settings = array()) {
		$this->model = $model;
		$this->_set($settings);
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
			$this->resetCache($model->id);
		}

		foreach ($this->_cached as $key => $value) {
			$this->deleteCache($key);
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

		$key = $this->cacheKey($key, false);
		$expires = $this->getExpiration($expires);

		$this->_isCaching = true;
		$this->_currentQuery = array(
			'key' => $key,
			'expires' => $expires
		);

		// Are results already cached?
		$results = null;

		if (!empty($this->_cached[$key])) {
			$results = $this->_cached[$key];

		} else if ($fromCache = $this->readCache($key)) {
			$results = $fromCache;
		}

		// Begin caching by replacing with a shim datasource
		if ($results) {
			$this->_cached[$key] = $results;
			$this->_previousDbConfig = $model->useDbConfig;

			// Create datasource config if it doesn't exist
			$dbConfig = $this->dbConfig;

			if (!isset(ConnectionManager::$config->{$dbConfig})) {
				ConnectionManager::$config->{$dbConfig} = array(
					'datasource' => 'Utility.ShimSource',
					'database' => null
				);
			}

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
			} else if (!empty($results)) {
				if ($this->appendKey) {
					foreach ($results as &$result) {
						$result['Cacheable'] = $query;
					}
				}

				$this->writeCache($query['key'], $results, $query['expires']);
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
		$key = $this->methodKeys['getById'];

		if ($model->id && $key && (($created && $this->events['onCreate']) || (!$created && $this->events['onUpdate']))) {
			$this->writeCache(array($model->alias . '::' . $key, $model->id), $model->data);
		}

		return $created;
	}

	/**
	 * Once a record has been deleted, remove the cached result.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean|void
	 */
	public function afterDelete(Model $model) {
		$key = $this->methodKeys['getById'];

		if ($model->id && $key && $this->events['onDelete']) {
			$this->deleteCache(array($model->alias . '::' . $key, $model->id));
		}

		return true;
	}

	/**
	 * Cache data by using a Closure callback to generate the result set.
	 * This method can be used within other methods in place the of the find() approach.
	 *
	 * @access public
	 * @param array|string $keys
	 * @param Closure $callback
	 * @param string $expires
	 * @return mixed
	 * @throws Exception
	 */
	public function cache($keys, $callback, $expires = null) {
		if (!($callback instanceof Closure)) {
			throw new Exception(sprintf('A Closure is required for %s', __METHOD__));
		}

		if (Configure::read('Cache.disable')) {
			return $callback($this);
		}

		$key = $this->cacheKey($keys);
		$results = $this->readCache($key);

		if (empty($results)) {
			$results = $callback($this);

			$this->writeCache($key, $results, $expires);
		}

		return $results;
	}

	/**
	 * Generate a cache key. The first index should be the method name, the other indices should be unique values.
	 *
	 * @access public
	 * @param array|string $keys
	 * @param boolean $prefix
	 * @return string
	 */
	public function cacheKey($keys, $prefix = true) {
		if (is_array($keys)) {
			$key = array_shift($keys);

			if (!empty($keys)) {
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
			$key = (string) $this->prefix . $key;
		}

		return $key;
	}

	/**
	 * Return the expiration time for cache. Either used the passed value, or the settings default.
	 *
	 * @access public
	 * @param mixed $expires
	 * @return int|string
	 */
	public function getExpiration($expires = null) {
		if (!$expires) {
			if ($this->expires) {
				$expires = $this->expires;
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
	 * @param array|string $keys
	 * @return mixed
	 */
	public function readCache($keys) {
		return Cache::read($this->cacheKey($keys), $this->cacheConfig);
	}

	/**
	 * Write data to the cache. Be sure to parse the cache key and validate the config and expires.
	 *
	 * @access public
	 * @param array|string $keys
	 * @param mixed $value
	 * @param int|string $expires
	 * @return void
	 */
	public function writeCache($keys, $value, $expires = null) {
		Cache::set('duration', $this->getExpiration($expires), $this->cacheConfig);

		Cache::write($this->cacheKey($keys), $value, $this->cacheConfig);
	}

	/**
	 * Delete a cached item based on the defined key(s).
	 *
	 * @access public
	 * @param array|string $keys
	 * @return boolean
	 */
	public function deleteCache($keys) {
		return Cache::delete($this->cacheKey($keys), $this->cacheConfig);
	}

	/**
	 * Global function to reset specific cache keys within each model. By default, reset the getById and getList method keys.
	 * If the ID passed is an array of IDs, run through each hook and reset those caches only if each field exists.
	 *
	 * @access public
	 * @param string|array $id
	 * @return void
	 */
	public function resetCache($id = null) {
		$alias = $this->model->alias;

		if ($getList = $this->methodKeys['getList']) {
			$this->deleteCache(array($alias . '::' . $getList));
		}

		if (empty($id)) {
			return;

		} else if (!is_array($id)) {
			$id = array('id' => $id);
		}

		if (!empty($this->resetHooks)) {
			foreach ($this->resetHooks as $key => $args) {
				$continue = true;
				$keys = array($alias . '::' . $key);

				foreach ($args as $field) {
					if (isset($id[$field])) {
						$keys[] = $id[$field];
					} else {
						$continue = false;
						break;
					}
				}

				if ($continue) {
					$this->deleteCache($keys);
				}
			}
		}
	}

}
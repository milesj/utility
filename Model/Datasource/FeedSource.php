<?php
/**
 * FeedSource
 *
 * A DataSource that can read and parse web feeds and aggregate them into a single result.
 * Supports RSS, RDF and Atom feed types.
 *
 * {{{
 *		public $feed = array('datasource' => 'Utility.FeedSource');
 * }}}
 *
 * @version		3.0.1
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('Folder', 'Utility');
App::uses('DataSource', 'Model/Datasource');
App::uses('HttpSocket', 'Network/Http');
App::import('Vendor', 'Utility.TypeConverter');

class FeedSource extends DataSource {

	/**
	 * The processed feeds in array format.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_feeds = array();

	/**
	 * Apply the cache settings.
	 *
	 * @access public
	 * @param array $config
	 */
	public function __construct($config = array()) {
		parent::__construct($config);

		if (Cache::config('feeds') === false) {
			$cachePath = CACHE . 'feeds' . DS;

			if (!file_exists($cachePath)) {
				$folder = new Folder();
				$folder->create($cachePath, 0777);
			}

			Cache::config('feeds', array(
				'engine'	=> 'File',
				'serialize'	=> true,
				'prefix'	=> 'feed_',
				'path'		=> $cachePath,
				'duration'	=> '+1 day'
			));
		}
	}

	/**
	 * Describe the supported feeds.
	 *
	 * @access public
	 * @param Model|string $model
	 * @return array
	 */
	public function describe($model) {
		return $this->_feeds;
	}

	/**
	 * Return a list of aggregated feed URLs.
	 *
	 * @access public
	 * @param array $data
	 * @return array
	 */
	public function listSources($data = null) {
		return array_keys($this->_feeds);
	}

	/**
	 * Grab the feeds through an HTTP request and parse it into an array.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $queryData
	 * @return array
	 */
	public function read(Model $model, $queryData = array()) {
		$query = $queryData;
		$defaults = array(
			'root' => '',
			'cache' => false,
			'expires' => '+1 hour'
		);

		if (!empty($query['feed'])) {
			$query['feed'] = (array) $query['feed'] + $defaults;
		} else {
			$query['feed'] = $defaults;
		}

		// Get order sorting
		$query['feed']['order'] = 'ASC';
		$query['feed']['sort'] = 'date';

		if (isset($query['order'][0])) {
			if (is_array($query['order'][0])) {
				$sort = array_keys($query['order'][0]);
				$query['feed']['sort'] = $sort[0];
				$query['feed']['order'] = strtoupper($query['order'][0][$query['feed']['sort']]);
			} else {
				$query['feed']['order'] = strtoupper($query['order'][0]);
			}
		}

		// Attempt to get the feed from the model
		if (empty($query['conditions']) && !empty($model->feedUrls)) {
			$query['conditions'] = (array) $model->feedUrls;
		}

		// Loop the sources
		if (!empty($query['conditions'])) {
			$cache = $query['feed']['cache'];

			// Detect cached first
			if ($cache) {
				Cache::set('duration', $query['feed']['expires']);

				$results = Cache::read($cache, 'feeds');

				if ($results && is_array($results)) {
					return $this->_truncate($results, $query['limit']);
				}
			}

			$http = new HttpSocket();

			// Request and parse feeds
			foreach ($query['conditions'] as $source => $url) {
				$cacheKey = $model->name . '_' . md5($url);

				$this->_feeds[$url] = Cache::read($cacheKey, 'feeds');

				if (!$this->_feeds[$url]) {
					if ($response = $http->get($url)) {
						$this->_feeds[$url] = $this->_process($response, $query, $source);

						Cache::write($cacheKey, $this->_feeds[$url], 'feeds');
					}
				}
			}

			// Combine and sort feeds
			$results = array();

			if ($this->_feeds) {
				foreach ($query['conditions'] as $source => $url) {
					if ($this->_feeds[$url]) {
						$results = $this->_feeds[$url] + $results;
					}
				}

				$results = array_filter($results);

				if ($query['feed']['order'] == 'ASC') {
					krsort($results);
				} else {
					ksort($results);
				}

				if ($cache) {
					Cache::set(array('duration' => $query['feed']['expires']));
					Cache::write($cache, $results, 'feeds');
				}
			}

			return $this->_truncate($results, $query['limit']);
		}

		return array();
	}

	/**
	 * Extracts a certain value from a node.
	 *
	 * @access protected
	 * @param string $item
	 * @param array $keys
	 * @return string
	 */
	protected function _extract($item, $keys = array('value')) {
		if (is_array($item)) {
			if (isset($item[0])) {
				return $this->_extract($item[0], $keys);

			} else {
				foreach ($keys as $key) {
					if (!empty($item[$key])) {
						return trim($item[$key]);

					} else if (isset($item['attributes'])) {
						return $this->_extract($item['attributes'], $keys);
					}
				}
			}
		} else {
			return trim($item);
		}
	}

	/**
	 * Processes the feed and rebuilds an array based on the feeds type (RSS, RDF, Atom).
	 *
	 * @access protected
	 * @param HttpResponse $response
	 * @param array $query
	 * @param string $source
	 * @return boolean
	 */
	protected function _process(HttpResponse $response, $query, $source) {
		$feed = TypeConverter::toArray($response->body());
		$clean = array();

		if (!empty($query['root']) && !empty($feed[$query['feed']['root']])) {
			$items = $feed[$query['feed']['root']];
		} else {
			// RSS
			if (isset($feed['channel']) && isset($feed['channel']['item'])) {
				$items = $feed['channel']['item'];
			// RDF
			} else if (isset($feed['item'])) {
				$items = $feed['item'];
			// Atom
			} else if (isset($feed['entry'])) {
				$items = $feed['entry'];
			// XML
			} else {
				$items = $feed;
			}
		}

		if (empty($items) || !is_array($items)) {
			return $clean;
		}

		// Gather elements
		$elements = array(
			'title',
			'guid' => array('guid', 'id'),
			'date' => array('date', 'pubDate', 'published', 'updated'),
			'link' => array('link', 'origLink'),
			'image' => array('image', 'thumbnail'),
			'author' => array('author', 'writer', 'editor', 'user'),
			'source' => array('source'),
			'description' => array('description', 'desc', 'summary', 'content', 'text')
		);

		if (is_array($query['fields'])) {
			$elements = array_merge_recursive($elements, $query['fields']);
		}

		// Loop the feed
		foreach ($items as $item) {
			$data = array();

			foreach ($elements as $element => $keys) {
				if (is_numeric($element)) {
					$element = $keys;
					$keys = array($keys);
				}

				if (isset($keys['attributes'])) {
					$attributes = $keys['attributes'];
					unset($keys['attributes']);
				} else {
					$attributes = array('value', 'href', 'src', 'name', 'label');
				}

				if (isset($keys['keys'])) {
					$keys = $keys['keys'];
				}

				foreach ($keys as $key) {
					if (isset($item[$key]) && empty($data[$element])) {
						$value = $this->_extract($item[$key], $attributes);

						if (!empty($value)) {
							$data[$element] = $value;
							break;
						}
					}
				}
			}

			if (empty($data['link'])) {
				trigger_error(sprintf('Feed %s does not have a valid link element.', $source), E_USER_NOTICE);
				continue;
			}

			if (empty($data['source']) && $source) {
				$data['source'] = (string) $source;
			}

			$sort = null;

			if (isset($data[$query['feed']['sort']])) {
				$sort = $data[$query['feed']['sort']];
			}

			if (!$sort) {
				if ($query['feed']['sort'] == 'date' && isset($data['date'])) {
					$sort = strtotime($data['date']);
				} else {
					$sort = microtime();
				}
			}

			if ($data) {
				$clean[$sort] = $data;
			}
		}

		return $clean;
	}

	/**
	 * Truncates the feed to a certain length.
	 *
	 * @access protected
	 * @param array $feed
	 * @param int $count
	 * @return array
	 */
	protected function _truncate($feed, $count = null) {
		if (empty($feed)) {
			return $feed;
		}

		if (!is_numeric($count)) {
			$count = 20;
		}

		if (count($feed) > $count) {
			$feed = array_slice($feed, 0, $count);
		}

		return $feed;
	}

}
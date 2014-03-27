<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('Folder', 'Utility');
App::uses('DataSource', 'Model/Datasource');
App::uses('HttpSocket', 'Network/Http');

use Titon\Utility\Converter;

/**
 * A DataSource that can read and parse web feeds and aggregate them into a single result.
 * Supports RSS, RDF and Atom feed types.
 *
 * {{{
 *        public $feed = array('datasource' => 'Utility.FeedSource');
 * }}}
 */
class FeedSource extends DataSource {

    /**
     * The processed feeds in array format.
     *
     * @type array
     */
    protected $_feeds = array();

    /**
     * Apply the cache settings.
     *
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
                'engine'    => 'File',
                'serialize'    => true,
                'prefix'    => 'feed_',
                'path'        => $cachePath,
                'duration'    => '+1 day'
            ));
        }
    }

    /**
     * Describe the supported feeds.
     *
     * @param Model|string $model
     * @return array
     */
    public function describe($model) {
        return $this->_feeds;
    }

    /**
     * Return a list of aggregated feed URLs.
     *
     * @param array $data
     * @return array
     */
    public function listSources($data = null) {
        return array_keys($this->_feeds);
    }

    /**
     * Grab the feeds through an HTTP request and parse it into an array.
     *
     * @param Model $model
     * @param array $queryData
     * @param int $recursive
     * @return array
     */
    public function read(Model $model, $queryData = array(), $recursive = null) {
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
            $order = $query['order'][0];

            if (is_array($order)) {
                foreach ($order as $sort => $o) {
                    $query['feed']['sort'] = $sort;
                    $query['feed']['order'] = strtoupper($o);
                    break;
                }
            } else {
                $query['feed']['order'] = strtoupper($order);
            }
        }

        // Attempt to get the feed from the model
        if (empty($query['conditions']) && !empty($model->feedUrls)) {
            $query['conditions'] = (array) $model->feedUrls;
        }

        // Loop the sources
        if (!empty($query['conditions'])) {
            $cacheKey = $query['feed']['cache'];
            $cache = (bool) $cacheKey;
            $expires = $query['feed']['expires'];

            // Change cache key
            if ($cacheKey === true) {
                $cacheKey = $model->name . '_' . md5(json_encode($query));
            }

            // Detect cached first
            if ($cache) {
                $results = Cache::read($cacheKey, 'feeds');

                if ($results && is_array($results)) {
                    return $this->_truncate($results, $query['limit']);
                }
            }

            $http = new HttpSocket();

            // Request and parse feeds
            foreach ($query['conditions'] as $source => $url) {
                $urlCacheKey = $model->name . '_' . md5($url);
                $urlData = Cache::read($urlCacheKey, 'feeds');

                if (!$urlData) {
                    $urlData = $this->_process($http->get($url), $query, $source);

                    if ($urlData && $cache) {
                        Cache::set('duration', $expires);
                        Cache::write($urlCacheKey, $urlData, 'feeds');
                    }
                }

                $this->_feeds[$url] = $urlData;
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

                if ($query['feed']['order'] === 'ASC') {
                    krsort($results);
                } else {
                    ksort($results);
                }

                if ($cache) {
                    Cache::set('duration', $expires);
                    Cache::write($cacheKey, $results, 'feeds');
                }
            }

            return $this->_truncate($results, $query['limit']);
        }

        return array();
    }

    /**
     * Extracts a certain value from a node.
     *
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
        }

        return trim($item);
    }

    /**
     * Processes the feed and rebuilds an array based on the feeds type (RSS, RDF, Atom).
     *
     * @param HttpSocketResponse $response
     * @param array $query
     * @param string $source
     * @return bool
     */
    protected function _process(HttpSocketResponse $response, $query, $source) {
        if (!$response->isOk()) {
            return array();
        }

        $feed = Converter::toArray($response->body());
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
            'title' => array('title'),
            'guid' => array('guid', 'id'),
            'date' => array('date', 'pubDate', 'published', 'updated'),
            'link' => array('link', 'origLink'),
            'image' => array('image', 'thumbnail', 'enclosure'),
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
                if (isset($keys['attributes'])) {
                    $attributes = $keys['attributes'];
                    unset($keys['attributes']);
                } else {
                    $attributes = array('value', 'href', 'src', 'name', 'label', 'url');
                }

                if (isset($keys['keys'])) {
                    $keys = $keys['keys'];
                }

                foreach ($keys as $key) {
                    if (isset($item[$key]) && empty($data[$element])) {
                        if ($value = $this->_extract($item[$key], $attributes)) {
                            $data[$element] = $value;
                            break;
                        }
                    }
                }
            }

            if (empty($data['link'])) {
                trigger_error(sprintf('Feed %s does not have a valid link element', $source), E_USER_NOTICE);
                continue;
            }

            if (empty($data['source']) && $source) {
                $data['source'] = (string) $source;
            }

            // Determine how to sort
            $sortBy = $query['feed']['sort'];

            if (isset($data[$sortBy])) {
                $sort = $data[$sortBy];
            } else if (isset($data['date'])) {
                $sort = $data['date'];
            } else {
                $sort = null;
            }

            if ($sortBy === 'date' && $sort) {
                $sort = strtotime($sort);
            } else if (!$sort) {
                $sort = microtime();
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
     * @param array $feed
     * @param int $count
     * @return array
     */
    protected function _truncate($feed, $count = null) {
        if (!$feed) {
            return $feed;
        }

        if ($count === null) {
            $count = 20;
        }

        if ($count && count($feed) > $count) {
            $feed = array_slice($feed, 0, $count);
        }

        return array_values($feed);
    }

}

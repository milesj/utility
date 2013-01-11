<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('AppModel', 'Model');

/**
 * A Modal that connects to the FeedSource and defines a custom find() function specific to feed aggregation.
 */
class Aggregator extends AppModel {

	/**
	 * No database table needed.
	 *
	 * @var boolean
	 */
	public $useTable = false;

	/**
	 * Use FeedSource.
	 *
	 * @var boolean
	 */
	public $useDbConfig = 'feed';

	/**
	 * Overwrite the find method to be specific for feed aggregation.
	 * Set the default settings and prepare the URLs.
	 *
	 * @param string $type
	 * @param array $query
	 * @return array
	 */
	public function find($type = 'first', $query = array()) {
		$query = $query + array(
			'fields' => array(),
			'order' => array('date' => 'ASC'),
			'limit' => 20,
			'feed' => array(
				'root' => '',
				'cache' => false,
				'expires' => '+1 hour'
			)
		);

		return parent::find($type, $query);
	}

	/**
	 * Format the date a certain way.
	 *
	 * @param array $results
	 * @param boolean $primary
	 * @return array
	 */
	public function afterFind($results = array(), $primary = false) {
		if ($results) {
			foreach ($results as &$result) {
				if (isset($result['date'])) {
					if ($time = DateTime::createFromFormat(DateTime::RFC822, $result['date'] . 'C')) {
						$result['date'] = $time->format('Y-m-d H:i:s');
					}
				}
			}
		}

		return $results;
	}

}
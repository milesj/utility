<?php
/**
 * Aggregator
 *
 * A Modal that connects to the FeedSource and defines a custom find() function specific to feed aggregation.
 *
 * @version		1.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('Model', 'Model');

class Aggregator extends Model {

	/**
	 * No database table needed.
	 *
	 * @access public
	 * @var boolean
	 */
	public $useTable = false;

	/**
	 * Use FeedSource.
	 *
	 * @access public
	 * @var boolean
	 */
	public $useDbConfig = 'feed';

	/**
	 * Overwrite the find method to be specific for feed aggregation.
	 * Set the default settings and prepare the URLs.
	 *
	 * @access public
	 * @param string $type
	 * @param array $options
	 * @return array
	 */
	public function find($type, array $options = array()) {
		$options = $options + array(
			'fields' => array(),
			'order' => array('date' => 'ASC'),
			'limit' => 20,
			'feed' => array(
				'root' => '',
				'cache' => false,
				'expires' => '+1 hour'
			)
		);

		return parent::find($type, $options);
	}

	/**
	 * Format the date a certain way.
	 *
	 * @access public
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
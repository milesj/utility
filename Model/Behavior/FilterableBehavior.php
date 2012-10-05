<?php
/**
 * FilterableBehavior
 *
 * A CakePHP Behavior that will run sanitization filters on specific fields during an insert or update.
 * The currently supported filters are: html escaping, sql escaping, strip tags, paranoid.
 *
 * {{{
 *		class Topic extends AppModel {
 *			public $actsAs = array(
 * 				'Utility.Filterable' => array(
 *					'fieldOne' => array(
 *						'strip' => true
 *					),
 *					'fieldTwo' => array(
 *						'html' => array('flags' => ENT_QUOTES)
 * 					)
 * 				)
 * 			);
 *		}
 * }}}
 *
 * @version		1.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

class FilterableBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 * 	html		- Escapes HTML entities
	 * 	strip		- Removes HTML tags
	 * 	paranoid	- Removes any non-alphanumeric characters
	 *	escape		- Escapes SQL queries
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaults = array(
		'html' => array(
			'encoding' => 'UTF-8',
			'flags' => ENT_QUOTES,
			'double' => false
		),
		'strip' => array(
			'allowed' => ''
		),
		'paranoid' => array(
			'allowed' => array()
		),
		'escape' => array()
	);

	/**
	 * Merge in the settings for each field.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 * @return void
	 */
	public function setup(Model $model, $settings = array()) {
		foreach ($settings as $field => $options) {
			$filters = array();

			foreach ($options as $key => $value) {
				if (!isset($this->_defaults[$key])) {
					continue;
				}

				if (is_array($value)) {
					if (($key === 'strip' || $key === 'paranoid') && empty($key['allowed'])) {
						$value = array('allowed' => $value);
					}

					if (empty($value['filter'])) {
						$value['filter'] = true;
					}
				} else {
					$value = array('filter' => (bool) $value);
				}

				$filters[$key] = Hash::merge($this->_defaults[$key], $value);
			}

			$this->settings[$model->alias][$field] = $filters;
		}
	}

	/**
	 * Run the filters before each save.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean|mixed
	 */
	public function beforeSave(Model $model) {
		$settings = $this->settings[$model->alias];

		if ($model->data[$model->alias]) {
			foreach ($model->data[$model->alias] as $key => &$value) {
				if (isset($settings[$key])) {
					$filters = $settings[$key];

					// Strip tags
					if (isset($filters['strip']) && $filters['strip']['filter']) {
						$value = strip_tags($value, (string) $filters['strip']['allowed']);
					}

					// HTML escape
					if (isset($filters['html']) && $filters['html']['filter']) {
						$value = Sanitize::html($value, array(
							'quotes' => $filters['html']['flags'],
							'charset' => $filters['html']['encoding'],
							'double' => $filters['html']['double']
						));
					}

					// Paranoid
					if (isset($filters['paranoid']) && $filters['paranoid']['filter']) {
						$value = Sanitize::paranoid($value, (array) $filters['paranoid']['allowed']);
					}

					// SQL escape
					if (isset($filters['escape']) && $filters['escape']['filter']) {
						$value = Sanitize::escape($value, $model->useDbConfig);
					}
				}
			}
		}

		return true;
	}

}
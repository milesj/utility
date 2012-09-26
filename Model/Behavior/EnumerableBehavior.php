<?php
/**
 * EnumerableBehavior
 *
 * A CakePHP Behavior that emulates enumerable fields within the model. Each model that contains an enum field
 * (a field of multiple designated values), should define an $enum map and associated constants.
 *
 * After every query, any field within the $enum map will be replaced by the respective value (example: a status
 * of 0 will be replaced with PENDING). This allows for easy readability for clients and easy usability,
 * flexibility and portability for developers.
 *
 * {{{
 *		class User extends AppModel {
 * 			const PENDING = 0;
 * 			const ACTIVE = 1;
 * 			const INACTIVE = 2;
 *
 *			public $actsAs = array('Utility.Enumerable');
 *
 * 			public $enum = array(
 *				'status' => array(
 *					self::PENDING => 'PENDING',
 * 					self::ACTIVE => 'ACTIVE',
 * 					self::INACTIVE => 'INACTIVE
 *				)
 * 			);
 *		}
 *
 * 		// Return the enum array for the status field
 * 		$user->enum('status');
 *
 * 		// Find all users by status
 * 		$user->findByStatus(User::PENDING);
 * }}}
 *
 * @version		1.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

class EnumerableBehavior extends ModelBehavior {

	/**
	 * Format options.
	 */
	const NO = false;
	const REPLACE = 'replace';
	const APPEND = 'append';

	/**
	 * Persist the value in the response by appending a new field named <field><suffix>.
	 *
	 * @access public
	 * @var array
	 */
	public $persist = true;

	/**
	 * Should we replace all enum fields with the respective mapped value.
	 *
	 * @access public
	 * @var boolean
	 */
	public $format = self::REPLACE;

	/**
	 * Toggle the replacing of raw values with enum values when a record is being updated (checks Model::$id).
	 *
	 * @access public
	 * @var boolean
	 */
	public $onUpdate = false;

	/**
	 * The suffix to append to the persisted value.
	 *
	 * @access public
	 * @var string
	 */
	public $suffix = '_enum';

	/**
	 * The enums for all models.
	 *
	 * @access protected
	 * @var array
	 */
	protected $_enums = array();

	/**
	 * Store the settings and Model::$enum.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 * @throws InvalidArgumentException
	 */
	public function setup(Model $model, $settings = array()) {
		if (isset($model->enum)) {
			$enum = $model->enum;
			$parent = $model;

			// Grab the parent enum and merge
			while ($parent = get_parent_class($parent)) {
				$props = get_class_vars($parent);

				if (isset($props['enum'])) {
					$enum = $enum + $props['enum'];
				}
			}

			$this->_enums[$model->alias] = $enum;
		}

		$this->_set($settings);
	}

	/**
	 * Helper method for grabbing and filtering the enum from the model.
	 *
	 * @access public
	 * @param Model|string $model
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 * @throws Exception
	 */
	public function enum($model, $key = null, $value = null) {
		$alias = is_string($model) ? $model : $model->alias;

		if (!isset($this->_enums[$alias])) {
			throw new Exception(sprintf('%s::$enum does not exist.', $alias));
		}

		$enum = $this->_enums[$alias];

		if ($key) {
			if (!isset($enum[$key])) {
				throw new Exception(sprintf('Field %s does not exist within %s::$enum.', $key, $model->alias));
			}

			if ($value !== null) {
				return isset($enum[$key][$value]) ? $enum[$key][$value] : null;
			} else {
				return $enum[$key];
			}
		}

		return $enum;
	}

	/**
	 * Generate select options based on the enum fields which will be used for form input auto-magic.
	 * If a Controller is passed, it will auto-set the data to the views.
	 *
	 * @access public
	 * @param Model $model
	 * @param Controller|null $controller
	 * @return array
	 */
	public function options(Model $model, Controller $controller = null) {
		$enum = array();

		if (isset($this->_enums[$model->alias])) {
			foreach ($this->_enums[$model->alias] as $key => $values) {
				$var = Inflector::variable(Inflector::pluralize(preg_replace('/_id$/', '', $key)));

				if ($controller) {
					$controller->set($var, $values);
				}

				$enum[$var] = $values;
			}
		}

		return $enum;
	}

	/**
	 * Format the results by replacing all enum fields with their respective value replacement.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $results
	 * @param boolean $primary
	 * @return mixed
	 */
	public function afterFind(Model $model, $results, $primary = true) {
		if (!$this->format || ($model->id && !$this->onUpdate)) {
			return $results;
		}

		if ($results) {
			$alias = $model->alias;
			$enum = $this->_enums[$alias];

			foreach ($results as &$result) {
				foreach ($enum as $key => $nop) {
					if (isset($result[$alias][$key])) {
						$value = $result[$alias][$key];

						if ($this->format === self::REPLACE) {
							$result[$alias][$key] = $this->enum($model, $key, $value);

							if ($this->persist) {
								$result[$alias][$key . $this->suffix] = $value;
							}
						} else if ($this->format === self::APPEND) {
							$result[$alias][$key . $this->suffix] = $this->enum($model, $key, $value);
						}
					}
				}
			}
		}

		return $results;
	}

}
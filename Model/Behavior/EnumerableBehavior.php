<?php
/**
 * EnumerableBehavior
 *
 * A CakePHP Behavior that attaches a file to a model, and uploads automatically, then stores a value in the database.
 *
 * @author		Miles Johnson - http://milesj.me
 * @copyright	Copyright 2006+, Miles Johnson, Inc.
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

class EnumerableBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 * 	persistValue - Persist the raw value in the response by appending a new field named <field>_enum
	 * 	formatOnUpdate - Toggle the replacing of raw values with enum values when a record is being updated (checks Model::$id)
	 *
	 * @var array
	 */
	public $settings = array(
		'persistValue' => true,
		'formatOnUpdate' => false
	);

	/**
	 * The enum from the model.
	 *
	 * @var array
	 */
	public $enum = array();

	/**
	 * Store the settings and Model::$enum.
	 *
	 * @param Model $model
	 * @param array $settings
	 * @throws Exception
	 */
	public function setup(Model $model, $settings = array()) {
		if (!isset($model->enum)) {
			throw new Exception(sprintf('%s::$enum does not exist', $model->alias));
		}

		$enum = $model->enum;

		// Grab the parent enum and merge
		while ($parent = get_parent_class($model)) {
			$object = new $parent();

			if (isset($object->enum)) {
				$enum = $enum + $object->enum;
			}

			$model = $object;
		}

		$this->enum = $enum;
		$this->settings = array_merge($this->settings, $settings);
	}

	/**
	 * Helper method for grabbing and filtering the enum from the model.
	 *
	 * @param Model $model
	 * @param string|null $key
	 * @param string|null $value
	 * @return array
	 * @throws Exception
	 */
	public function enum(Model $model, $key = null, $value = null) {
		$enum = $this->enum;

		if ($key) {
			if (!isset($enum[$key])) {
				throw new Exception(sprintf('Field %s does not exist within %s::$enum', $key, $model->alias));
			}

			if ($value) {
				return isset($enum[$key][$value]) ? $enum[$key][$value] : null;
			} else {
				return $enum[$key];
			}
		}

		return $enum;
	}

	/**
	 * Format the results by replacing all enum fields with their respective value replacement.
	 *
	 * @param Model $model
	 * @param array $results
	 * @param boolean $primary
	 * @return mixed
	 */
	public function afterFind(Model $model, $results, $primary) {
		if (!empty($model->id) && !$this->settings['formatOnUpdate']) {
			return $results;
		}

		if (!empty($results)) {
			$enum = $this->enum;
			$alias = $model->alias;
			$settings = $this->settings;
			$isMulti = true;

			if (!isset($results[0])) {
				$results = array($results);
				$isMulti = false;
			}

			foreach ($results as &$result) {
				foreach ($enum as $key => $values) {
					if (isset($result[$alias][$key])) {
						$value = $result[$alias][$key];

						// Persist ID
						if ($settings['persistValue']) {
							$result[$alias][$key . '_enum'] = $value;
						}

						$result[$alias][$key] = $this->enum($model, $key, $value);
					}
				}
			}

			if (!$isMulti) {
				$results = $results[0];
			}
		}

		return $results;
	}

}
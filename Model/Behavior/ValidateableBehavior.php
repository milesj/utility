<?php
/**
 * ValidateableBehavior
 *
 * A CakePHP Behavior that allows for multiple validation sets to exist,
 * and the ability to toggle which set should be used for validation before each Model::save().
 * Simply define Model::$validations and call Model::validate('setName') before a save.
 *
 * {{{
 *		class User extends AppModel {
 *			public $actsAs = array('Utility.Validateable');
 *
 *			public $validations = array(
 *				'setOne' => array(),
 *				'setTwo' => array()
 * 			);
 *
 * 			public function updatePassword($id, $data) {
 *				$this->id = $id;
 *				$this->validate('setOne');
 *
 *				return $this->save($data, true);
 * 			}
 *		}
 * }}}
 *
 * @version		1.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

class ValidateableBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 * 	defaultSet	- The default validation set to use if none defined
	 * 	resetAfter	- Should Model::$validate be reset after validation
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaults = array(
		'defaultSet' => 'default',
		'resetAfter' => true
	);

	/**
	 * Merge settings.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 */
	public function setup(Model $model, $settings = array()) {
		$this->settings[$model->alias] = array_merge($this->_defaults, $settings);
	}

	/**
	 * Set the validation set to use.
	 *
	 * @access public
	 * @param Model $model
	 * @param string $set
	 * @return Model
	 * @throws Exception
	 */
	public function validate(Model $model, $set) {
		if (!isset($model->validations[$set])) {
			throw new Exception(sprintf('Validation set %s does not exist.', $set));
		}

		$model->validate = $model->validations[$set];

		return $model;
	}

	/**
	 * If validate is empty and a default set exists, apply the rules.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
	 */
	public function beforeValidate(Model $model) {
		$default = $this->settings[$model->alias]['defaultSet'];

		if (empty($model->validate) && isset($model->validations[$default])) {
			$this->validate($model, $default);
		}

		return true;
	}

	/**
	 * Reset Model::$validate after validation.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
	 */
	public function afterValidate(Model $model) {
		if ($this->settings[$model->alias]['resetAfter']) {
			$model->validate = array();
		}

		return true;
	}

}
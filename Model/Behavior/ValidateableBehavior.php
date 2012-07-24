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
 * @author		Miles Johnson - http://milesj.me
 * @copyright	Copyright 2012+, Miles Johnson, Inc.
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

class ValidateableBehavior extends ModelBehavior {

	/**
	 * The default fallback set to use.
	 *
	 * @access public
	 * @var string
	 */
	public $defaultSet = 'default';

	/**
	 * Merge settings.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 */
	public function setup(Model $model, $settings = array()) {
		$this->_set($settings);
	}

	/**
	 * Set the validation set to use.
	 *
	 * @access public
	 * @param Model $model
	 * @param string $set
	 * @return void
	 * @throws Exception
	 */
	public function validate(Model $model, $set) {
		if (!isset($model->validations[$set])) {
			throw new Exception(sprintf('Validation set %s does not exist', $set));
		}

		$model->validate = $model->validations[$set];
	}

	/**
	 * If validate is empty and a default set exists, apply the rules.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
	 */
	public function beforeValidate(Model $model) {
		if (empty($model->validate) && isset($model->validations[$this->defaultSet])) {
			$this->validate($model, $this->defaultSet);
		}

		return true;
	}

}
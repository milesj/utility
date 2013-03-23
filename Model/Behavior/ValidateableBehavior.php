<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

/**
 * A CakePHP Behavior that allows for multiple validation sets to exist,
 * and the ability to toggle which set should be used for validation before each Model::save().
 * Simply define Model::$validations and call Model::validate('setName') before a save.
 * Further provides for default messaging and message localization.
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
 */
class ValidateableBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 * 	defaultSet		- The default validation set to use if none defined
	 * 	resetAfter		- Should Model::$validate be reset after validation
	 * 	localeDomain 	- Pass all validation messages through this locale domain for translation
	 * 	useDefaultMessages - Use default messages for every validation rule
	 *
	 * @var array
	 */
	protected $_defaults = array(
		'defaultSet' => 'default',
		'resetAfter' => true,
		'localeDomain' => 'default',
		'useDefaultMessages' => true
	);

	/**
	 * Default messages.
	 *
	 * @var array
	 */
	protected $_messages = array(
		'alphaNumeric' => 'May only contain alphabetical or numerical characters',
		'between' => 'May only be between %s and %s characters',
		'blank' => 'May only contain white space characters',
		'boolean' => 'Invalid boolean flag',
		'cc' => 'Invalid credit card number',
		'comparison' => 'Comparison failed',
		'custom' => 'Field is invalid',
		'date' => 'Invalid date',
		'datetime' => 'Invalid date and time',
		'decimal' => 'Invalid decimal number; accepts %s decimal places',
		'email' => 'Invalid email address; accepts email@domain.com format',
		'equalTo' => 'Field does not match',
		'extension' => 'Valid extensions are %s',
		'fileSize' => 'Invalid file size; accepts %s %s',
		'inList' => 'Valid values are %s',
		'ip' => 'Invalid IP address',
		'isUnique' => 'This value has already been taken',
		'maxLength' => 'Maximum character length is %s',
		'mimeType' => 'Valid mime types are %s',
		'minLength' => 'Minimum character length is %s',
		'money' => 'Invalid monetary amount',
		'multiple' => 'Please select valid options',
		'notEmpty' => 'Please provide a value for this field',
		'numeric' => 'May only contain numerical characters',
		'naturalNumber' => 'Please supply a number',
		'phone' => 'Invalid phone number',
		'postal' => 'Invalid postal code',
		'range' => 'Please enter a number between %s and %s',
		'ssn' => 'Invalid social security number',
		'time' => 'Invalid time',
		'uploadError' => 'File failed to upload',
		'url' => 'Invalid URL',
		'uuid' => 'Invalid UUID (universally unique identifier)'
	);

	/**
	 * Merge settings.
	 *
	 * @param Model $model
	 * @param array $settings
	 */
	public function setup(Model $model, $settings = array()) {
		$settings = array_merge($this->_defaults, $settings);
		$this->settings[$model->alias] = $settings;

		// Store the default model validate set
		if (!empty($model->validate)) {
			if (empty($model->validations)) {
				$model->validations = array();
			}

			$model->validations[$settings['defaultSet']] = $model->validate;
			$model->validate = null;
		}
	}

	/**
	 * Set the validation set to use.
	 *
	 * @param Model $model
	 * @param string $set
	 * @return Model
	 * @throws OutOfBoundsException
	 */
	public function validate(Model $model, $set) {
		if (!isset($model->validations[$set])) {
			throw new OutOfBoundsException(sprintf('Validation set %s does not exist', $set));
		}

		$rules = $model->validations[$set];
		$settings = $this->settings[$model->alias];

		// Translate present messages first
		if ($settings['localeDomain']) {
			$rules = $this->_translateRules($model, $rules);
		}

		// Add default messages second
		if ($settings['useDefaultMessages']) {
			$rules = $this->_applyMessages($model, $rules);
		}

		$model->validate = $rules;

		return $model;
	}

	/**
	 * If validate is empty and a default set exists, apply the rules.
	 *
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
	 * @param Model $model
	 * @return boolean
	 */
	public function afterValidate(Model $model) {
		if ($this->settings[$model->alias]['resetAfter']) {
			$model->validate = array();
		}

		return true;
	}

	/**
	 * Apply and set default messages if they are missing.
	 *
	 * @param Model $model
	 * @param array $rules
	 * @return array
	 */
	protected function _applyMessages(Model $model, array $rules) {
		foreach ($rules as $key => $value) {
			if (is_array($value)) {
				$rules[$key] = $this->_applyMessages($model, $value);

			} else if ($key === 'rule') {
				$rule = $value;
				$params = array();

				if (is_array($rule)) {
					$params = $rule;
					$rule = array_shift($params);
				}

				if (isset($this->_messages[$rule]) && empty($rules['message'])) {
					$message = $this->_messages[$rule];

					if ($domain = $this->settings[$model->alias]['localeDomain']) {
						$message = __d($domain, $message, $params);
					} else {
						$message = vsprintf($message, $params);
					}

					$rules['message'] = $message;
				}
			}
		}

		return $rules;
	}

	/**
	 * Translate rule messages by passing them through localization.
	 *
	 * @param Model $model
	 * @param array $rules
	 * @return array
	 */
	protected function _translateRules(Model $model, array $rules) {
		foreach ($rules as $key => $value) {
			if (is_array($value)) {
				$rules[$key] = $this->_translateRules($model, $value);

			} else if ($key === 'message') {
				$rules[$key] = __d($this->settings[$model->alias]['localeDomain'], $value);
			}
		}

		return $rules;
	}

}
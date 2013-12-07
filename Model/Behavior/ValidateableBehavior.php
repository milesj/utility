<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

/**
 * A CakePHP Behavior that allows for multiple validation sets to exist,
 * and the ability to toggle which set should be used for validation before each Model::save().
 * Simply define Model::$validations and call Model::validate('setName') before a save.
 * Further provides for default messaging and message localization.
 *
 * {{{
 *      class User extends AppModel {
 *          public $actsAs = array('Utility.Validateable');
 *
 *          public $validations = array(
 *              'setOne' => array(),
 *              'setTwo' => array()
 *          );
 *
 *          public function updatePassword($id, $data) {
 *              $this->id = $id;
 *              $this->validate('setOne');
 *
 *              return $this->save($data, true);
 *          }
 *      }
 * }}}
 */
class ValidateableBehavior extends ModelBehavior {

    /**
     * Default settings.
     *
     * @type array {
     *      @type string $defaultSet    The default validation set to use if none defined
     *      @type bool $resetAfter      Should Model::$validate be reset after validation
     *      @type bool $useDefaults     Use default messages for every validation rule
     * }
     */
    protected $_defaults = array(
        'defaultSet' => 'default',
        'resetAfter' => true,
        'useDefaults' => true
    );

    /**
     * Default messages.
     *
     * @type array
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
        if ($model->validate) {
            if (empty($model->validations)) {
                $model->validations = array();
            }

            $model->validations[$settings['defaultSet']] = $model->validate;
            $model->validate = array();
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

        // Add default messages second
        if ($settings['useDefaults']) {
            $rules = $this->_applyMessages($model, $rules);
        }

        // Merge in case there are other behaviors modifying the rules
        $model->validate = Hash::merge($model->validate, $rules);

        return $model;
    }

    /**
     * Convenience method to invalidate a field and translate the custom message.
     *
     * @param Model $model
     * @param string $field
     * @param string $message
     * @param array $params
     * @return bool
     */
    public function invalid(Model $model, $field, $message, $params = array()) {
        $model->invalidate($field, __d($model->validationDomain ?: 'default', $message, $params));

        return false;
    }

    /**
     * If validate is empty and a default set exists, apply the rules.
     *
     * @param Model $model
     * @param array $options
     * @return bool
     */
    public function beforeValidate(Model $model, $options = array()) {
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
     * @return bool
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
                if ($key !== 'rule') {
                    $rules[$key] = $this->_applyMessages($model, $value);
                }

            // Collapsed rules
            } else if (isset($this->_messages[$value]) && $key !== 'rule') {
                $rules[$key] = array(
                    $value => array(
                        'rule' => $value,
                        'message' => $this->_messages[$value]
                    )
                );

            // Missing message
            } else if ($key === 'rule') {
                $rule = $value;

                if (is_array($rule)) {
                    $rule = array_shift($params);
                }

                if (isset($this->_messages[$rule]) && empty($rules['message'])) {
                    $rules['message'] = $this->_messages[$rule];
                }
            }
        }

        return $rules;
    }

}
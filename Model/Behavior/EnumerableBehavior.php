<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

/**
 * A CakePHP Behavior that emulates enumerable fields within the model. Each model that contains an enum field
 * (a field of multiple designated values), should define an $enum map and associated constants.
 *
 * After every query, any field within the $enum map will be replaced by the respective value (example: a status
 * of 0 will be replaced with PENDING). This allows for easy readability for clients and easy usability,
 * flexibility and portability for developers.
 *
 * {{{
 *      class User extends AppModel {
 *          const PENDING = 0;
 *          const ACTIVE = 1;
 *          const INACTIVE = 2;
 *
 *          public $actsAs = array('Utility.Enumerable');
 *
 *          public $enum = array(
 *              'status' => array(
 *                  self::PENDING => 'PENDING',
 *                  self::ACTIVE => 'ACTIVE',
 *                  self::INACTIVE => 'INACTIVE'
 *              )
 *          );
 *      }
 *
 *      // Return the enum array for the status field
 *      $user->enum('status');
 *
 *      // Find all users by status
 *      $user->findByStatus(User::PENDING);
 * }}}
 */
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
     * @type array
     */
    public $persist = true;

    /**
     * Should we replace all enum fields with the respective mapped value.
     *
     * @type bool
     */
    public $format = self::APPEND;

    /**
     * Toggle the replacing of raw values with enum values when a record is being updated (checks Model::$id).
     *
     * @type bool
     */
    public $onUpdate = false;

    /**
     * The suffix to append to the persisted value.
     *
     * @type string
     */
    public $suffix = '_enum';

    /**
     * The enums for all models.
     *
     * @type array
     */
    protected $_enums = array();

    /**
     * Store the settings and Model::$enum.
     *
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
     * @param Model|string $model
     * @param string $key
     * @param mixed $value
     * @return mixed
     * @throws InvalidArgumentException
     * @throws OutOfBoundsException
     */
    public function enum($model, $key = null, $value = null) {
        $alias = is_string($model) ? $model : $model->alias;

        if (!isset($this->_enums[$alias])) {
            throw new InvalidArgumentException(sprintf('%s::$enum does not exist', $alias));
        }

        $enum = $this->_enums[$alias];

        if ($key) {
            if (!isset($enum[$key])) {
                throw new OutOfBoundsException(sprintf('Field %s does not exist within %s::$enum', $key, $model->alias));
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
     * Used for model validation to validate a value is within a certain field enum.
     *
     * @param Model $model
     * @param array $check
     * @param array $rule
     * @return bool
     */
    public function validateEnum(Model $model, $check, $rule) {
        $field = key($check);
        $value = $check[$field];

        if ($value === '' || $value === null || $value === false) {
            return (bool) $rule['allowEmpty'];
        }

        $enum = $this->enum($model, $field);

        return isset($enum[$value]);
    }

    /**
     * Format the results by replacing all enum fields with their respective value replacement.
     *
     * @param Model $model
     * @param array $results
     * @param bool $primary
     * @return mixed
     */
    public function afterFind(Model $model, $results, $primary = true) {
        $alias = $model->alias;

        if (!$this->format || ($model->id && !$this->onUpdate) || empty($this->_enums[$alias])) {
            return $results;
        }

        if ($results) {
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

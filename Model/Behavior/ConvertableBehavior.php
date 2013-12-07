<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

/**
 * A CakePHP Behavior that converts a field into a specific type before an insert or update,
 * and then converts the field back to its original type when the record is retrieved.
 *
 * The currently supported types are: serialize, json, html, base64, url, rawurl, utf8
 * However, you can define custom converters by creating methods in your model and
 * setting the engine to the method name.
 *
 * {{{
 *      class User extends AppModel {
 *          public $actsAs = array(
 *              'Utility.Convertable' => array(
 *                  'fieldOne' => 'base64',
 *                  'fieldTwo' => array(
 *                      'engine' => 'json',
 *                      'object' => true
 *                  )
 *              )
 *          );
 *      }
 * }}}
 */
class ConvertableBehavior extends ModelBehavior {

    /**
     * Conversion modes.
     */
    const TO = 0;
    const FROM = 1;

    /**
     * Default settings.
     *
     * @type array
     */
    protected $_defaults = array(
        'serialize' => array(
            'flatten' => false
        ),
        'json' => array(
            'object' => false,
            'flatten' => false
        ),
        'html' => array(
            'decode' => false,
            'encoding' => 'UTF-8',
            'flags' => ENT_QUOTES
        ),
        'base64' => array(),
        'url' => array(),
        'rawurl' => array(),
        'utf8' => array()
    );

    /**
     * Merge in the settings for each field.
     *
     * @param Model $model
     * @param array $settings
     * @throws InvalidArgumentException
     */
    public function setup(Model $model, $settings = array()) {
        if ($settings) {
            foreach ($settings as $field => $options) {
                if (is_string($options)) {
                    $options = array('engine' => $options);
                }

                if (!isset($options['engine'])) {
                    throw new InvalidArgumentException(sprintf('Engine option for %s has not been defined', $field));
                }

                $options = Hash::merge($this->_defaults[$options['engine']], $options);

                $this->settings[$model->alias][$field] = Hash::merge(array(
                    'encode' => true,
                    'decode' => true,
                    'flatten' => true
                ), $options);
            }
        }
    }

    /**
     * Run the converter before an insert or update query.
     *
     * @param Model $model
     * @param array $options
     * @return bool|mixed
     */
    public function beforeSave(Model $model, $options = array()) {
        $model->data = $this->convert($model, $model->data, self::TO);

        return true;
    }

    /**
     * Run the converter after a find query.
     *
     * @param Model $model
     * @param mixed $results
     * @param bool $primary
     * @return array|mixed
     */
    public function afterFind(Model $model, $results, $primary = true) {
        if ($results) {
            foreach ($results as $index => $result) {
                $results[$index] = $this->convert($model, $result, self::FROM);
            }
        }

        return $results;
    }

    /**
     * Loop through the data and run the correct converter engine on the associated field.
     *
     * @param Model $model
     * @param array $data
     * @param int $mode
     * @return mixed
     */
    public function convert(Model $model, $data, $mode) {
        if (empty($data[$model->alias])) {
            return $data;
        }

        foreach ($data[$model->alias] as $key => $value) {
            if (isset($this->settings[$model->alias][$key])) {
                $converter = $this->settings[$model->alias][$key];

                if (method_exists($model, $converter['engine'])) {
                    $function = array($model, $converter['engine']);
                } else if (method_exists($this, $converter['engine'])) {
                    $function = array($this, $converter['engine']);
                }

                if (isset($function)) {
                    if (is_array($value) && $converter['flatten']) {
                        $value = (string) $value;
                    }

                    $data[$model->alias][$key] = call_user_func_array($function, array($value, $converter, $mode));
                }
            }
        }

        return $data;
    }

    /**
     * Convert between serialized and unserialized arrays.
     *
     * @param string $value
     * @param array $options
     * @param int $mode
     * @return string
     */
    public function serialize($value, array $options, $mode) {
        if ($mode === self::TO && $options['encode']) {
            return serialize($value);
        }

        if ($mode === self::FROM && $options['decode']) {
            return @unserialize($value);
        }

        return $value;
    }

    /**
     * Convert between JSON and array types.
     *
     * @param string $value
     * @param array $options
     * @param int $mode
     * @return string
     */
    public function json($value, array $options, $mode) {
        if ($mode === self::TO && $options['encode']) {
            return json_encode($value);
        }

        if ($mode === self::FROM && $options['decode']) {
            return json_decode($value, !$options['object']);
        }

        return $value;
    }

    /**
     * Convert between HTML entities and raw value.
     *
     * @param string $value
     * @param array $options
     * @param int $mode
     * @return string
     */
    public function html($value, array $options, $mode) {
        if ($mode === self::TO && $options['encode']) {
            return htmlentities($value, $options['flags'], $options['encoding']);
        }

        if ($mode === self::FROM && $options['decode']) {
            return html_entity_decode($value, $options['flags'], $options['encoding']);
        }

        return $value;
    }

    /**
     * Convert between base64 encoding and raw value.
     *
     * @param string $value
     * @param array $options
     * @param int $mode
     * @return string
     */
    public function base64($value, array $options, $mode) {
        if ($mode === self::TO && $options['encode']) {
            return base64_encode($value);
        }

        if ($mode === self::FROM && $options['decode']) {
            return base64_decode($value);
        }

        return $value;
    }

    /**
     * Convert between url() encoding and raw value.
     *
     * @param string $value
     * @param array $options
     * @param int $mode
     * @return string
     */
    public function url($value, array $options, $mode) {
        if ($mode === self::TO && $options['encode']) {
            return urlencode($value);
        }

        if ($mode === self::FROM && $options['decode']) {
            return urldecode($value);
        }

        return $value;
    }

    /**
     * Convert between rawurl() encoding and raw value.
     *
     * @param string $value
     * @param array $options
     * @param int $mode
     * @return string
     */
    public function rawurl($value, array $options, $mode) {
        if ($mode === self::TO && $options['encode']) {
            return rawurlencode($value);
        }

        if ($mode === self::FROM && $options['decode']) {
            return rawurldecode($value);
        }

        return $value;
    }

    /**
     * Convert between UTF-8 encoded and non-encoded strings.
     *
     * @param string $value
     * @param array $options
     * @param int $mode
     * @return string
     */
    public function utf8($value, array $options, $mode) {
        if ($mode === self::TO && $options['encode']) {
            return utf8_encode($value);
        }

        if ($mode === self::FROM && $options['decode']) {
            return utf8_decode($value);
        }

        return $value;
    }

}
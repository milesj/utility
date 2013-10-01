<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');
App::uses('Sanitize', 'Utility');

/**
 * A CakePHP Behavior that will run sanitization filters on specific fields during an insert or update.
 * The currently supported filters are: html escaping, sql escaping, strip tags, paranoid.
 *
 * {{{
 *      class Topic extends AppModel {
 *          public $actsAs = array(
 *              'Utility.Filterable' => array(
 *                  'fieldOne' => array(
 *                      'strip' => true
 *                  ),
 *                  'fieldTwo' => array(
 *                      'html' => array('flags' => ENT_QUOTES)
 *                  )
 *              )
 *          );
 *      }
 * }}}
 */
class FilterableBehavior extends ModelBehavior {

    /**
     * Default settings.
     *
     * @type array {
     *      @type array $html       Escapes HTML entities
     *      @type array $strip      Removes HTML tags
     *      @type array $paranoid   Removes any non-alphanumeric characters
     *      @type array $escape     Escapes SQL queries
     * }
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
     * @param Model $model
     * @param array $settings
     */
    public function setup(Model $model, $settings = array()) {
        foreach ($settings as $field => $options) {
            $filters = array();

            foreach ($options as $key => $value) {
                if (!isset($this->_defaults[$key])) {
                    continue;
                }

                if (is_array($value)) {
                    if (($key === 'strip' || $key === 'paranoid') && empty($value['allowed'])) {
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
     * @param Model $model
     * @param array $options
     * @return bool|mixed
     */
    public function beforeSave(Model $model, $options = array()) {
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
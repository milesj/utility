<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('AppHelper', 'View/Helper');

class UtilityHelper extends AppHelper {

    /**
     * Helpers.
     *
     * @type array
     */
    public $helpers = array('Html');

    /**
     * Cached data for the current request.
     *
     * @type array
     */
    protected $_cached = array();

    /**
     * Retrieve an enum list for a Models field and translate the values.
     *
     * @param string $model
     * @param string $field
     * @param mixed $value
     * @param string $domain
     * @return string|array
     */
    public function enum($model, $field, $value = null, $domain = null) {
        $enum = ClassRegistry::init($model)->enum($field);

        list($plugin, $model) = pluginSplit($model);

        // Set domain
        if (!$domain) {
            if ($plugin) {
                $domain = Inflector::underscore($plugin);
            } else {
                $domain = 'default';
            }
        }

        // Cache the translations
        $key = Inflector::underscore($model) . '.' . Inflector::underscore($field);
        $cache = $key . '.enum';

        if (isset($this->_cached[$cache])) {
            $enum = $this->_cached[$cache];

        } else {
            foreach ($enum as $k => &$v) {
                $message = __d($domain, $key . '.' . $k);

                // Only use message if a translation exists
                if ($message !== $key . '.' . $k) {
                    $v = $message;
                }
            }

            $this->_cached[$cache] = $enum;
        }

        // Filter down by value
        if ($value !== null) {
            return isset($enum[$value]) ? $enum[$value] : null;
        }

        return $enum;
    }

    /**
     * Render out a gravatar thumbnail based on an email.
     *
     * @param string $email
     * @param array $options
     * @param array $attributes
     * @return string
     */
    public function gravatar($email, array $options = array(), array $attributes = array()) {
        $options = $options + array(
            'default' => 'mm',
            'size' => 80,
            'rating' => 'g',
            'hash' => 'md5',
            'secure' => env('HTTPS')
        );

        $email = Security::hash(strtolower(trim($email)), $options['hash']);
        $query = array();

        if ($options['secure']) {
            $image = 'https://secure.gravatar.com/avatar/' . $email;
        } else {
            $image = 'http://www.gravatar.com/avatar/' . $email;
        }

        foreach (array('default' => 'd', 'size' => 's', 'rating' => 'r') as $key => $param) {
            $query[] = $param . '=' . urlencode($options[$key]);
        }

        $image .= '?' . implode('&amp;', $query);

        return $this->Html->image($image, $attributes);
    }

}
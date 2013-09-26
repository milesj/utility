<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('AppHelper', 'View/Helper');

/**
 * Generates meta tags for the OpenGraph protocol. Tags can also be generated when calling BreadcrumbHelper::add().
 */
class OpenGraphHelper extends AppHelper {

    /**
     * Helpers.
     *
     * @type array
     */
    public $helpers = array('Html');

    /**
     * Namespaces to add to the HTML tag.
     *
     * @type array
     */
    protected $_namespaces = array();

    /**
     * Tags to render.
     *
     * @type array
     */
    protected $_tags = array();

    /**
     * Set default tags.
     *
     * @param View $View
     * @param array $settings
     */
    public function __construct(View $View, $settings = array()) {
        parent::__construct($View, $settings);

        $this->type('website');
        $this->uri(null);
        $this->ns('og', 'http://ogp.me/ns#');
        $this->ns('fb', 'http://ogp.me/ns/fb#');
    }

    /**
     * Render an HTML tag with OG namespaces.
     *
     * @param array $options
     * @param array $ns
     * @return string
     */
    public function html(array $options = array(), array $ns = array()) {
        if ($ns) {
            foreach ($ns as $key => $url) {
                $this->ns($key, $url);
            }
        }

        return $this->Html->tag('html', null, $this->_namespaces + $options);
    }

    /**
     * App ID.
     *
     * @param int $id
     * @return OpenGraphHelper
     */
    public function appId($id) {
        $this->tag('fb:app_id', $id);

        return $this;
    }

    /**
     * Admins.
     *
     * @param int|array $id
     * @return OpenGraphHelper
     */
    public function admins($id) {
        if (is_array($id)) {
            $id = implode(',', $id);
        }

        $this->tag('fb:admins', $id);

        return $this;
    }

    /**
     * Site name.
     *
     * @param string $value
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function name($value, $ns = 'og') {
        $this->tag($ns . ':site_name', $value);

        return $this;
    }

    /**
     * Title.
     *
     * @param string $value
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function title($value, $ns = 'og') {
        $this->tag($ns . ':title', $value);

        return $this;
    }

    /**
     * Type.
     *
     * @param string $value
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function type($value, $ns = 'og') {
        $this->tag($ns . ':type', $value);

        return $this;
    }

    /**
     * URL.
     *
     * @param string $value
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function uri($value, $ns = 'og') {
        $this->tag($ns . ':url', Router::url($value, true));

        return $this;
    }

    /**
     * Description.
     *
     * @param string $value
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function description($value, $ns = 'og') {
        $this->tag($ns . ':description', $value);

        return $this;
    }

    /**
     * Locale.
     *
     * @param string $value
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function locale($value, $ns = 'og') {
        $value = array_unique((array) $value);

        foreach ($value as &$v) {
            if (strpos($v, '-') !== false) {
                list($l, $r) = explode('-', $v);

                $v = strtolower($l) . '_' . strtoupper($r);
            }
        }

        $locale = array_shift($value);
        $options = array();

        if ($value) {
            $options['alternate'] = $value;
        }

        $this->tag($ns . ':locale', $locale, $options);

        return $this;
    }

    /**
     * Image.
     *
     * @param string $value
     * @param array $options
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function image($value, array $options = array(), $ns = 'og') {
        $this->tag($ns . ':image', $value, $options);

        return $this;
    }

    /**
     * Video.
     *
     * @param string $value
     * @param array $options
     * @param string $ns
     * @return OpenGraphHelper
     */
    public function video($value, array $options = array(), $ns = 'og') {
        $this->tag($ns . ':video', $value, $options);

        return $this;
    }

    /**
     * Output a tag.
     *
     * @param string $tag
     * @param string $value
     * @param array $options
     * @return OpenGraphHelper
     */
    public function tag($tag, $value, array $options = array()) {
        if ($options) {
            $this->_tags[$tag][] = array('value' => $value) + $options;
        } else {
            $this->_tags[$tag] = $value;
        }

        return $this;
    }

    /**
     * Add a namespace.
     *
     * @param string $key
     * @param string $url
     * @return OpenGraphHelper
     */
    public function ns($key, $url) {
        if (strpos($key, 'xmlns') !== 0) {
            $key = 'xmlns:' . $key;
        }

        $this->_namespaces[$key] = $url;

        return $this;
    }

    /**
     * Append the meta tags to the openGraph block.
     *
     * @return string
     */
    public function fetch() {
        if ($this->_tags) {
            foreach ($this->_tags as $tag => $values) {
                $options = array('block' => 'openGraph', 'inline' => false);

                if (is_array($values)) {
                    foreach ($values as $value) {
                        if (isset($value['value'])) {
                            $options['property'] = $tag;
                            $options['content'] = $value['value'];

                            $this->Html->meta(null, null, $options);

                            unset($value['value']);
                        }

                        foreach ($value as $key => $val) {
                            $options['property'] = $tag . ':' . $key;

                            if (is_array($val)) {
                                foreach ($val as $v) {
                                    $options['content'] = $v;

                                    $this->Html->meta(null, null, $options);
                                }
                            } else {
                                $options['content'] = $val;

                                $this->Html->meta(null, null, $options);
                            }
                        }
                    }
                } else {
                    $options['property'] = $tag;
                    $options['content'] = $values;

                    $this->Html->meta(null, null, $options);
                }
            }
        }

        return $this->_View->fetch('openGraph');
    }

    /**
     * Check to see if a field is set.
     *
     * @param string $key
     * @param string $ns
     * @return bool
     */
    public function has($key, $ns = 'og') {
        return isset($this->_tags[$ns . ':' . $key]);
    }

}
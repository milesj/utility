<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('AppHelper', 'View/Helper');

/**
 * A CakePHP Helper that provides basic functionality for generating breadcrumb lists.
 * Can be used to grab the first or last crumb, or generate a string of crumbs for use in page titles.
 * Will also couple with the OpenGraphHelper for extra convenience.
 */
class BreadcrumbHelper extends AppHelper {

    /**
     * Helpers.
     *
     * @type array
     */
    public $helpers = array('Utility.OpenGraph');

    /**
     * List of breadcrumbs.
     *
     * @type array
     */
    protected $_crumbs = array();

    /**
     * Add a breadcrumb to the list.
     *
     * @param string $title
     * @param string|array $url
     * @param array $options
     * @return BreadcrumbHelper
     */
    public function add($title, $url, array $options = array()) {
        $this->append($title, $url, $options);

        return $this;
    }

    /**
     * Add a breadcrumb to the end of the list.
     *
     * @param string $title
     * @param string|array $url
     * @param array $options
     * @return BreadcrumbHelper
     */
    public function append($title, $url, array $options = array()) {
        $this->_crumbs[] = array(
            'title' => strip_tags($title),
            'url' => $url,
            'options' => $options
        );

        $this->OpenGraph->title($this->pageTitle(null, array('reverse' => true)));
        $this->OpenGraph->uri($url);

        return $this;
    }

    /**
     * Add a breadcrumb to the beginning of the list.
     *
     * @param string $title
     * @param string|array $url
     * @param array $options
     * @return BreadcrumbHelper
     */
    public function prepend($title, $url, array $options = array()) {
        array_unshift($this->_crumbs, array(
            'title' => strip_tags($title),
            'url' => $url,
            'options' => $options
        ));

        $this->OpenGraph->title($this->pageTitle(null, array('reverse' => true)));
        $this->OpenGraph->uri($url);

        return $this;
    }

    /**
     * Return the list of breadcrumbs.
     *
     * @param string $key
     * @return array
     */
    public function get($key = '') {
        if (!$key) {
            return $this->_crumbs;
        }

        $crumbs = array();

        foreach ($this->_crumbs as $crumb) {
            $crumbs[] = isset($crumb[$key]) ? $crumb[$key] : null;
        }

        return $crumbs;
    }

    /**
     * Return the first crumb in the list.
     *
     * @param string $key
     * @return mixed
     */
    public function first($key = '') {
        $crumbs = $this->get($key);

        if (!$crumbs) {
            return null;
        }

        $first = array_slice($crumbs, 0, 1);

        return $first[0];
    }

    /**
     * Return the last crumb in the list.
     *
     * @param string $key
     * @return mixed
     */
    public function last($key = '') {
        $crumbs = $this->get($key);

        if (!$crumbs) {
            return null;
        }

        $last = array_slice($crumbs, -1);

        return $last[0];
    }

    /**
     * Generate a page title based off the current crumbs.
     *
     * @param string $base
     * @param array $options
     * @return string
     */
    public function pageTitle($base = '', array $options = array()) {
        $options = $options + array(
            'reverse' => false,
            'depth' => 3,
            'separator' => ' - '
        );

        $crumbs = $this->get('title');
        $count = count($crumbs);
        $title = array();

        if ($count) {
            if ($options['depth'] && $count > $options['depth']) {
                $title = array_slice($crumbs, -$options['depth']);
                array_unshift($title, array_shift($crumbs));

            } else {
                $title = $crumbs;
            }

        } else if ($pageTitle = $this->_View->get('title_for_layout')) {
            $title[] = $pageTitle;
        }

        if ($options['reverse']) {
            $title = array_reverse($title);
        }

        if ($base) {
            array_unshift($title, $base);
        }

        return implode($options['separator'], array_unique($title));
    }

}
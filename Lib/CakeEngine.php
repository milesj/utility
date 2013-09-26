<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('View', 'View');

use Decoda\Engine\AbstractEngine;

/**
 * Renders Decoda templates using CakePHP's View engine.
 */
class CakeEngine extends AbstractEngine {

    /**
     * CakePHP View engine.
     *
     * @type View
     */
    protected $_view;

    /**
     * Initialize View and helpers.
     *
     * @param array $helpers
     */
    public function __construct(array $helpers) {
        $view = new View();
        $view->helpers = $helpers;
        $view->layout = null;
        $view->autoLayout = false;
        $view->name = 'Decoda';
        $view->viewPath = 'Decoda';

        $this->_view = $view;
    }

    /**
     * Renders the tag by using Cake views.
     *
     * @param array $tag
     * @param string $content
     * @return string
     * @throws \Exception
     */
    public function render(array $tag, $content) {
        $setup = $this->getFilter()->getTag($tag['tag']);

        $vars = $tag['attributes'];
        $vars['filter'] = $this->getFilter();
        $vars['content'] = $content;

        $this->_view->set($vars);

        // Try outside of the plugin first in-case they use their own templates
        try {
            $response = $this->_view->render($setup['template']);

        // Else fallback to the plugin templates
        } catch (Exception $e) {
            $this->_view->plugin = 'Utility';

            $response = $this->_view->render($setup['template']);
        }

        $this->_view->hasRendered = false;
        $this->_view->plugin = null;

        return $response;
    }

}
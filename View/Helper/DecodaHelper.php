<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('AppHelper', 'View/Helper');
App::uses('CakeEngine', 'Utility.Lib');

/**
 * A lightweight lexical string parser for simple markup syntax, ported to CakePHP.
 * Provides a very powerful filter and hook system to extend the parsing cycle.
 */
class DecodaHelper extends AppHelper {

    /**
     * Helpers.
     *
     * @type array
     */
    public $helpers = array('Html');

    /**
     * Decoda instance.
     *
     * @type \Decoda\Decoda
     */
    protected $_decoda;

    /**
     * Instantiate the class and apply settings.
     *
     * @param View $view
     * @param array $settings
     */
    public function __construct(View $view, $settings = array()) {
        parent::__construct($view, $settings);

        $settings = $settings + Configure::read('Decoda.config');

        $locale = Configure::read('Config.language') ?: $settings['locale'];
        $localeMap = Configure::read('Decoda.locales');

        unset($settings['locale']);

        $decoda = new \Decoda\Decoda('', $settings);
        $decoda
            ->whitelist($settings['whitelist'])
            ->blacklist($settings['blacklist']);

        if ($paths = $settings['paths']) {
            foreach ((array) $paths as $path) {
                $decoda->addPath($path);
            }
        }

        if ($messages = $settings['messages']) {
            $decoda->addMessages(new \Decoda\Loader\DataLoader($messages));
        }

        // Set locale
        if (isset($localeMap[$locale])) {
            $decoda->setLocale($localeMap[$locale]);

        } else if (in_array($locale, $localeMap)) {
            $decoda->setLocale($locale);
        }

        // Apply hooks and filters
        if (empty($settings['filters']) && empty($settings['hooks'])) {
            $decoda->defaults();

        } else {
            if ($filters = $settings['filters']) {
                foreach ((array) $filters as $filter) {
                    $filter = sprintf('\Decoda\Filter\%sFilter', $filter);
                    $decoda->addFilter(new $filter());
                }
            }

            if ($hooks = $settings['hooks']) {
                foreach ((array) $hooks as $hook) {
                    $hook = sprintf('\Decoda\Hook\%sHook', $hook);
                    $decoda->addHook(new $hook());
                }
            }
        }

        // Custom config
        $decoda->addHook( new \Decoda\Hook\EmoticonHook(array('path' => '/utility/img/emoticon/')) );
        $decoda->setEngine( new CakeEngine($settings['helpers']) );

        $this->_decoda = $decoda;
    }

    /**
     * Execute setupDecoda() if it exists. This allows for custom filters and hooks to be applied.
     *
     * @param string $viewFile
     */
    public function beforeRender($viewFile) {
        if (method_exists($this, 'setupDecoda')) {
            $this->setupDecoda($this->getDecoda());
        }
    }

    /**
     * Return the Decoda instance.
     *
     * @return \Decoda\Decoda
     */
    public function getDecoda() {
        return $this->_decoda;
    }

    /**
     * Reset the Decoda instance, apply any whitelisted tags and executes the parsing process.
     *
     * @param string $string
     * @param array $whitelist
     * @param bool $wrap
     * @return string
     */
    public function parse($string, array $whitelist = array(), $wrap = true) {
        $parsed = $this->getDecoda()->reset($string)->whitelist($whitelist)->parse();

        if ($wrap) {
            return $this->Html->div('decoda', $parsed);
        }

        return $parsed;
    }

    /**
     * Reset the Decoda instance and strip out any Decoda tags and HTML.
     *
     * @param string $string
     * @param bool $html
     * @return string
     */
    public function strip($string, $html = false) {
        return $this->getDecoda()->reset($string)->strip($html);
    }

}
<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

Router::connect('/sitemap.xml', array('plugin' => 'utility', 'controller' => 'sitemap', 'action' => 'index', 'ext' => 'xml'));
Router::connect('/sitemap.json', array('plugin' => 'utility', 'controller' => 'sitemap', 'action' => 'index', 'ext' => 'json'));
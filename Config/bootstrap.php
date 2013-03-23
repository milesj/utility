<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

/**
 * Default Decoda configuration.
 */
Configure::write('Decoda.config', array(
	'open' => '[',
	'close' => ']',
	'locale' => 'en-us',
	'disabled' => false,
	'shorthandLinks' => false,
	'xhtmlOutput' => false,
	'escapeHtml' => true,
	'strictMode' => true,
	'maxNewlines' => 3,
	'paths' => array(),
	'whitelist' => array(),
	'blacklist' => array(),
	'helpers' => array('Time', 'Html', 'Text'),
	'filters' => array(),
	'hooks' => array()
));

/**
 * List of Cake locales to Decoda locales.
 */
Configure::write('Decoda.locales', array(
	'eng' => 'en-us',
	'spa' => 'es-mx',
	'swe' => 'sv-se',
	'deu' => 'de-de',
	'fre' => 'fr-fr',
	'rus' => 'ru-ru',
	'ind' => 'id-id',
	'bul' => 'bg-bg'
));
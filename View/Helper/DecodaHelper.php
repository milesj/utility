<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
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
	 * @access public
	 * @var array
	 */
	public $helpers = array('Html');

	/**
	 * Decoda instance.
	 *
	 * @access protected
	 * @var \Decoda\Decoda
	 */
	protected $_decoda;

	/**
	 * Instantiate the class and apply settings.
	 *
	 * @access public
	 * @param View $view
	 * @param array $settings
	 */
	public function __construct(View $view, $settings = array()) {
		parent::__construct($view, $settings);

		$settings = $settings + array(
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
			'helpers' => array('Time', 'Html', 'Text')
		);

		$locale = Configure::read('Config.language') ?: $settings['locale'];
		$localeMap = array(
			'eng' => 'en-us',
			'esp' => 'es-mx',
			'fre' => 'fr-fr',
			'ita' => 'it-it',
			'deu' => 'de-de',
			'swe' => 'sv-se',
			'gre' => 'el-gr',
			'bul' => 'bg-bg',
			'rus' => 'ru-ru',
			'chi' => 'zh-cn',
			'jpn' => 'ja-jp',
			'kor' => 'ko-kr',
			'ind' => 'id-id'
		);

		unset($settings['locale']);

		$this->_decoda = new \Decoda\Decoda('', $settings);
		$this->_decoda
			->whitelist($settings['whitelist'])
			->blacklist($settings['blacklist'])
			->defaults();

		if (isset($localeMap[$locale])) {
			$this->_decoda->setLocale($localeMap[$locale]);

		} else if (in_array($locale, $localeMap)) {
			$this->_decoda->setLocale($locale);
		}

		if ($settings['paths']) {
			foreach ((array) $settings['paths'] as $path) {
				$this->_decoda->addPath($path);
			}
		}

		// Custom config
		$this->_decoda->addHook( new \Decoda\Hook\EmoticonHook(array('path' => '/utility/img/emoticon/')) );
		$this->_decoda->setEngine( new CakeEngine($settings['helpers']) );
	}

	/**
	 * Execute setupDecoda() if it exists. This allows for custom filters and hooks to be applied.
	 *
	 * @access public
	 * @param string $viewFile
	 * @return void
	 */
	public function beforeRender($viewFile) {
		if (method_exists($this, 'setupDecoda')) {
			$this->setupDecoda($this->_decoda);
		}
	}

	/**
	 * Reset the Decoda instance, apply any whitelisted tags and executes the parsing process.
	 *
	 * @access public
	 * @param string $string
	 * @param array $whitelist
	 * @param boolean $disable
	 * @return string
	 */
	public function parse($string, array $whitelist = array(), $disable = false) {
		$this->_decoda->reset($string)->disable($disable)->whitelist($whitelist);

		return $this->Html->div('decoda', $this->_decoda->parse());
	}

	/**
	 * Reset the Decoda instance and strip out any Decoda tags and HTML.
	 *
	 * @access public
	 * @param string $string
	 * @param boolean $html
	 * @return string
	 */
	public function strip($string, $html = false) {
		$this->_decoda->reset($string);

		return $this->_decoda->strip($html);
	}

}
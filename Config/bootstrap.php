<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
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
    'lineBreaks' => true,
    'removeEmpty' => false,
    'paths' => array(),
    'whitelist' => array(),
    'blacklist' => array(),
    'helpers' => array('Time', 'Html', 'Text'),
    'filters' => array(),
    'hooks' => array(),
    'messages' => array()
));

/**
 * List of Cake locales to Decoda locales.
 */
Configure::write('Decoda.locales', array(
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
));

/**
 * List of all timezones.
 */
Configure::write('Utility.timezones', array(
    '-12'   => '(GMT -12:00) International Date Line West',
    '-11'   => '(GMT -11:00) Midway Island',
    '-10'   => '(GMT -10:00) Hawaii',
    '-9'    => '(GMT -9:00) Alaska',
    '-8'    => '(GMT -8:00) Pacific Time',
    '-7'    => '(GMT -7:00) Mountain Time',
    '-6'    => '(GMT -6:00) Central Time',
    '-5'    => '(GMT -5:00) Eastern Time',
    '-4'    => '(GMT -4:00) Atlantic Time',
    '-3'    => '(GMT -3:00) Greenland',
    '-2'    => '(GMT -2:00) Brazil, Mid-Atlantic',
    '-1'    => '(GMT -1:00) Portugal',
    '0'     => '(GMT +0:00) Greenwich Mean Time',
    '+1'    => '(GMT +1:00) Germany, Italy, Spain',
    '+2'    => '(GMT +2:00) Greece, Israel, Turkey, Zambia',
    '+3'    => '(GMT +3:00) Iraq, Kenya, Russia (Moscow)',
    '+4'    => '(GMT +4:00) Azerbaijan, Afghanistan, Russia (Izhevsk)',
    '+5'    => '(GMT +5:00) Pakistan, Uzbekistan',
    '+5.5'  => '(GMT +5:30) India, Sri Lanka',
    '+6'    => '(GMT +6:00) Bangladesh, Bhutan',
    '+6.5'  => '(GMT +6:30) Burma, Cocos',
    '+7'    => '(GMT +7:00) Thailand, Vietnam',
    '+8'    => '(GMT +8:00) China, Malaysia, Taiwan, Australia',
    '+9'    => '(GMT +9:00) Japan, Korea, Indonesia',
    '+9.5'  => '(GMT +9:30) Australia',
    '+10'   => '(GMT +10:00) Australia, Guam, Micronesia',
    '+11'   => '(GMT +11:00) Solomon Islands, Vanuatu',
    '+12'   => '(GMT +12:00) New Zealand, Fiji, Nauru',
    '+13'   => '(GMT +13:00) Tonga'
));
<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('AppHelper', 'View/Helper');

class UtilityHelper extends AppHelper {

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

		// Translate the enum
		$key = Inflector::underscore($model) . '.' . Inflector::underscore($field);

		foreach ($enum as $k => &$v) {
			$message = __d($domain, $key . '.' . $k);

			// Only use message if a translation exists
			if ($message !== $key . '.' . $k) {
				$v = $message;
			}

			if ($value !== null && $value == $k) {
				return $v;
			}
		}

		// Invalid value used
		if ($value) {
			return null;
		}

		return $enum;
	}

}
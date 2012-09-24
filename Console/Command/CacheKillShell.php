<?php
/**
 * CacheKillShell
 *
 * Deletes a single item or all items within a cache configuration.
 *
 * @version		2.0
 * @author		Miles Johnson - http://milesj.me
 * @copyright	Copyright 2006+, Miles Johnson, Inc.
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under The MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('Shell', 'Console');

// Make sure we notice errors while in console
Configure::write('debug', 2);

class CacheKillShell extends Shell {

	/**
	 * Gather params and clear cache.
	 */
	public function main() {
		$this->out('Shell: CacheKill v2.0');
		$this->out('About: Deletes cached entries within a configuration');
		$this->hr(1);

		$key = isset($this->params['key']) ? $this->params['key'] : null;
		$config = isset($this->params['config']) ? $this->params['config'] : 'default';
		$check = isset($this->params['check']) ? (bool) $this->params['check'] : false;

		if ($key) {
			$this->out(sprintf('Killing %s in %s...', $key, $config));
			Cache::delete($key, $config);

		} else {
			$this->out(sprintf('Killing all in %s...', $config));
			Cache::clear($check, $config);
		}

		$this->hr(1);
		$this->out('Cache murdered!');
	}

}
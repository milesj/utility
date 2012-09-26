<?php
/**
 * CacheKillShell
 *
 * Deletes a single item or all items within a cache configuration.
 *
 * {{{
 * 		Command line usage:
 *
 *		cake Utility.cache_kill
 * }}}
 *
 * @version		2.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('Shell', 'Console');

// Make sure we notice errors while in console
Configure::write('debug', 2);

class CacheKillShell extends Shell {

	/**
	 * Gather params and clear cache.
	 *
	 * @access public
	 * @return void
	 */
	public function main() {
		$this->out('Shell: CacheKill v2.0.0');
		$this->out('About: Deletes cached entries within a configuration');
		$this->hr(1);

		$key = isset($this->params['key']) ? $this->params['key'] : null;
		$check = isset($this->params['check']) ? (bool) $this->params['check'] : false;
		$config = isset($this->params['config']) ? $this->params['config'] : 'default';

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
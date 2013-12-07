<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('Shell', 'Console');

// Make sure we notice errors while in console
Configure::write('debug', 2);

/**
 * Deletes a single item or all items within a cache configuration.
 */
class CacheKillShell extends Shell {

    /**
     * Display help info.
     */
    public function main() {
        $this->out($this->OptionParser->help());
    }

    /**
     * Delete APC cache.
     */
    public function apc() {
        if (!extension_loaded('apc')) {
            $this->err('<error>APC extension not loaded</error>');
            return;
        }

        apc_clear_cache();
        apc_clear_cache('user');
        apc_clear_cache('opcode');

        $this->out('<info>APC cache cleared!</info>');
    }

    /**
     * Delete CakePHP cache.
     */
    public function core() {
        $key = isset($this->params['key']) ? $this->params['key'] : null;
        $config = isset($this->params['config']) ? $this->params['config'] : 'default';

        if ($key) {
            $this->out(sprintf('Clearing %s in %s...', $key, $config));
            Cache::delete($key, $config);

        } else {
            $this->out(sprintf('Clearing all in %s...', $config));
            Cache::clear(false, $config);
        }

        $this->out('<info>Cache cleared!</info>');
    }

    /**
     * Add sub-commands.
     *
     * @return ConsoleOptionParser
     */
    public function getOptionParser() {
        $parser = parent::getOptionParser();

        $parser->addSubcommand('core', array(
            'help' => 'Delete all cache within CakePHP',
            'parser' => array(
                'description' => 'This command will clear all cache in CakePHP using the Cache engine settings.',
                'options' => array(
                    'config' => array('short' => 'c', 'help' => 'Cache Config', 'default' => 'default'),
                    'key' => array('short' => 'k', 'help' => 'Cache Key', 'default' => '')
                )
            )
        ));

        $parser->addSubcommand('apc', array(
            'help' => 'Delete all cache within APC',
            'parser' => array(
                'description' => 'This command will clear all cache in APC, including user, system and opcode caches.'
            )
        ));

        return $parser;
    }

}
<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('DataSource', 'Model/Datasource');

/**
 * A Model DataSource that does nothing and is used to trick the model layer for specific functionality.
 * Is used by the CacheableBehavior.
 *
 * {{{
 *        public $shim = array('datasource' => 'Utility.ShimSource');
 * }}}
 */
class ShimSource extends DataSource {

    /**
     * Return the Model schema.
     *
     * @param Model|string $model
     * @return array
     */
    public function describe($model) {
        return $model->schema();
    }

    /**
     * Return $data else the query will fail.
     *
     * @param mixed $data
     * @return array|null
     */
    public function listSources($data = null) {
        return $data;
    }

    /**
     * {@inheritdoc}
     */
    public function read(Model $model, $queryData = array(), $recursive = null) {
        return array();
    }

}
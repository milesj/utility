<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

/**
 * A CakePHP behavior that will generate a slug based off of another field before an insert or update query.
 *
 * {{{
 *        class Topic extends AppModel {
 *            public $actsAs = array(
 *                'Utility.Sluggable' => array(
 *                    'field' => 'title',
 *                    'length' => 100
 *                 )
 *            );
 *         }
 * }}}
 */
class SluggableBehavior extends ModelBehavior {

    /**
     * Default settings.
     *
     * @type array {
     *      @type string $field     The column to base the slug on
     *      @type string $slug      The column to write the slug to
     *      @type array $scope      Additional query conditions when finding duplicates
     *      @type string $separator The separating character between words
     *      @type int $length       The max length of a slug
     *      @type bool $onUpdate    Will update the slug when a record is updated
     *      @type bool $unique      Whether to make the slug unique or not
     * }
     */
    protected $_defaults = array(
        'field' => 'title',
        'slug' => 'slug',
        'scope' => array(),
        'separator' => '-',
        'length' => 255,
        'onUpdate' => true,
        'unique' => true
    );

    /**
     * Merge settings.
     *
     * @param Model $model
     * @param array $settings
     */
    public function setup(Model $model, $settings = array()) {
        $this->settings[$model->alias] = array_merge($this->_defaults, $settings);
    }

    /**
     * Generate a slug based on another field.
     *
     * @param Model $model
     * @param array $options
     * @return bool
     */
    public function beforeSave(Model $model, $options = array()) {
        $settings = $this->settings[$model->alias];

        if (empty($model->data[$model->alias]) ||
            empty($model->data[$model->alias][$settings['field']]) ||
            !empty($model->data[$model->alias][$settings['slug']])) {
            return true;

        } else if ($model->id && !$settings['onUpdate']) {
            return true;
        }

        $slug = $model->data[$model->alias][$settings['field']];

        if (method_exists($model, 'beforeSlug')) {
            $slug = $model->beforeSlug($slug, $this);
        }

        $slug = $this->slugify($model, $slug);

        if (method_exists($model, 'afterSlug')) {
            $slug = $model->afterSlug($slug, $this);
        }

        if (mb_strlen($slug) > ($settings['length'] - 3)) {
            $slug = mb_substr($slug, 0, ($settings['length'] - 3));
        }

        if ($settings['unique']) {
            $slug = $this->_makeUnique($model, $slug);
        }

        $model->data[$model->alias][$settings['slug']] = $slug;

        return true;
    }

    /**
     * Return a slugged version of a string.
     *
     * @param Model $model
     * @param string $string
     * @return string
     */
    public function slugify(Model $model, $string) {
        $string = strip_tags($string);
        $string = str_replace('&amp;', 'and', $string);
        $string = str_replace('&', 'and', $string);
        $string = str_replace('@', 'at', $string);

        return mb_strtolower(Inflector::slug($string, $this->settings[$model->alias]['separator']));
    }

    /**
     * Helper function to check if a slug exists.
     *
     * @param Model $model
     * @param string $slug
     * @return bool
     */
    public function slugExists(Model $model, $slug) {
        return (bool) $model->find('count', array(
            'conditions' => array($this->settings[$model->alias]['slug'] => $slug),
            'recursive' => -1,
            'contain' => false
        ));
    }

    /**
     * Validate the slug is unique by querying for other slugs.
     *
     * @param Model $model
     * @param string $string
     * @return string
     */
    protected function _makeUnique(Model $model, $string) {
        $settings = $this->settings[$model->alias];
        $conditions = array(
            array($settings['slug'] => $string),
            array($settings['slug'] . ' LIKE' => $string . $settings['separator'] . '%')
        );

        foreach ($conditions as $i => $where) {
            $where = $where + $settings['scope'];

            if ($model->id) {
                $where[$model->primaryKey . ' !='] = $model->id;
            }

            $count = $model->find('count', array(
                'conditions' => $where,
                'recursive' => -1,
                'contain' => false
            ));

            if ($i == 0) {
                if ($count == 0) {
                    return $string;
                } else {
                    continue;
                }
            }

            $string .= $settings['separator'] . $count;
        }

        return $string;
    }

}

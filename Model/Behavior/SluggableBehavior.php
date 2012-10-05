<?php
/**
 * SluggableBehavior
 *
 * A CakePHP behavior that will generate a slug based off of another field before an insert or update query.
 *
 * {{{
 *		class Topic extends AppModel {
 *			public $actsAs = array(
 *				'Utility.Sluggable' => array(
 *					'field' => 'title',
 *					'length' => 100
 * 				)
 *			);
 * 		}
 * }}}
 *
 * @version		1.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('ModelBehavior', 'Model');

class SluggableBehavior extends ModelBehavior {

	/**
	 * Default settings.
	 *
	 * 	field		- The column to base the slug on
	 * 	slug		- The column to write the slug to
	 * 	scope		- Additional query conditions when finding duplicates
	 * 	separator	- The separating character between words
	 * 	length		- The max length of a slug
	 * 	onUpdate	- Will update the slug when a record is updated
	 *
	 * @access protected
	 * @var array
	 */
	protected $_defaults = array(
		'field' => 'title',
		'slug' => 'slug',
		'scope' => array(),
		'separator' => '-',
		'length' => 255,
		'onUpdate' => true
	);

	/**
	 * Merge settings.
	 *
	 * @access public
	 * @param Model $model
	 * @param array $settings
	 */
	public function setup(Model $model, $settings = array()) {
		$this->settings[$model->alias] = array_merge($this->_defaults, $settings);
	}

	/**
	 * Generate a slug based on another field.
	 *
	 * @access public
	 * @param Model $model
	 * @return boolean
	 */
	public function beforeSave(Model $model) {
		$settings = $this->settings[$model->alias];

		if (empty($model->data[$model->alias]) || empty($model->data[$model->alias][$settings['field']])) {
			return true;

		} else if ($model->id && !$settings['onUpdate']) {
			return true;
		}

		$slug = $model->data[$model->alias][$settings['field']];

		if (method_exists($model, 'beforeSlug')) {
			$slug = $model->beforeSlug($slug);
		}

		$slug = $this->slugify($model, $slug);

		if (method_exists($model, 'afterSlug')) {
			$slug = $model->afterSlug($slug);
		}

		if (mb_strlen($slug) > ($settings['length'] - 5)) {
			$slug = substr($slug, 0, ($settings['length'] - 5));
		}

		$model->data[$model->alias][$settings['slug']] = $this->_makeUnique($model, $slug);

		return true;
	}

	/**
	 * Return a slugged version of a string.
	 *
	 * @access public
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
	 * Validate the slug is unique by querying for other slugs.
	 *
	 * @access protected
	 * @param Model $model
	 * @param string $string
	 * @return string
	 */
	protected function _makeUnique(Model $model, $string) {
		$settings = $this->settings[$model->alias];
		$conditions = array($settings['slug'] . ' LIKE' => '%' . $string) + $settings['scope'];

		if ($model->id) {
			$conditions[$model->primaryKey . ' !='] = $model->id;
		}

		$count = $model->find('count', array(
			'conditions' => $conditions,
			'recursive' => -1,
			'contain' => false
		));

		if ($count) {
			$string .= $settings['separator'] . $count;
		}

		return $string;
	}

}
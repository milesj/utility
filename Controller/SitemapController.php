<?php
/**
 * @copyright	Copyright 2006-2013, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('Controller', 'Controller');
App::uses('CakeTime', 'Utility');

/**
 * Handles sitemap generation for search engines.
 */
class SitemapController extends Controller {

	/**
	 * Components.
	 *
	 * @var array
	 */
	public $components = array('RequestHandler');

	/**
	 * Loop through active controllers and generate sitemap data.
	 */
	public function index() {
		$controllers = App::objects('Controller');
		$sitemap = array();

		// Fetch sitemap data
		foreach ($controllers as $controller) {
			App::uses($controller, 'Controller');

			// Don't load AppController's, SitemapController or Controller's who can't be found
			if (strpos($controller, 'AppController') !== false || $controller === 'SitemapController' || !App::load($controller)) {
				continue;
			}

			$instance = new $controller($this->request, $this->response);
			$instance->constructClasses();

			if (method_exists($instance, '_generateSitemap')) {
				if ($data = $instance->_generateSitemap()) {
					$sitemap = array_merge($sitemap, $data);
				}
			}
		}

		// Cleanup sitemap
		if ($sitemap) {
			foreach ($sitemap as &$item) {
				if (is_array($item['loc'])) {
					if (!isset($item['loc']['plugin'])) {
						$item['loc']['plugin'] = false;
					}

					$item['loc'] = h(Router::url($item['loc'], true));
				}

				if (array_key_exists('lastmod', $item)) {
					if (!$item['lastmod']) {
						unset($item['lastmod']);
					} else {
						$item['lastmod'] = CakeTime::format(DateTime::W3C, $item['lastmod']);
					}
				}
			}
		}

		// Render view and don't use specific view engines
		$this->RequestHandler->respondAs($this->request->params['ext']);

		$this->set('sitemap', $sitemap);
	}

}
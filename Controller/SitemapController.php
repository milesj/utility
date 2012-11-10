<?php
/**
 * SitemapController
 *
 * Handles sitemap generation for search engines.
 *
 * @version		1.0.0
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('CakeTime', 'Utility');

class SitemapController extends Controller {

	/**
	 * Components.
	 *
	 * @access public
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

			// Don't load AppController's or Controller's who can't be found
			if (strpos($controller, 'AppController') !== false || !App::load($controller)) {
				continue;
			}

			$instance = new $controller($this->request, $this->response);

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

				if (isset($item['lastmod'])) {
					$item['lastmod'] = CakeTime::format('Y-m-d', $item['lastmod']);
				}

				if (isset($item['title'])) {
					$item['title'] = h($item['title']);
				}
			}
		}

		// Render view and don't use specific view engines
		$this->RequestHandler->respondAs($this->request->params['ext']);

		$this->set('sitemap', $sitemap);
	}

}
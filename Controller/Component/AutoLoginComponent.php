<?php
/**
 * AutoLoginComponent
 *
 * A CakePHP Component that will automatically login the Auth session for a duration if the user requested to (saves data to cookies).
 *
 * @version		3.6.1
 * @copyright	Copyright 2006-2012, Miles Johnson - http://milesj.me
 * @license		http://opensource.org/licenses/mit-license.php - Licensed under the MIT License
 * @link		http://milesj.me/code/cakephp/utility
 */

App::uses('Component', 'Controller');

class AutoLoginComponent extends Component {

	/**
	 * Components.
	 *
	 * @access public
	 * @var array
	 */
	public $components = array('Auth', 'Cookie');

	/**
	 * Name of the user model.
	 *
	 * @access public
	 * @var string
	 */
	public $model = 'User';

	/**
	 * Field name for login username.
	 *
	 * @access public
	 * @var string
	 */
	public $username = 'username';

	/**
	 * Field name for login password.
	 *
	 * @access public
	 * @var string
	 */
	public $password = 'password';

	/**
	 * Plugin name if component is placed within a plugin.
	 *
	 * @access public
	 * @var string
	 */
	public $plugin = '';

	/**
	 * Users login/logout controller.
	 *
	 * @access public
	 * @var string
	 */
	public $controller = 'users';

	/**
	 * Users login action.
	 *
	 * @access public
	 * @var string
	 */
	public $loginAction = 'login';

	/**
	 * Users logout controller.
	 *
	 * @access public
	 * @var string
	 */
	public $logoutAction = 'logout';

	/**
	 * Name of the auto login cookie.
	 *
	 * @access public
	 * @var string
	 */
	public $cookieName = 'autoLogin';

	/**
	 * Duration in cookie length, using strtotime() format.
	 *
	 * @access public
	 * @var string
	 */
	public $expires = '+2 weeks';

	/**
	 * Domain used on a local environment (localhost).
	 *
	 * @access public
	 * @var boolean
	 */
	public $cookieLocalDomain = false;

	/**
	 * Force a redirect after successful auto login.
	 *
	 * @access public
	 * @var boolean
	 */
	public $redirect = true;

	/**
	 * If true, will require a checkbox value in the login form data.
	 *
	 * @access public
	 * @var boolean
	 */
	public $requirePrompt = true;

	/**
	 * Force the process to continue or exit.
	 *
	 * @access public
	 * @var boolean
	 */
	public $active = true;

	/**
	 * Should we debug?
	 *
	 * @access protected
	 * @var boolean
	 */
	protected $_debug = false;

	/**
	 * Initialize settings and debug.
	 *
	 * @access public
	 * @param Controller $controller
	 * @return void
	 */
	public function initialize(Controller $controller) {
		$autoLogin = (array) Configure::read('AutoLogin');

		// Is debug enabled?
		$this->_debug = (!empty($autoLogin['ips']) && in_array(env('REMOTE_ADDR'), (array) $autoLogin['ips']));
	}

	/**
	 * Automatically login existent Auth session; called after controllers beforeFilter() so that Auth is initialized.
	 *
	 * @access public
	 * @param Controller $controller
	 * @return void
	 */
	public function startup(Controller $controller) {
		// Backwards support
		if (isset($this->settings)) {
			$this->_set($this->settings);
		}

		// Detect cookie or login
		$cookie = $this->read();
		$user = $this->Auth->user();

		if (!$this->active || !empty($user) || !$controller->request->is('get')) {
			return;

		} else if ($cookie === null) {
			$this->debug('cookieFail', $this->Cookie, $user);
			$this->delete();
			return;

		} else if (empty($cookie['hash']) || $cookie['hash'] !== $this->Auth->password($cookie['username'] . $cookie['time'])) {
			$this->debug('hashFail', $this->Cookie, $user);
			$this->delete();
			return;
		}

		// Set the data to identify with
		$controller->request->data[$this->model][$this->username] = $cookie['username'];
		$controller->request->data[$this->model][$this->password] = $cookie['password'];

		if ($this->Auth->login()) {
			$this->debug('login', $this->Cookie, $this->Auth->user());

			if (in_array('_autoLogin', get_class_methods($controller))) {
				call_user_func(array($controller, '_autoLogin'), $this->Auth->user());
			}

			if ($this->redirect) {
				$controller->redirect(array(), 301);
			}
		} else {
			$this->debug('loginFail', $this->Cookie, $this->Auth->user());

			if (in_array('_autoLoginError', get_class_methods($controller))) {
				call_user_func(array($controller, '_autoLoginError'), $this->read());
			}
		}
	}

	/**
	 * Automatically process logic when hitting login/logout actions.
	 *
	 * @access public
	 * @param Controller $controller
	 * @param string $url
	 * @param int $status
	 * @param boolean $exit
	 * @return void
	 */
	public function beforeRedirect(Controller $controller, $url, $status = null, $exit = true) {
		if (!$this->active) {
			return;
		}

		if (is_array($this->Auth->loginAction)) {
			if (!empty($this->Auth->loginAction['controller'])) {
				$this->controller = $this->Auth->loginAction['controller'];
			}

			if (!empty($this->Auth->loginAction['action'])) {
				$this->loginAction = $this->Auth->loginAction['action'];
			}

			if (!empty($this->Auth->loginAction['plugin'])) {
				$this->plugin = $this->Auth->loginAction['plugin'];
			}
		}

		if (empty($this->controller)) {
			$this->controller = Inflector::pluralize($this->model);
		}

		// Is called after user login/logout validates, but before auth redirects
		if ($controller->plugin == Inflector::camelize($this->plugin) && $controller->name == Inflector::camelize($this->controller)) {
			$data = $controller->request->data;
			$action = isset($controller->request->params['action']) ? $controller->request->params['action'] : 'login';

			switch ($action) {
				case $this->loginAction:
					if (isset($data[$this->model])) {
						$this->login($data[$this->model]);
					}
				break;

				case $this->logoutAction:
					$this->logout();
				break;
			}
		}
	}

	/**
	 * Login the user by storing their information in a cookie.
	 *
	 * @access public
	 * @param array $data
	 * @return void
	 */
	public function login($data) {
		$username = $data[$this->username];
		$password = $data[$this->password];
		$autoLogin = isset($data['auto_login']) ? $data['auto_login'] : !$this->requirePrompt;

		if ($username && $password && $autoLogin) {
			$this->write($username, $password);

		} else if (!$autoLogin) {
			$this->delete();
		}
	}

	/**
	 * Logout the user by deleting the cookie.
	 *
	 * @access public
	 * @return void
	 */
	public function logout() {
		$this->debug('logout', $this->Cookie, $this->Auth->user());
		$this->delete();
	}

	/**
	 * Read the AutoLogin cookie and base64_decode().
	 *
	 * @access public
	 * @return array|null
	 */
	public function read() {
		$cookie = $this->Cookie->read($this->cookieName);

		if (empty($cookie) || !is_array($cookie)) {
			return null;
		}

		if (isset($cookie['username'])) {
			$cookie['username'] = base64_decode($cookie['username']);
		}

		if (isset($cookie['password'])) {
			$cookie['password'] = base64_decode($cookie['password']);
		}

		return $cookie;
	}

	/**
	 * Remember the user information.
	 *
	 * @access public
	 * @param string $username
	 * @param string $password
	 * @return void
	 */
	public function write($username, $password) {
		$time = time();

		$cookie = array();
		$cookie['username'] = base64_encode($username);
		$cookie['password'] = base64_encode($password);
		$cookie['hash'] = $this->Auth->password($username . $time);
		$cookie['time'] = $time;

		if (env('REMOTE_ADDR') === '127.0.0.1' || env('HTTP_HOST') === 'localhost') {
			$this->Cookie->domain = $this->cookieLocalDomain;
		}

		$this->Cookie->write($this->cookieName, $cookie, true, $this->expires);
		$this->debug('cookieSet', $cookie, $this->Auth->user());
	}

	/**
	 * Delete the cookie.
	 *
	 * @access public
	 * @return void
	 */
	public function delete() {
		$this->Cookie->delete($this->cookieName);
	}

	/**
	 * Debug the current auth and cookies.
	 *
	 * @access public
	 * @param string $key
	 * @param array $cookie
	 * @param array $user
	 * @return void
	 */
	public function debug($key, $cookie = array(), $user = array()) {
		$scopes = array(
			'login'				=> 'Login Successful',
			'loginFail'			=> 'Login Failure',
			'loginCallback'		=> 'Login Callback',
			'logout'			=> 'Logout',
			'logoutCallback'	=> 'Logout Callback',
			'cookieSet'			=> 'Cookie Set',
			'cookieFail'		=> 'Cookie Mismatch',
			'hashFail'			=> 'Hash Mismatch',
			'custom'			=> 'Custom Callback'
		);

		if ($this->_debug && isset($scopes[$key])) {
			$debug = (array) Configure::read('AutoLogin');
			$content = "";

			if ($cookie || $user) {
				if ($cookie) {
					$content .= "Cookie information: \n\n" . print_r($cookie, true) . "\n\n\n";
				}

				if ($user) {
					$content .= "User information: \n\n" . print_r($user, true);
				}
			} else {
				$content = 'No debug information.';
			}

			if (empty($debug['scope']) || in_array($key, (array) $debug['scope'])) {
				if (!empty($debug['email'])) {
					mail($debug['email'], '[AutoLogin] ' . $scopes[$key], $content, 'From: ' . $debug['email']);
				} else {
					$this->log($scopes[$key] . ': ' . $content, LOG_DEBUG);
				}
			}
		}
	}

}
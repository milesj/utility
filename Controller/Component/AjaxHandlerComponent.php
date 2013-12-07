<?php
/**
 * @copyright   2006-2013, Miles Johnson - http://milesj.me
 * @license     https://github.com/milesj/utility/blob/master/license.md
 * @link        http://milesj.me/code/cakephp/utility
 */

App::uses('Component', 'Controller');

use Titon\Utility\Converter;

/**
 * A CakePHP Component that will automatically handle and render AJAX calls and apply the appropriate returned format and headers.
 */
class AjaxHandlerComponent extends Component {

    /**
     * Components.
     *
     * @type array
     */
    public $components = array('RequestHandler');

    /**
     * Controller instance.
     *
     * @type Controller
     */
    public $controller;

    /**
     * Should we allow remote AJAX calls.
     *
     * @type bool
     */
    public $allowRemote = false;

    /**
     * A user given code associated with failure / success messages.
     *
     * @type int
     */
    protected $_code;

    /**
     * Contains the success messages / errors.
     *
     * @type array
     */
    protected $_data;

    /**
     * Which actions are handled as AJAX.
     *
     * @type array
     */
    protected $_handled = array();

    /**
     * Determines if the AJAX call was a success or failure.
     *
     * @type bool
     */
    protected $_success = false;

    /**
     * Load the Controller object.
     *
     * @param Controller $controller
     */
    public function initialize(Controller $controller) {
        if ($controller->request->is('ajax')) {
            Configure::write('debug', 0);

            // Must disable security component for AJAX
            if (isset($controller->Security)) {
                $controller->Security->validatePost = false;
                $controller->Security->csrfCheck = false;
            }

            // If not from this domain, destroy
            if (!$this->allowRemote && (strpos(env('HTTP_REFERER'), trim(env('HTTP_HOST'), '/')) === false)) {
                if (isset($controller->Security)) {
                    $controller->Security->blackHole($controller, 'Invalid referrer detected for this request.');
                } else {
                    $controller->redirect(null, 403, true);
                }
            }
        }

        $this->controller = $controller;
    }

    /**
     * Determine if the action is an AJAX action and handle it.
     *
     * @param Controller $controller
     */
    public function startup(Controller $controller) {
        $handled = ($this->_handled === array('*') || in_array($controller->action, $this->_handled));

        if ($handled && !$controller->request->is('ajax')) {
            if (isset($controller->Security)) {
                $controller->Security->blackHole($controller, 'You are not authorized to process this request.');
            } else {
                $controller->redirect(null, 401, true);
            }
        }

        $this->controller = $controller;
    }

    /**
     * A list of actions that are handled as an AJAX call.
     */
    public function handle() {
        $actions = func_get_args();

        if ($actions === array('*') || empty($actions)) {
            $this->_handled = array('*');
        } else {
            $this->_handled = array_unique(array_intersect($actions, get_class_methods($this->controller)));
        }
    }

    /**
     * Respond the AJAX call with the gathered data.
     *
     * @param string $type
     * @param array $response
     */
    public function respond($type = 'json', array $response = array()) {
        if ($response) {
            $response = $response + array(
                'success' => false,
                'data' => '',
                'code' => null
            );

            $this->response($response['success'], $response['data'], $response['code']);
        }

        if ($type === 'html') {
            $this->RequestHandler->renderAs($this->controller, 'ajax');

        } else {
            $this->RequestHandler->respondAs($type);
            $this->controller->autoLayout = false;
            $this->controller->autoRender = false;

            echo $this->_format($type);
        }
    }

    /**
     * Handle the response as a success or failure alongside a message or error.
     *
     * @param bool $success
     * @param mixed $data
     * @param mixed $code
     */
    public function response($success, $data = '', $code = null) {
        $this->_success = (bool) $success;
        $this->_data = $data;
        $this->_code = $code;
    }

    /**
     * Format the response into the right content type.
     *
     * @param string $type
     * @return string
     */
    protected function _format($type) {
        $response = array(
            'success' => $this->_success,
            'data' => $this->_data
        );

        if ($this->_code) {
            $response['code'] = $this->_code;
        }

        switch (strtolower($type)) {
            case 'json':
                $format = Converter::toJson($response);
            break;
            case 'xml':
                $format = Converter::toXml($response);
            break;
            case 'html';
            case 'text':
            default:
                $format = (string) $this->_data;
            break;
        }

        return $format;
    }

}

<?php
/**
 * @file        Controller/Basic.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Basic controller class
 *
 * @detail      This controller is a basic controller for directly handling requests.  Developers can extend this class
 *              to create their own flexible controllers for use in modern AJAX enabled websites that don't require
 *              HTML views.
 *
 *              How it works is a request is passed to the controller and the controller is responsible for processing
 *              it, creating a new response object and return that object back to the application for processing.
 *
 *              This controller type is typically used for handling AJAX requests as responses to these requests do not
 *              require rendering any views.  This allows AJAX requests to be processed quickly without the overhead of
 *              rendering a view that will never be displayed.
 */
abstract class Basic extends \Hazaar\Controller {

    public $url_default_action_name = 'index';

    protected $request;

    protected $__action        = 'index';

    protected $__actionArgs    = array();

    protected $__cachedActions = array();

    protected static $__cache  = null;

    protected $__cache_key     = null;

    protected $__stream        = FALSE;

    public function cacheAction($action, $timeout = 60, $public = false) {

        /*
         * To cache an action the caching library has to be installed
         */
        if(!class_exists('Hazaar\Cache'))
            throw new \Hazaar\Exception('The Hazaar\Cache class is not available.  Please make sure the hazaar-cache library is correctly installed', 401);

        if(!Basic::$__cache instanceof \Hazaar\Cache)
            Basic::$__cache = new \Hazaar\Cache();

        $this->__cachedActions[$this->name . '::' . $action] = array('timeout' => $timeout, 'public' => $public);

        return true;

    }

    public function getAction() {

        return $this->__action;

    }

    public function getActionArgs() {

        return $this->__actionArgs;

    }

    public function __initialize(\Hazaar\Application\Request $request) {

        parent::__initialize($request);

        $response = null;

        if(!($this->__action = $request->popPath()))
            $this->__action = 'index';

        if(method_exists($this, 'init')) {

            $response = $this->init($request);

            if($response === FALSE)
                throw new \Hazaar\Exception('Failed to initialize action controller! ' . get_class($this) . '::init() returned false!');

        }

        if($request->getPath())
            $this->__actionArgs = explode('/', $request->getPath());

        return $response;

    }

    /**
     * Run an action method on a controller
     *
     * This is the main controller action decision code and is where the controller will decide what to
     * actually execute and whether to cache the response on not.
     *
     * @param mixed $action The name of the action to run
     *
     * @throws Exception\ActionNotFound
     * @throws Exception\ActionNotPublic
     *
     * @return mixed
     */
    protected function __runAction(&$action = null) {

        if(!$action)
            $action = $this->__action;

        /*
         * Check if we have routed to the default controller, or the action method does not
         * exist and if not check for the __default() method.  Otherwise we have nothing
         * to execute so throw a nasty exception.
         */
        if($this->application->router->is_default_controller === true
            || !method_exists($this, $action)) {

            if(method_exists($this, '__default')) {

                array_unshift($this->__actionArgs, $action);

                array_unshift($this->__actionArgs, $this->name);

                $this->__action = $action = '__default';

            } else {

                throw new Exception\ActionNotFound(get_class($this), $action);

            }

        }

        $cache_name = $this->name . '::' . $action;

        /**
         * Check the cached actions to see if this requested should use a cached version
         */
        if(Basic::$__cache && array_key_exists($cache_name, $this->__cachedActions)) {

            $this->__cache_key = $cache_name . '(' . serialize($this->__actionArgs) . ')';

            if($this->__cachedActions[$cache_name]['public'] !== true && $sid = session_id())
                $this->__cache_key .= '::' . $sid;

            if($response = Basic::$__cache->get($this->__cache_key))
                return $response;

        }

        $method = new \ReflectionMethod($this, $action);

        if(! $method->isPublic())
            throw new Exception\ActionNotPublic(get_class($this), $action);

        $response = $method->invokeArgs($this, $this->__actionArgs);

        if($this->__stream)
            return new Response\Stream($response);

        return $response;

    }

    public function __run(){

        $response = $this->__runAction();

        if(!$response instanceof Response){

            $response = (is_array($response) || is_object($response)) ? new Response\Json($response) : new Response\Text($response);

            $this->cacheResponse($response);

        }

        $response->setController($this);

        return $response;

    }

    /**
     * Cache a response to the current action invocation
     *
     * @param Response $response The response to cache
     *
     * @return boolean True or false from the cache backend indicating if the cache store was successful or not.
     */
    protected function cacheResponse(Response $response){

        $cache_name = $this->name . '::' . $this->__action;

        if(!($this->__cache_key !== null && array_key_exists($cache_name, $this->__cachedActions)))
            return false;

        return Basic::$__cache->set($this->__cache_key, $response, $this->__cachedActions[$cache_name]['timeout']);

    }

    public function stream($value) {

        if(! headers_sent()) {

            if(count(ob_get_status()) > 0)
                ob_end_clean();

            header('Content-Type: application/octet-stream;charset=ISO-8859-1');

            header('Content-Encoding: none');

            header("Cache-Control: no-cache, must-revalidate");

            header("Pragma: no-cache");

            header('X-Accel-Buffering: no');

            header('X-Response-Type: stream');

            flush();

            $this->__stream = TRUE;

            ob_implicit_flush();

        }

        $type = 's';

        if(is_array($value)){

            $value = json_encode($value);

            $type = 'a';

        }

        echo dechex(strlen($value)) . "\0" . $type . $value;

        return TRUE;

    }

    /**
     * Forwards an action from the requested controller to another controller
     * 
     * This is some added magic to assist with poorly designed MVC applications where too much "common" code
     * has been implemented in a controller action.  This allows the action request to be forwarded and the
     * response returned.  The target action is executed as though it was called on the requested controller.
     * This means that view data can be modified after the action has executed to modify the response.  
     * 
     * Note: If you don't need to modify any response data, then it would be more efficient to use an alias.
     * 
     * @param string $controller    The name of the controller to forward to.
     * @param string $action        Optional. The name of the action to call on the target controller.  If ommitted, the 
     *                              name of the requested action will be used.
     * @param array  $actionArgs    Optional. An array of arguments to forward to the action.  If ommitted, the arguments
     *                              sent to the calling action will be forwarded.
     * @param Hazaar\Controller $target The target controller.  Allows direct access to the forward controller after it has
     *                              been loaded.
     * 
     * @return mixed Retuns the same return value returned by the forward controller action.
     */
    public function forwardAction($controller, $action = null, $actionArgs = null, &$target = null){

        $target = \Hazaar\Loader::getInstance()->loadController($controller, $this->name);

        if(!($target instanceof Basic))
            throw new \Exception("Unable to forward action to controller '$controller'.  Target controller must be an instance of \Hazaar\Controller\Basic.");

        if($action === null)
            $action = $this->getAction();

        if($actionArgs === null)
            $actionArgs = $this->getActionArgs();

        $this->request->pushPath($action);

        $target->__initialize($this->request);

        return call_user_func_array(array($target, $action), $actionArgs);

    }

}

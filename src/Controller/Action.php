<?php
/**
 * @file        Controller/Action.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Abstract controller action class
 *
 * @detail      This controller handles actions and responses using views
 */
abstract class Action extends \Hazaar\Controller\Basic {

    public    $view;

    public    $_helper;

    protected $methods       = array();

    public function __construct($name, \Hazaar\Application $application, $use_app_config = true) {

        parent::__construct($name, $application);

        $this->_helper = new Action\HelperBroker($this);

        if(!($this->view = $this->_helper->addHelper('ViewRenderer')))
            throw new Exception\NoDefaultRenderer();

        if($use_app_config && $this->application->config->app->has('layout')) {

            $this->_helper->ViewRenderer->layout($this->application->config->app['layout'], true);

            if($this->application->config->app->has('favicon'))
                $this->_helper->ViewRenderer->link($this->application->config->app['favicon'], 'shortcut icon');

        }

    }

    public function __registerMethod($name, $callback) {

        if(array_key_exists($name, $this->methods))
            throw new Exception\MethodExists($name);

        $this->methods[$name] = $callback;

        return TRUE;

    }

    public function __call($method, $args) {

        if(array_key_exists($method, $this->methods))
            return call_user_func_array($this->methods[$method], $args);

        throw new Exception\MethodNotFound(get_class($this), $method);

    }

    public function __get($plugin) {

        throw new \Hazaar\Exception('Controller plugins not supported yet.  Called: ' . $plugin);

        if(array_key_exists($plugin, $this->plugins))
            return $this->plugins[$plugin];

        return NULL;

    }

    public function __run() {

        $response = parent::__runAction();

        if(!$response instanceof Response) {

            if($response === NULL) {

                $response = new Response\Html();

                /*
                 * Execute the action helpers.  These are responsible for actually rendering any views.
                 */
                $this->_helper->execAllHelpers($this, $response);

                $response->enableTidy($this->application->config->app->get('tidy', false));

            }elseif(is_string($response)){

                $response = new Response\Text($response);

            }elseif($response instanceof \Hazaar\Html\Element){

                $html = new Response\Html();

                $html->setContent($response);

                $response = $html;

            }elseif($response instanceof \Hazaar\File){

                $response = new Response\File($response);

            }else{

                $response = new Response\Json($response);

            }

        }

        $this->cacheResponse($response);

        $response->setController($this);

        return $response;

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

        $response = parent::forwardAction($controller, $action, $actionArgs, $target);

        $this->methods = $target->methods;

        $this->_helper = $target->_helper;

        $this->view = $target->view;

        return $response;

    }

}
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

    private   $stream        = FALSE;

    public function __construct($name, \Hazaar\Application $application, $use_app_config = true) {

        parent::__construct($name, $application, $use_app_config);

        $this->_helper = new Action\HelperBroker($this);

        if(! $this->view = $this->_helper->addHelper('ViewRenderer'))
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

        if($this->stream)
            return new Response\Stream($response);

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

            $this->stream = TRUE;

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

}
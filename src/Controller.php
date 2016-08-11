<?php
/**
 * @file        Controller/Controller.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * @brief       Abstract controller class
 */
abstract class Controller implements Controller\_Interface {

    public    $application;

    protected $name;

    protected $request;

    public    $statusCode;

    public function __construct($name, $application) {

        $this->name = $name;

        $this->setApplication($application);

    }

    public function __shutdown() {

        if(method_exists($this, 'shutdown')) {

            $this->shutdown();

        }

    }

    public function getName() {

        return $this->name;

    }

    public function setApplication($application) {

        if(! $application instanceof \Hazaar\Application)
            throw new Exception("Error setting application on controller " . get_class($this) . ". Object of type " . get_class($application) . " is not an application object!");

        $this->application = $application;

    }

    public function setRequest($request) {

        if($request instanceof Application\Request) {

            $this->request = $request;

        }

    }

    public function setStatus($code) {

        $this->statusCode = $code;

    }

    public function redirect($location, $args = array(), $save_url = TRUE) {

        $this->application->redirect($location, $args, $save_url);

    }

    public function __tostring() {

        return get_class($this);

    }

    /**
     * @brief       Generate a URL relative to the controller
     *
     * @detail      This is the controller relative method for generating URLs in your application.  URLs generated from
     * here are
     *              relative to the controller.  For URLs that are relative to the current application see
     * Application::url()
     *
     *              Parameters are dynamic and depend on what you are trying to generate.
     *
     *              For examples see: \ref generating_urls
     *
     */
    public function url() {

        $controller = NULL;

        $method = NULL;

        $params = NULL;

        /*
         * Figure out our controller/method combo
         */
        if(count($args = func_get_args()) > 0) {

            if(is_array($args[0]) && count($args[0]) > 1) {

                list($controller, $method) = $args[0];

            } elseif(count($args) == 2) {

                list($controller, $method) = $args;

                if(is_array($method)) {

                    $params = $method;

                    $method = NULL;

                }

                if(! $method) {

                    $method = $controller;

                    $controller = NULL;

                }

            } elseif(count($args) == 3) {

                list($controller, $method, $params) = $args;

            } else {

                if(substr(trim($args[0]), 0, 1) == '/') {

                    $args[0] = $this->getAction() . $args[0];

                }

                $method = $args[0];

            }

        }

        if(! $controller) {

            $controller = strtolower($this->getName());

        }

        return $this->application->url($controller, $method, $params);

    }

    public function cacheAction($action, $timeout = 60) {

        if(! array_key_exists($action, $this->cachedActions)) {

            $this->cachedActions[$action] = $timeout;

            return TRUE;

        }

        return FALSE;

    }

    public function active($controller = NULL, $action = NULL) {

        if(is_array($controller)) {

            $parts = $controller;

            if(count($parts) > 0)
                $controller = array_shift($parts);

            if(count($parts) > 0)
                $action = array_shift($parts);

        }

        if(! $controller)
            $controller = $this->getName();

        if(! $action)
            $action = 'index';

        return (strcasecmp($this->getName(), $controller) == 0 && strcasecmp($this->getAction(), $action) == 0);

    }

}
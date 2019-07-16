<?php

namespace Hazaar\Controller\Action;

class HelperBroker {

    private $helpers = array();

    private $controller;

    function __construct($controller) {

        $this->controller = $controller;

    }

    public function addHelper($helper) {

        $class = 'Hazaar\\Controller\\Action\\Helper\\' . $helper;

        $obj = new $class($this->controller);

        $this->helpers[$helper] = $obj;

        return $obj;

    }

    public function removeHelper($name) {

        if(!array_key_exists($name, $this->helpers))
            return false;

        unset($this->helpers[$name]);

        return true;

    }

    public function __call($helper, $args) {

        if(array_key_exists($helper, $this->helpers) && $this->helpers[$helper] instanceof Helper) {

            $obj = $this->helpers[$helper];

            if(method_exists($obj, 'direct')) {

                return call_user_func_array(array(
                    $obj,
                    'direct'
                ), $args);

            }

            return $obj;

        }

        return null;

    }

    public function __get($helper) {

        if(array_key_exists($helper, $this->helpers) 
            && $this->helpers[$helper] instanceof Helper) {

            $obj = $this->helpers[$helper];

            return $obj;

        }

        return null;

    }

    public function execAllHelpers($controller, $response) {

        foreach($this->helpers as $helper => $obj)
            $obj->__exec($controller, $response);

    }

}

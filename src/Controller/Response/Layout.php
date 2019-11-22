<?php

namespace Hazaar\Controller\Response;

class Layout extends \Hazaar\Controller\Response\Html implements \ArrayAccess {

    private $_layout;

    private $_data = array();

    function __construct($layout = NULL, $init_default_helpers = TRUE) {

        parent::__construct();

        if($layout instanceof \Hazaar\View\Layout) {

            $this->_layout = $layout;

        } else {

            $this->_layout = new \Hazaar\View\Layout($layout, $init_default_helpers);

        }

    }

    public function __get($key) {

        return $this->_layout->get($key);

    }

    public function __set($key, $value) {

        return $this->_layout->set($key, $value);

    }

    protected function __prepare($controller) {

        $this->_layout->setContent($this->content);

        $this->_layout->registerMethodHandler($controller);

        $this->_layout->initHelpers();

        $this->_layout->runHelpers();

        $content = $this->_layout->render();

        $this->setContent($content);

    }

    public function __call($method, $param_arr) {

        return call_user_func_array(array(
            $this->_layout,
            $method
        ), $param_arr);

    }

    public function offsetGet($key) {

        return $this->_layout->get($key);

    }

    public function offsetSet($key, $value) {

        $this->_layout->set($key, $value);

    }

    public function offsetUnset($key) {

        $this->_layout->remove($key);

    }

    public function offsetExists($key) {

        return $this->_layout->has($key);

    }

    public function view($view, $key = null){

        return $this->_layout->add($view, $key);

    }

}
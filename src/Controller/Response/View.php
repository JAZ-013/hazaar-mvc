<?php

namespace Hazaar\Controller\Response;

class View extends \Hazaar\Controller\Response\Html {

    private $_view;

    private $_view_name;

    private $_data     = array();

    private $_requires = array();

    function __construct($view) {

        parent::__construct();

        $this->load($view);

    }

    public function & __get($key) {

        return $this->_data[$key];

    }

    public function __set($key, $value) {

        $this->_data[$key] = $value;

    }

    public function populate($values) {

        if(is_object($values))
            $values = $values instanceof \Hazaar\Model\Strict ? $values->toArray() : \iterator_to_array($values);

        $this->_data = $values;

    }

    public function load($view, $backend = NULL) {

        if($view instanceof \Hazaar\View) {

            $this->_view = $view;

            $this->_view_name = $view->getName();

        } else {

            $this->_view_name = $view;

            $this->_view = new \Hazaar\View($view, array('html'));

        }

    }

    protected function __prepare($controller) {

        if(! ($this->_view instanceof \Hazaar\View))
            $this->_view = new \Hazaar\View($this->_view_name);

        $this->_view->registerMethodHandler($controller);

        $this->_view->populate($this->_data);

        if(is_array($this->_requires)) {

            foreach($this->_requires as $script)
                $this->_view->requires($script);

        }

        $content = $this->_view->render();

        $this->setContent($content);

    }

    public function __call($method, $param_arr) {

        return call_user_func_array(array($this->_view, $method), $param_arr);

    }

    public function requires($script) {

        $this->_requires[] = $script;

    }

    public function render($controller) {

        $this->_view->registerMethodHandler($controller);

        return $this->_view->render();

    }

}
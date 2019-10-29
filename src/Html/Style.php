<?php

namespace Hazaar\Html;

class Style {

    private $selectors = array();

    private $current;

    function __construct($selector = NULL, $elements = array()) {

        $this->current = $selector;

        if(count($elements) > 0)
            $this->set($elements);

    }

    public function select($selector) {

        $this->current = $selector;

        return $this;

    }

    public function __set($key, $value) {

        return $this->set($key, $value);

    }

    public function & __get($key) {

        $this->current = $key;

        return $this;

    }

    public function __call($method, $args) {

        if($this->current) {

            $method = preg_replace('/_/', '-', $method);

            $this->selectors[$this->current][$method] = $args[0];

        }

        return $this;

    }

    /**
     * @detail      Set a style element or group of elements on a selector
     *
     *              This method has three possible methods of use.
     *
     *              set( Array ) - Set multiple elements on the current selector
     *
     *              set( element_name, element_value ) - Set a single element on the current selector.
     *
     *              set( selector, Array ) - Set multiple elements on the requested selector
     */
    public function set() {

        $argc = func_num_args();

        if($argc == 1) {

            $value = func_get_arg(0);

            if(! is_array($value))
                throw new \Hazaar\Exception('Argument should be an array when only passing one argument to Style::set()');

            if(! array_key_exists($this->current, $this->selectors) || ! is_array($this->selectors[$this->current]))
                $this->selectors[$this->current] = array();

            $this->selectors[$this->current] = array_merge($this->selectors[$this->current], $value);

        } elseif($argc == 2) {

            list($key, $value) = func_get_args();

            /**
             * If the second argument is an array then we are setting an entire selector
             */
            if(is_array($value)) {

                $this->current = $key;

                return $this->set($value);

            } else {

                $this->selectors[$this->current][$key] = $value;

            }

        } else {

            throw new \Hazaar\Exception("Style method 'set' does not know how to handle $argc arguments");

        }

        return $this;

    }

    public function __tostring() {

        if($this->current) {

            return (string)$this->asBlock();

        }

        return (string)$this->asParameterList();

    }

    public function asParameterList() {

        if(! array_key_exists(NULL, $this->selectors))
            return NULL;

        $params = new Parameters(NULL, ': ', FALSE, ';');

        foreach($this->selectors[NULL] as $property => $value) {

            $params->set($property, $value);

        }

        return $params;

    }

    public function asBlock() {

        /*
         * Otherwise build a style block
         */
        $selectors = array();

        foreach($this->selectors as $selector => $properties) {

            $block = $selector . " { ";

            foreach($properties as $property => $value) {

                $block .= $property . ': ' . $value . '; ';

            }

            $block .= " }";

            $selectors[] = $block;

        }

        return new Block('style', implode(' ', $selectors));

    }

    /**
     * Array Access Methods
     */
    public function offsetExists($offset) {

        return array_key_exists($offset, $this->selectors);

    }

    public function & offsetGet($offset) {

        if(array_key_exists($offset, $this->selectors))
            return $this->selectors[$offset];

        return NULL;

    }

    public function offsetSet($offset, $value) {

        $this->selectors[$offset] = $value;

    }

    public function offsetUnset($offset) {

        unset($this->selectos[$offset]);

    }

    static public function px($value) {

        if(is_integer($value) || is_numeric($value)) {

            $value = $value . 'px';

        }

        return $value;

    }

    static public function em($value) {

        if(is_integer($value) || is_numeric($value)) {

            $value = $value . 'em';

        }

        return $value;

    }

}


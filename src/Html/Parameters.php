<?php

namespace Hazaar\Html;

/**
 * @brief       HTML Parameter Class
 *
 * @detail      Class for storing and rendering HTML element parameters.
 *
 * @since       1.0.0
 */
class Parameters implements \Countable {

    private $params       = array();

    private $delimeter    = '=';

    private $suffix;

    private $quote_params = TRUE;

    private $multi_value = [];

    /**
     * @detail      HTML parameter class constructor
     *
     * @since       1.0.0
     *
     * @param       Array $params Array of key/value pairs of parameters
     *
     * @param       string $delimiter Optionally set the delimiter. (allows for use in other than HTML)
     *
     * @param       bool $quote_params Set whether parameters are quoted.  Default: true.
     *
     * @param       string $suffix Optionally specify a parameter value suffix.
     */
    function __construct($params = array(), $delimeter = NULL, $quote_params = TRUE, $suffix = NULL) {

        if($params) {

            if(! is_array($params))
                $params = array($params);

            $this->params = $params;

        }

        if($delimeter)
            $this->delimeter = $delimeter;

        if($quote_params !== NULL)
            $this->quote_params = $quote_params;

        if($suffix)
            $this->suffix = $suffix;

    }

    public function count() {

        return count($this->params);

    }

    public function & get($key) {

        if(array_key_exists($key, $this->params))
            return $this->params[$key];

        $null = NULL;

        return $null;

    }

    public function set() {

        if(func_num_args() == 1) {

            list($key) = func_get_args();

            $value = NULL;

        } else {

            list($key, $value) = func_get_args();

        }

        if($value === NULL) {

            return $this->params[] = $key;

        } elseif(is_bool($value)) {

            $value = strbool($value);

        } elseif(is_array($value)) {

            foreach($value as $skey => $svalue)
                $this->params[$skey] = $svalue;

            return NULL;

        }

        if(array_key_exists($key, $this->multi_value)){

            if(!(array_key_exists($key, $this->params) && is_array($this->params[$key])))
                $this->params[$key] = [];

            $this->params[$key] = array_unique(array_merge($this->params[$key], explode($this->multi_value[$key], trim($value, $this->multi_value[$key]))));

            return $value;

        }

        return $this->params[$key] = $value;

    }

    /**
     * Remove a parameter, optionally for only a specific value
     * 
     * If only $key is specified, then the entire parameter is removed.  If the $value is specified and the parameter
     * is a multi-value enabled parameter, then only the specified value in the parameter is removed.
     * 
     * @param string $key The name of the parameter.
     * 
     * @param string $value The optional parameter value to be removed.
     */
    public function remove($key, $value = null){

        if(!array_key_exists($key, $this->params))
            return false;

        if(is_array($this->params[$key]) && array_key_exists($key, $this->multi_value) && ($delimeter = $this->multi_value[$key])){

            if (($index = array_search(trim($value, $delimeter), $this->params[$key])) !== false)
                unset($this->params[$key][$index]);

        }else unset($this->params[$key]);

        return true;

    }

    public function appendTo($key, $value) {

        if(! array_key_exists($key, $this->params))
            return FALSE;

        $this->params[$key] .= $value;

        return TRUE;

    }

    public function __tostring() {

        return $this->renderObject();

    }

    public function renderObject() {

        $out = array();

        foreach($this->params as $key => $value) {

            if($value instanceof Style) {

                $value = (string)$value->asParameterList();

                $key = 'style';

            }

            if(is_numeric($key)) {

                $out[] = $value;

            } else {

                if(is_array($value))
                    $value = implode((array_key_exists($key, $this->multi_value) ? $this->multi_value[$key] : ''), $value);

                if($this->quote_params)
                    $value = '"' . htmlspecialchars($value) . '"';

                $out[] = $key . $this->delimeter . $value . $this->suffix;

            }

        }

        return implode(' ', $out);

    }

    public function has($key) {

        /**
         * Some magic here.  We look for attributes as array keys and if that doesn't exist we look for properties,
         * where a property is a key in the array as a value with a numeric index.
         */
        return array_key_exists($key, $this->params) || is_int(array_search($key, $this->params, true));

    }

    public function append($key, $value) {

        if(array_key_exists($key, $this->params))
            $this->params[$key] .= $value;
        else
            $this->params[$key] = $value;

    }

    public function toArray(){

        return $this->params;

    }

    public function setMultiValue($key, $delimeter = ','){

        $this->multi_value[$key] = $delimeter;

    }

}


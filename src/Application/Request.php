<?php
/**
 * @file        Hazaar/Application/Request.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Application;

abstract class Request implements Request\_Interface {

    protected $dispatched = FALSE;

    protected $params     = array();

    protected $exception;

    /**
     * The original path excluding the application base path
     */
    protected $base_path;

    /**
     * The requested path
     *
     * @var mixed
     */
    private $path;

    function __construct() {

        $args = func_get_args();

        if(method_exists($this, 'init'))
            $this->base_path = call_user_func_array(array($this,'init'), $args);

        $this->path = $this->base_path;

    }

    public function getBasePath(){

        return $this->base_path;

    }

    /**
     * Return the request path.
     *
     * @param mixed $strip_filename If true, this will cause the function to return anything before the last '/'
     *                              (including the '/') which is the full directory path name. (Similar to dirname()).
     *
     * @since       1.0.0
     *
     * @return \null|string The path suffix of the request URI
     */
    public function getPath($strip_filename = false) {

        if($strip_filename !== true)
            return $this->path;

        $path = ltrim($this->path, '/');

        if(($pos = strrpos($path, '/')) === false)
            return null;

        return substr($path, 0, $pos) . '/';

    }

    /**
     * Set the request path
     *
     * @param mixed $path
     */
    public function setPath($path){

        $this->path = $path;

    }

    /**
     * Pop a part off the path.
     *
     * A "part" is simple anything delimited by '/' in the path section of the URL.
     *
     * @return string
     */
    public function popPath(){

        if(!$this->path)
            return null;

        if(($pos = strpos($this->path, '/')) === false){

            $part = $this->path;

            $this->path = null;

        }else{

            $part = substr($this->path, 0, $pos);

            $this->path = substr($this->path, $pos + 1);

        }

        return $part;

    }

    public function pushPath($part){

        $this->path .= ((strlen($this->path) > 0) ? '/' : '') . $part;
        
    }

    public function __get($key) {

        return $this->get($key);

    }

    /**
     * Retrieve a request value.
     *
     * These values can be sent in a number of ways.
     * * In a query string.  eg: http://youhost.com/controller?key=value
     * * As form POST data.
     * * As JSON encoded request body.
     *
     * Only JSON encoded request bodies support data typing.  All other request values will be
     * strings.
     *
     * @since 2.3.44
     *
     * @param mixed $key The data key to retrieve
     * @param mixed $default If the value is not set, use this default value.
     * @return string|mixed Most of the time this will return a string, unless data-typing is available when using JSON requests.
     */
    public function get($key, $default = NULL) {

        if(array_key_exists($key, $this->params)){

            $value = $this->params[$key];

            if($value === 'null')
                $value = null;
            elseif($value == 'true' || $value == 'false')
                $value = boolify($value);

            return $value;

        }

        return $default;

    }

    /**
     * Retrieve an integer value from the request
     *
     * The most common requests will not provide data typing and data value will always be a string.  This method
     * will automatically return the requested value as an integer unless it is NULL or not set.  In which case
     * either NULL or the default value will be returned.
     *
     * @since 2.3.44
     *
     * @param mixed $key The key of the request value to return.
     * @param mixed $default A default value to use if the value is NULL or not set.
     * @return int
     */
    public function get_int($key, $default = NULL) {

        $value = $this->get($key, $default);

        return ($value === null) ? $value : intval($value);

    }

    /**
     * Retrieve an float value from the request
     *
     * The most common requests will not provide data typing and data value will always be a string.  This method
     * will automatically return the requested value as an float unless it is NULL or not set.  In which case
     * either NULL or the default value will be returned.
     *
     * @since 2.3.44
     *
     * @param mixed $key The key of the request value to return.
     * @param mixed $default A default value to use if the value is NULL or not set.
     * @return float
     */
    public function get_float($key, $default = NULL) {

        $value = $this->get($key, $default);

        return ($value === null) ? $value : floatval($value);

    }

    /**
     * Retrieve an boolean value from the request
     *
     * The most common requests will not provide data typing and data value will always be a string.  This method
     * will automatically return the requested value as an boolean unless it is NULL or not set.  In which case
     * either NULL or the default value will be returned.
     *
     * This internally uses the boolify() function so the usual bool strings are supported (t, f, true, false, 0, 1, on, off, etc).
     *
     * @since 2.3.44
     *
     * @param mixed $key The key of the request value to return.
     * @param mixed $default A default value to use if the value is NULL or not set.
     * @return boolean
     */
    public function get_bool($key, $default = NULL) {

        return boolify($this->get($key, $default));

    }

    /**
     * Check to see if a request value has been set
     *
     * @param mixed $keys The key of the request value to check for.
     * @param boolean $check_any The check type when $key is an array.  TRUE means that ANY key must exist.  FALSE means ALL keys must exist.
     *
     * @return boolean True if the value is set, False otherwise.
     */
    public function has($keys, $check_any = false) {

        /**
         * If the parameter is an array, make sure all of the keys exist before returning true
         */

        if(!is_array($keys))
            $keys = array($keys);

        $result = false;

        $count = count(array_intersect($keys, array_keys($this->params)));

        return $check_any ? $count > 0 : $count === count($keys);

    }

    /**
     * Set a request value.
     *
     * This would not normally be used and has no internal implications on how the application will function
     * as this data is not processed in any way.  However setting request data may be useful in your application
     * when reusing/repurposing controller actions so that they may be called from somewhere else in your
     * application.
     *
     * @param mixed $key The key value to set.
     * @param mixed $value The new value.
     */
    public function set($key, $value) {

        $this->params[$key] = $value;

    }

    public function __unset($key) {

        $this->remove($key);

    }

    public function remove($key) {

        unset($this->params[$key]);

    }

    /**
     * Return an array of request parameters as key/value pairs.
     *
     * @param array $filter_in Only include parameters with keys specified in this filter.
     * 
     * @param array $filter_out Exclude parameters with keys specified in this filter.
     *
     * @return array
     */
    public function getParams($filter_in = NULL, $filter_out = NULL) {

        if($filter_in === null && $filter_out === null)
            return $this->params;

        $params = $this->params;

        if($filter_in){
            
            if(!is_array($filter_in))
                $filter_in = array($filter_in);

            $params = array_intersect_key($params, array_flip($filter_in));

        }

        if($filter_out){

            if(!is_array($filter_out))
                $filter_out = array($filter_out);

            $params = array_diff_key($params, array_flip($filter_out));

        }

        return $params;

    }

    public function hasParams() {

        return (count($this->params) > 0);

    }

    public function setParams(array $array) {

        $this->params = array_merge($this->params, $array);

        foreach($this->params as $key => $value) {

            if(substr($key, 0, 4) == 'amp;') {

                $newKey = substr($key, 4);

                $this->params[$newKey] = $value;

                unset($this->params[$key]);

            }

        }

    }

    public function count() {

        return count($this->params);

    }

    public function setDispatched($flag = TRUE) {

        $this->dispatched = $flag;

    }

    public function isDispatched() {

        return $this->dispatched;

    }

    /*
     * This is used to store an exception that has occurred during the processing of the request
     */
    public function setException(Exception $e) {

        $this->exception = $e;

    }

    public function hasException() {

        return ($this->exception instanceof Exception);
    }

    public function getException() {

        return $this->exception;

    }

}
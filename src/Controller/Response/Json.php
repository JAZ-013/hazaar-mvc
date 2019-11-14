<?php

namespace Hazaar\Controller\Response;

class Json extends \Hazaar\Controller\Response implements \ArrayAccess {

    protected $content = array();

    /*
     * If the callback is set, such as in a JSONP request, we use the callback to return
     * the encoded data.
     */
    private $callback;

    function __construct($data = array(), $status = 200) {

        if(! function_exists('json_encode')) {
            throw new Exception\JsonNotSupported();
        }

        parent::__construct("application/json", $status);

        $this->content = $data;

    }

    public function toArray() {

        return $this->content;

    }

    public function & __get($key) {

        return $this->get($key);

    }

    public function & get($key) {

        return $this->content[$key];

    }

    public function __set($key, $value) {

        $this->set($key, $value);

    }

    public function set($key, $value) {

        $this->content[$key] = $value;

    }

    public function populate($data) {

        $this->content = $data;

    }

    public function push($data) {

        $this->content[] = $data;

    }

    /*
     * JSONP Tools
     */

    public function setCallback($callback) {

        $this->callback = $callback;

    }

    public function getContent() {

        $data = json_encode($this->content, JSON_INVALID_UTF8_SUBSTITUTE);

        if($data === false)
            throw new \Hazaar\Exception('JSON Encode error: ' . json_last_error_msg());

        if($this->callback)
            $data = $this->callback . "($data)";

        return $data;

    }

    //ArrayAccess

    public function offsetExists($key) {

        return array_key_exists($key, $this->content);

    }

    public function & offsetGet($key) {

        if(array_key_exists($key, $this->content))
            return $this->content[$key];

        $null = NULL;

        return $null;

    }

    public function offsetSet($key, $value) {

        if($key === NULL)
            $this->content[] = $value;
        else
            $this->content[$key] = $value;

    }

    public function offsetUnset($key) {

        unset($this->content[$key]);

    }

}
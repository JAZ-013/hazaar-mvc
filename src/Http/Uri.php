<?php

namespace Hazaar\Http;

class Uri implements \ArrayAccess {

    private $parts  = array(
        'scheme' => 'http',
        'host' => 'localhost',
        'path' => '/',
        'query' => null
    );

    private $params = array();

    public $common_ports = array(
        'ftp' => 21,
        'http' => 80,
        'https' => 443
    );

    function __construct($uri = null) {

        if($uri === null)
            return;

        if(strpos($uri, ':') == false)
            $uri = 'http://' . $uri;

        $this->parts = parse_url($uri);

        if(! is_array($this->parts))
            return;

        if(array_key_exists('query', $this->parts))
            parse_str($this->parts['query'], $this->params);

    }

    public function scheme($value = null) {

        if($value === null)
            return ake($this->parts, 'scheme', 'http');

        return $this->parts['scheme'] = $value;

    }

    public function host($value = NULL) {

        if($value === null)
            return ake($this->parts, 'host');

        return $this->parts['host'] = $value;

    }

    public function port($value = null) {

        if($value === null) {

            if(!array_key_exists('port', $this->parts)) {

                $scheme = $this->scheme();

                /*
                 * Check the scheme is a common one and get the port that way if we can.
                 * Doing this first instead of using the services lookup will be faster for these common protocols.
                 */
                $this->parts['port'] = $this->lookupPort($scheme);

            }

            return ake($this->parts, 'port');

        }

        return $this->parts['port'] = intval($value);

    }

    public function lookupPort($scheme){

        if($port = ake($this->common_ports, $scheme))
            return $port;

        $services_file = ((substr(PHP_OS, 0, 3) == 'WIN') ? $_SERVER['SystemRoot'] . '\System32\drivers' : null )
                        .  DIRECTORY_SEPARATOR . 'etc' . DIRECTORY_SEPARATOR . 'services';

        if(file_exists($services_file)){

            $services = file_get_contents($services_file);

            if(preg_match('/^' . $scheme . '\s*(\d*)\/tcp/m', $services, $matches))
                return (int)$matches[1];

        }

        return null;
    }

    public function user() {

        if(func_num_args() == 0)
            return ake($this->parts, 'user');

        return $this->parts['user'] = func_get_arg(0);

    }

    public function pass() {

        if(func_num_args() == 0)
            return ake($this->parts, 'pass');

        return $this->parts['pass'] = func_get_arg(0);

    }

    public function path() {

        if(func_num_args() == 0)
            return ake($this->parts, 'path', '/');

        return $this->parts['path'] = func_get_arg(0);
    }

    public function params() {

        if(func_num_args() == 0)
            return ake($this->parts, 'query');

        return $this->parts['query'] = func_get_arg(0);

    }

    public function hash() {

        if(func_num_args() == 0)
            return ake($this->parts, 'fragment');

        return $this->parts['fragment'] = func_get_arg(0);

    }

    public function __get($key) {

        return $this->get($key);

    }

    public function get($key) {

        return ake($this->params, $key);

    }

    public function __set($key, $value) {

        $this->set($key, $value);

    }

    public function set($key, $value) {

        $this->params[$key] = $value;

    }

    public function setParams($array) {

        if(! is_array($array))
            return FALSE;

        $this->params = array_merge($this->params, $array);

    }

    public function offsetExists($key) {

        return array_key_exists($key, $this->parts['query']);

    }

    public function offsetGet($key) {

        return $this->get($key);

    }

    public function offsetSet($key, $value) {

        $this->set($key, $value);

    }

    public function offsetUnSet($key) {

        unset($this->params[$key]);

    }

    public function isSecure() {

        return (substr($this->scheme(), -1) == 's');

    }

    public function toString() {

        $scheme = $this->scheme();

        $port = $this->port();

        $uri = $scheme . '://' . (ake($this->parts, 'user') ? $this->parts['user'] . (ake($this->parts, 'pass') ? ':' . $this->parts['pass'] : NULL) . '@' : NULL) 
            . $this->host() 
            . (($port === $this->lookupPort($scheme)) ? null : ':' . $port) 
            . $this->path() 
            . ((count($this->params) > 0) ? '?' . http_build_query($this->params) : NULL);

        return $uri;

    }

    public function __tostring() {

        return $this->toString();

    }

    public function queryString(){

        if(count($this->params) > 0)
            return '?' . http_build_query($this->params);

        return null;

    }

}



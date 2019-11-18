<?php

/**
 * @file        Cache.php
 *
 * @author      Jamie Carl <jamie@hazaarmvc.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarmvc.com)
 *
 * @version     $Id: Application.php 24593 2012-08-29 20:35:02Z jamie $
 */
namespace Hazaar;

/**
 * @brief The cache frontend
 */
class Cache implements \ArrayAccess {

    protected $options;

    protected $use_cache = TRUE;

    protected $backend;

    /**
     * Cache object constructor
     *
     * @param string $backend
     *            The name of the backend to use. Currently 'apc', 'file', 'memcached', 'session' and
     *            'sqlite' are supported.
     *
     * @param array $options
     *            Options to pass on to the backend. These will also be specific to the backend
     *            you are using.
     *
     * @param string $namespace
     *            The namespace to use for grouping stored data.
     *
     * @throws Cache\Exception\InvalidBackend
     *
     * @throws Cache\Exception\InvalidFrontend
     */
    function __construct($backend = NULL, $config_options = array(), $namespace = 'default') {

        $options = new \Hazaar\Map();

        if($config_options)
            $options->extend($config_options);

        if (!$backend){

            //Set up a default backend chain
            $backend = array('shm', 'apc', 'session');

            //Grab the application context so we can load any cache settings
            if (($app = \Hazaar\Application::getInstance()) instanceof \Hazaar\Application) {

                if($app->config->cache->has('backend')){

                    $backend = $app->config->cache['backend'];

                    $options->extend($app->config->cache['options']);

                }

            }

        }

        $this->options = $options;

        $this->configure(array(
            'lifetime' => 3600,
            'use_pragma' => TRUE
        ));

        if(!is_array($backend))
            $backend = array($backend);

        //We set this now as it is an absolute safe fallback
        $backendClass = '\\Hazaar\\Cache\\Backend\\File';

        foreach($backend as $name){

            $backendClass = '\\Hazaar\\Cache\\Backend\\' . ucfirst($name);

            if(class_exists($backendClass) && $backendClass::available())
                break;

            unset($backendClass);

        }

        if (!isset($backendClass))
            throw new Cache\Exception\NoBackendAvailable($backendClass);

        $this->backend = new $backendClass($options, $namespace);

        if (!$this->backend instanceof Cache\Backend)
            throw new Cache\Exception\InvalidBackend($backendClass);

        /**
         * Cache skip
         *
         * Check for a Pragma header to see if we should skip loading from cache.
         */
        if ($this->options->use_pragma && function_exists('apache_request_headers')) {

            $headers = apache_request_headers();

            if (array_key_exists('Pragma', $headers) && $headers['Pragma'] == 'no-cache')
                $this->use_cache = FALSE;

        }

    }

    public function configure($options) {

        $this->options->enhance($options);

    }

    public function getBackend(){

        return $this->backend;

    }

    public function setBackendOption($key, $value){

        $this->backend->options->extend(array($key => $value));

    }

    /**
     * Retrieve a value from cache storage.
     *
     * @param mixed $key
     *            The reference key used to store the value.
     *
     * @param bool $default
     *            If the value doesn't exist, this default will be returned instead.
     *
     * @param bool $save_default
     *            If the value doesn't exist and a default is specified, save that default to cache.
     *
     * @return mixed The value that was stored in cache.
     */
    public function &get($key, $default = FALSE, $save_default = FALSE) {

        if (!$this->use_cache)
            return $default;

        $result = $this->backend->get($key);

        if (!$this->backend->can('store_objects'))
            $result = unserialize($result);

        if ($result === FALSE) {

            if ($save_default === TRUE)
                $this->set($key, $default);

            return $default;

        }

        return $result;

    }

    /**
     * Store a value in the cache using the current cache backend.
     *
     * @param mixed $key
     *            The reference key under which to store the value.
     *
     * @param mixed $value
     *            The value that should be stored. Values can be pretty much anything including integers, booleans,
     *            strings and any object that can be serialised.
     *
     * @param mixed $timeout
     *            The number of seconds after which the value becomes invalid. If not set the global
     *            'lifetime' option is used. Set a value of '-1' to indicate that the value should never timeout.
     *
     * @return boolean Boolean indicating whether or not the operation succeeded.
     */
    public function set($key, $value, $timeout = NULL) {

        /*
         * If the backend can't store objects, serialize the value.
         */
        if (!$this->backend->can('store_objects'))
            $value = serialize($value);

        return $this->backend->set($key, $value, $timeout);

    }

    /**
     * Check if a stored value exists.
     *
     * @param mixed $key
     *            The value key to check for.
     *
     * @return boolean Returns TRUE or FALSE indicating if the value is stored.
     */
    public function has($key) {

        return $this->backend->has($key);

    }

    /**
     * Removes a stored value
     *
     * @param mixed $key
     *            The key of the value to remove
     *
     * @return boolean tRUE indicates the value existed and was removed. FALSE otherwise.
     */
    public function remove($key) {

        return $this->backend->remove($key);

    }

    public function extend($array, $recursive = FALSE) {

        if (!is_array($array))
            return FALSE;

        foreach($array as $key => $value) {

            if (is_array($value) && $recursive) {

                $c = $this->get($key);

                if ($c && is_array($c))
                    $value = array_merge_recursive($c, $value);

                $this->set($key, $value);
            } else {

                $this->set($key, $value);
            }
        }

    }

    public function clear() {

        return $this->backend->clear();

    }

    public function setValues($values) {

        if (!is_array($values))
            return FALSE;

        foreach($values as $key => $value)
            $this->set($key, $value);

        return TRUE;

    }

    public function pull($key) {

        $value = $this->get($key);

        $this->remove($key);

        return $value;

    }

    public function toArray() {

        if ($this->backend->can('array'))
            return $this->backend->toArray();

        return FALSE;

    }

    /**
     * Disable the use of cache
     *
     * This allows a cache object to be disabled but still be used without having to modify existing application
     * code.  The application can still call the get() method but it will always return false, simulating a negative
     * cache response.  Calling set() will still have an affect however.
     */
    public function on(){

        $this->use_cache = true;

    }

    /**
     * Enable the use of cache
     *
     * Cache is enabled by default.  This is to allow cache to be re-enabled after calling off().
     *
     * This method can also be used to force cache back on after being disabled by a "Pragma: no-cache" header
     * in the case where the use_pragma setting is enabled (which is the default).
     */
    public function off(){

        $this->use_cache = false;

    }

    /*
     * MAGIC METHOD FOR DIRECT ACCESS
     */
    public function __isset($key) {

        return $this->has($key);

    }

    public function &__get($key) {

        return $this->get($key);

    }

    public function __set($key, $value) {

        return $this->set($key, $value);

    }

    public function __unset($key) {

        return $this->remove($key);

    }

    /*
     * ARRAYACCESS METHODS
     */
    public function offsetExists($offset) {

        return $this->has($offset);

    }

    public function offsetGet($offset) {

        return $this->get($offset);

    }

    public function offsetSet($offset, $value) {

        return $this->set($offset, $value);

    }

    public function offsetUnset($offset) {

        return $this->remove($offset);

    }

}
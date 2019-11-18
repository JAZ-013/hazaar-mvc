<?php

/**
 * @file        Hazaar/Cache/Backend/Shm.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The PHP-SHM (shared memory) cache backend.
 *
 * @detail This is the absolute fastest caching backend.
 * 
 * There are no configuration options required for the backend.
 *
 * @since 2.2.0
 *
 */
class Shm extends \Hazaar\Cache\Backend {

    protected $weight = 0;

    private $namespace;

    private $shm_index;

    private $shm;

    private $index = array();

    static public function available(){

        return function_exists('shm_attach');

    }

    function init($namespace) {

        $this->namespace = $namespace;

        $this->addCapabilities('store_objects', 'keepalive', 'array');

        $shm_index = shm_attach(1);

        if(shm_has_var($shm_index, 0)){

            $namespaces = shm_get_var($shm_index, 0);

        }else{

            $namespaces = array(0 => 'index');

            shm_put_var($shm_index, 0, $namespaces);

        }

        if(!($key = array_search($namespace, $namespaces))){

            $key = count($namespaces) + 1;

            $namespaces[$key] = $namespace;

            shm_put_var($shm_index, 0, $namespaces);

        }

        $this->shm = shm_attach($key);

        if(!is_resource($this->shm))
            throw new \Exception('shm_attach() failed.  did not return resource.');

        if(shm_has_var($this->shm, 0))
            $this->index = shm_get_var($this->shm, 0);

    }

    function close(){

        if(is_resource($this->shm))
            shm_detach($this->shm);

    }

    public function has($key) {

        if(!($index = array_search($key, $this->index)))
            return false;

        if(!shm_has_var($this->shm, $index))
            return false;

        $info = shm_get_var($this->shm, $index);

        if(array_key_exists('expire', $info) && $info['expire'] < time())
            return false;

        return $info['data'];

    }

    private function info($index){

        $info = shm_get_var($this->shm, $index);

        if(array_key_exists('expire', $info)){

            if($info['expire'] < time())
                return false;

            $info['expire'] = time() + $info['timeout'];

            shm_put_var($this->shm, $index, $info);

        }

        return $info;

    }

    public function get($key) {

            if(!($index = array_search($key, $this->index)))
                return false;

        $info = $this->info($index);

        return $info['data'];

    }

    public function set($key, $value, $timeout = NULL) {

        if(!($index = array_search($key, $this->index))){

            $index = count($this->index) + 1; //Plus one because we can't use zero as it's our index.

            $this->index[$index] = $key;

            shm_put_var($this->shm, 0, $this->index);

        }

        $info = array('data' => $value);

        if($timeout !== null){

            $info['timeout'] = $timeout;

            $info['expire'] = time() + $timeout;

        }

        return shm_put_var($this->shm, $index, $info);

    }

    public function remove($key) {

        if(!($index = array_search($key, $this->index)))
            return false;

        unset($this->index[$index]);

        shm_put_var($this->shm, 0, $this->index);

        return shm_remove_var($this->shm, $index);

    }

    public function clear() {

        return shm_remove($this->shm);

    }

    public function toArray(){

        $array = array();

        foreach($this->index as $index =>  $key){

            if($info = $this->info($index))
                $array[$key] = $info['data'];

        }

        return $array;
        
    }

}
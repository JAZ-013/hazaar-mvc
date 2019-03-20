<?php

/**
 * @file        Hazaar/Cache/Backend/File.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The file cache backend.
 *
 * @detail This backend will cache data to the filesystem.  It is basically a "works all the time" backend that is available
 * regardless of what modules are installed or server software is available.  It is very handing for using caching on
 * systems where no other cache backends are available.
 *
 * Available config options:
 *
 * * cache_dir - The directory to store cache files in.  Default is to use a 'cache' directory in the application .runtime directory.
 * * file_prefix - This is an optional prefix to apply to the cache files.  Useful if you want to separate your cache files.  Default: no prefix
 * * use_zlib - Enable or disable zlib compression on the cache files.  This can slow things down quite a bit more, but is useful when you
 * * encode_fs - Encodes the filesystem files using an md5 hash to obscure file namespaces on disk
 * are caching very large things.  I wouldn't bother using it under normal circumstances.  Default: false.
 *
 * @since 1.0.0
 */
class File extends \Hazaar\Cache\Backend {

    private   $zlib   = false;

    protected $weight = 4;

    private   $namespace;

    private   $cache_dir;

    private   $store;

    private   $timeout_file;

    private   $timeout;

    private   $local = array();

    static public function available(){

        return true;

    }

    function init($namespace) {

        $this->namespace = $namespace;

        if($app = \Hazaar\Application::getInstance())
            $cache_dir = $app->runtimePath('cache', true);
        else
            $cache_dir = sys_get_temp_dir();

        $this->configure(array(
            'cache_dir'   => $cache_dir,
            'file_prefix' => NULL,
            'use_zlib'    => false,
            'encode_fs'   => false
        ));

        $cache_dir = $this->options->cache_dir
            . ( ($this->options->file_prefix ) ? DIRECTORY_SEPARATOR . $this->options->file_prefix : null )
            . DIRECTORY_SEPARATOR;

        if(!file_exists($cache_dir))
            mkdir($cache_dir);

        //Open the B-Tree database file
        $this->store = new \Hazaar\Btree($cache_dir . ($this->options->encode_fs ? md5($this->namespace) : $this->namespace) . '.db');

        $this->addCapabilities('store_objects', 'expire_val', 'array');

        if(in_array('zlib', get_loaded_extensions())){

            $this->zlib = true;

            $this->addCapabilities('compress');

        }

        if(!$this->options->encode_fs)
            $this->addCapabilities('array');

        //If the lifetime value is greater than 0 then we support namespace timeouts.
        if($this->options->lifetime > 0){

            //$this->timeout_file = $this->cache_dir . DIRECTORY_SEPARATOR . '.timeout';

            $this->addCapabilities('expire_ns', 'keepalive');

            //If a timeout exists, load it and check if we need to drop the namespace.
            if(!($timeout = $this->store->get('__namespace_timeout')))
                $timeout = 0;

            //If the namespace has expired, drop it
            if(time() >= $timeout){

                $this->clear(true);

                $this->timeout = time() + $this->options->lifetime;

            }

        }

    }

    /**
     * Store the namespace timeout in the cache dir timeout file.
     *
     * This should only happen if a keepalive() has been called.
     */
    function __destruct(){

        if($this->timeout > 0)
            $this->store->set('__namespace_timeout', $this->timeout);

    }

    private function keepalive(){

        if($this->options->keepalive === true && $this->options->lifetime > 0)
            $this->timeout = time() + $this->options->lifetime;

    }


    /**
     * Load the key value from storage
     *
     * This should only happen once and then it will be stored in memory and only written again when changed.
     *
     * @param mixed $key The value key
     *
     * @throws Exception\NoZlib
     *
     * @return mixed
     */
    private function load($key, &$cache = null) {

        if(!is_array($cache)){

            if(!array_key_exists($key, $this->local))
                $this->local[$key] = $this->store->get($key);

            $cache =& $this->local[$key];

        }

        $value = "\0";

        if($cache === null)
            return $value;

        $expire = array_key_exists('expire', $cache) ? $cache['expire'] : null;

        if($expire && $expire < time()){

            $this->store->remove($key);

            $this->local[$key] = null;

        }else{

            if(array_key_exists('data', $cache))
                $value = $cache['data'];

            if(ord(substr($value, 0, 1)) === 120) {

                if(! $this->zlib)
                    throw new Exception\NoZlib($key);

                $value = gzuncompress($value);

            }

        }

        $this->keepalive();

        return $value;

    }

    /**
     * Check if a value exists
     *
     * @param mixed $key
     * @return boolean
     */
    public function has($key) {

        return ($this->load($key) !== "\0");

    }

    public function get($key) {

        $value = $this->load($key);

        if($value === "\0")
            return false;

        return $value;

    }

    public function set($key, $value, $timeout = NULL) {

        $this->local[$key] = $value;

        if($this->zlib && $this->options->use_zlib)
            $value = gzcompress($value, 9);

        $data = array('data' => $value);

        if($timeout > 0)
            $data['expire'] = time() + $timeout;

        $this->keepalive();

        return $this->store->set($key, $data);

    }

    public function remove($key) {

        $this->keepalive();

        return $this->store->remove($key);

    }

    public function clear() {

        return $this->store->reset_btree_file();

    }

    public function toArray(){

        $array = array();

        $values = $this->store->range("\x00", "\xff");

        foreach($values as $key => $cache){

            if(substr($key, 0, 2) === '__')
                continue;

            if(($value = $this->load($key, $cache)) !== "\0")
                $array[$key] = $value;

        }

        return $array;

    }

}
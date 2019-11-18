<?php

/**
 * @file        Hazaar/Cache/Backend/Session.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief Session cache backend class
 *
 * @detail The session cache backend class allows access to a PHP session using standard class access methods. It will
 * automatically start a session if one is not already started. Sessions can be namespaced to
 * prevent variable collisions.
 *
 * Available config options:
 *
 * * lifetime - The lifetime to use for cached data. Default: 3600.
 * * session - Any settings to set on the session instance.
 *
 * @since 1.0.0
 */
class Session extends \Hazaar\Cache\Backend {

    private $namespace = NULL;

    private $timeout = 3600;

    private $values = array();

    protected $weight = 3;

    static private $started = false;

    static public function available(){

        return true;

    }

    /**
     * @detail The session backend init method is responsible for maintaining a valid session.
     * If a session
     * has not already been started it will configure and start one automatically. You can specify
     * a namespace for the session. The constructor will also maintain any timeouts for values
     * as per the application configuration.
     *
     * @since 1.0.0
     *
     * @param string $namespace
     *            The namespace to use for this session
     */
    function init($namespace) {

        if (!$namespace)
            throw new \Exception("Bad session cache namespace!");

        $this->namespace = $namespace;

        $this->addCapabilities('store_objects', 'array', 'keepalive');

        /*
         * Grab the application instance so we can configure the session.
         */
        if (($app = \Hazaar\Application::getInstance()) instanceof \Hazaar\Application) {

            /*
             * If we have a session name configured in the application config we can set
             * it now for this application. Otherwise just use the default name
             * specified in the PHP configuration. ie: PHPSESSID
             */
            if ($app->config->has('session')
                && is_array($app->config->session)
                && array_key_exists('name', $app->config->session)
                && $name = $app->config->session['name'])
                session_name($name);

            /*
             * Check if we need to configure a session cache expire time.
             */
            if ($this->options->has('lifetime'))
                $this->timeout = $this->options->lifetime;

            elseif ($app->config->has('session') && $app->config->session->has('timeout'))
                $this->timeout = (int) $app->config->session->timeout;
        }

        /*
         * Start the session if we don't already have one
         */
        if (!session_id()) {

            if(Session::$started == true)
                throw new \Exception('Session already started!');

            /*
             * This is a hack to make sure the session doesn't get cleaned up
             * while we are still using it
             */

            ini_set('session.gc_maxlifetime', $this->timeout * 2);

            ini_set('session.cookie_maxlifetime', $this->timeout * 2);

            session_start();

            Session::$started = true;
        }

        /*
         * Check if we have a session timeout
         */
        if (!isset($_SESSION['session']['created'])) {

            $_SESSION['session']['created'] = time();

        } elseif (isset($_SESSION['session']['last_access'])) {

            if ((time() - $_SESSION['session']['last_access']) > $this->timeout) {

                /*
                 * Reset the session
                 */
                $this->clear();

            }

        }

        $_SESSION['session']['last_access'] = time();

        /*
         * If this is the first load the application base won't be an array
         * so we need to set that in the session first.
         */
        if (!array_key_exists(APPLICATION_BASE, $_SESSION))
            $_SESSION[APPLICATION_BASE] = array();

        if (!(array_key_exists($this->namespace, $_SESSION[APPLICATION_BASE]) && is_array($_SESSION[APPLICATION_BASE][$this->namespace])))
            $_SESSION[APPLICATION_BASE][$this->namespace] = array();

        $this->values = & $_SESSION[APPLICATION_BASE][$this->namespace];

    }

    public function close() {

        session_write_close();

    }

    private function load($key) {

        $value = "\0";

        if (array_key_exists($key, $this->values)) {

            $expire = ake($this->values[$key], 'expire');

            if ($expire !== null && $expire < time())
                unset($this->values[$key]);

            else
                $value = ake($this->values[$key], 'data');
        }

        return $value;

    }

    /**
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function has($key) {

        return ($this->load($key) !== "\0");

    }

    /**
     * Return the value with key $key, optionally setting a default in the process.
     *
     * If $default is supplied and no value for $key is currently set in the session then
     * the default value will be set in the session and then returned.
     *
     * @param mixed $key
     *            The key name of the data field to return.
     * @param mixed $default
     *            An optional default value to set and return if the field currently has no value.
     *
     * @return mixed
     */
    public function &get($key) {

        $value = $this->load($key);

        if ($value === "\0")
            $value = FALSE;

        return $value;

    }

    public function set($key, $value, $timeout = NULL) {

        $cache = array(
            'data' => $value
        );

        if ($timeout > 0)
            $cache['expire'] = time() + $timeout;

        $this->values[$key] = $cache;

    }

    public function remove($key) {

        unset($this->values[$key]);

    }

    /**
     * @detail Clears all values from all applications.
     *
     * This equates to a full session reset.
     *
     * @warning Use this wisely as it will affect other applications using the same session.
     *
     *
     * @since 1.0.0
     */
    public function clear() {

        if (ini_get("session.use_cookies")) {

            $params = session_get_cookie_params();

            setcookie(session_name(), null, time() - 3600, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);

        }

        session_destroy();

        session_start();

        $_SESSION['session']['created'] = time();

        $_SESSION[APPLICATION_BASE][$this->namespace] = array();

        return true;

    }

    public function extend() {

        $args = func_get_args();

        foreach($args as $arg)
            $_SESSION[APPLICATION_BASE][$this->namespace] = array_merge($_SESSION[APPLICATION_BASE][$this->namespace], $arg);

    }

    public function toArray() {

        $values = array();

        foreach($_SESSION[APPLICATION_BASE][$this->namespace] as $key => $item){

            if(array_key_exists('expire', $item) && $item['expire'] <= time()){

                unset($_SESSION[APPLICATION_BASE][$this->namespace][$key]);

                continue;

            }

            $values[$key] = $item['data'];

        }

        return $values;

    }

}
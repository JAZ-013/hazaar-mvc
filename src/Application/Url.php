<?php
/**
 * @file        Hazaar/Application/Url.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Application;

/**
 * @brief       Generate a URL relative to the application
 *
 * @detail      This is the base method for generating URLs in your application.  URLs generated directly from here are
 *              relative to the application base path.  For URLs that are relative to the current controller see
 *              Controller::url()
 *
 *              Parameters are dynamic and depend on what you are trying to generate.
 *
 *              For examples see "Generating URLs":http://www.hazaarmvc.com/docs/the-basics/generating-urls in the
 *              Hazaar MVC support documentation.
 *
 * @since       1.0.0
 *
 */
class Url {

    public $controller;

    public $method;

    public $params;

    public $path;

    public $hash;

    public $base_path;

    private $encoded = false;

    public static $base = null;

    public static $rewrite = true;

    public static $aliases;

    function __construct() {

        /*
         * Figure out our controller/method combo
         */
        if(count($args = func_get_args()) > 0) {

            /*
             * Pull out the controller/method from our arguments
             */
            if(is_array($args[0]) && count($args[0]) > 1) {

                list($this->controller, $this->method) = $args[0];

            } elseif(count($args) == 2) {

                list($this->controller, $this->method) = $args;

                if(is_array($this->method)) {

                    $this->params = $this->method;

                    $this->method = NULL;

                }

            } elseif(count($args) >= 3) {

                list($this->controller, $this->method, $this->params) = $args;

                if(count($args) == 4)
                    $this->base_path = $args[3];

            } else {

                $this->controller = $args[0];

            }

            if(is_array($this->method))
                $this->method = implode('/', $this->method);

            /*
             * Here we pull out and check any parameters
             */
            $m_params = NULL;

            if(preg_match('/\?/', $this->controller)) {

                if($this->method)
                    throw new \Exception('Parameters are not allowed in the controller when a method has been set!');

                list($this->controller, $m_params) = explode('?', $this->controller);

            } elseif(count($mtest = explode('?', $this->method)) > 1) {

                list($this->method, $m_params) = $mtest;

            }

            if($m_params)
                parse_str($m_params, $this->params);

            /*
             * Sanitize the controller/method
             *
             * Here we trim any whitespace, set any default controllers if
             * needed, or strip controllers if they are defaults and we
             * don't have a method.
             */
            $this->controller = trim($this->controller);

            $this->method = trim($this->method);

            /*
             * Grab the default controller ready for testing
             */
            $app = \Hazaar\Application::getInstance();

            $default = strtolower($app->config->app['defaultController']);

            if(($this->method && ! $this->controller) || (! $this->controller && $this->params)) {

                $this->controller = $default;

            } elseif(! $this->method && $this->controller == $default && ! $this->params) {

                $this->controller = NULL;

            }

        }

    }

    private function renderPath(){

        $controller = $this->controller;

        if(Url::$aliases instanceof \Hazaar\Map){

            foreach(Url::$aliases as $alias_controller => $alias){

                $pos = strpos($alias, '/');

                $target = ($pos > 0) ? substr($alias, 0, $pos) : $alias;

                if(strcasecmp($target, $controller) === 0){

                    $controller = $alias_controller;

                    break;

                }

            }

        }

        return $controller . ($this->method ? '/' . $this->method : NULL);

    }

    /**
     * Write the URL as a string
     *
     * @param boolean $inc_path Include the URL path part.  Defaults to true.  Allows just the host part to be returned.
     *
     * @param array $params Override the default params with parameters in this array.
     *
     * @param boolean $encode Encode the URL as a Hazaar MVC encoded query string URL.
     *
     * @return      string The resulting URL based on the constructor arguments.
     */
    public function renderObject($inc_path = TRUE, $params = NULL, $encode = false) {

        $path = ($this->base_path ? $this->base_path . '/' : null);

        if(!is_array($params))
            $params = array();

        if(Url::$rewrite)
            $path .= $this->renderPath();

        elseif($this->controller)
            $params[Request\Http::$pathParam] = $this->renderPath();

        if(is_array($this->params))
            $params = array_merge($this->params, $params);

        if(Url::$base){

            $url = trim(Url::$base);

            if(substr($url, -1, 1) == '/') $url = substr($url, 0, strlen($url) - 1);

        } else {

            /*
             * Figure out the hostname and protocol
             */
            $host = ake($_SERVER, 'HTTP_HOST', 'localhost');

            if(strpos($host, ':') === false
                && array_key_exists('SERVER_PORT', $_SERVER)
                && $_SERVER['SERVER_PORT'] != 80
                && $_SERVER['SERVER_PORT'] != 443)
                $host .= ':' . $_SERVER['SERVER_PORT'];

            $proto = ((ake($_SERVER, 'SERVER_PORT') == 443) ? 'https' : 'http');

            $url = $proto . '://' . $host;

        }

        if($inc_path) {

            $url .= \Hazaar\Application::path($path);

            if($this->path) {

                if(substr($this->path, 0, 1) != '/')
                    $this->path = '/' . $this->path;

                $url .= $this->path;

            }

            if(count($params) > 0){

                $params = http_build_query($params);

                if($encode)
                    $params = Request\Http::$queryParam . '=' . base64_encode($params);

                $url .= '?' . $params;

            }

        }

        if($this->hash)
            $url .= '#' . $this->hash;

        return $url;

    }

    /**
     * @detail      Magic method to output a string URL.
     */
    public function __tostring() {

        return $this->toString();

    }

    /**
     * Write the URL as a string
     *
     * This method optionally takes an array to use to filter any placeholder parameters.  Parameters support special
     * placholder values that are prefixed with a '$', such as $name.  The actual value is then taken from the array
     * supplied to this method and replaced in the output.  This allows a single URL object to be used multiple times
     * and it's parameters changed
     *
     * ## Example:
     *
     * <code>
     * $url = new \Hazaar\Application\Url('controller', 'action', array('id' => '$id'));
     * echo $url->toString(array('id' => 1234));
     * </code>
     *
     * This will output something like: @http://localhost/controller/action?id=1234@
     *
     * @param boolean $values
     *
     * @return      string The resulting URL based on the constructor arguments.
     */
    public function toString($values = NULL) {

        if(is_array($values)) {

            $params = array();

            foreach($this->params as $key => $value) {

                if(preg_match('/\$(\w+)/', $value, $matches))
                    $value = ake($values, $matches[1]);

                $params[$key] = $value;

            }

            return $this->renderObject(TRUE, $params, $this->encoded);

        }

        return $this->renderObject(true, null, $this->encoded);

    }

    /**
     * Set the HTTP request parameters on the URL
     *
     * @param $params
     *
     */
    public function setParams($params, $merge = FALSE) {

        if($merge && is_array($this->params))
            $this->params = array_merge($this->params, $params);

        else
            $this->params = $params;

    }

    public function encode($encode = true){

        $this->encoded = $encode;

        return $this;

    }

}


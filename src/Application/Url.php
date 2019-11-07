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
 *              For examples see [Generating URLs](/basics/urls.md) in the
 *              Hazaar MVC support documentation.
 *
 * @since       1.0.0
 *
 */
class Url {

    public $path;

    public $params;

    public $hash;

    public $base_path;

    private $encoded = false;

    public static $base = null;

    public static $rewrite = true;

    public static $aliases;

    function __construct() {

        if(!func_num_args() > 0)
            return;

        $parts = array();

        $params = array();

        foreach(\func_get_args() as $part){

            if(is_array($part) || $part instanceof \stdClass){

                $params = (array)$part;

                break;

            }

            $part_parts = (strpos($part, '/') === false) ? array($part) : explode('/', $part);

            foreach($part_parts as $part_part){

                if(strpos($part_part, '?') !== false){

                    list($part_part, $part_params) = explode('?', $part_part, 2);

                    parse_str($part_params, $part_params);

                    $params = array_merge($params, $part_params);

                }

                if(!($part_part = strtolower(trim($part_part))))
                    continue;

                $parts[] = $part_part;

            }

        }

            /*
            * Grab the default controller ready for testing
            */
        $app = \Hazaar\Application::getInstance();

        $default = strtolower($app->config->app['defaultController']);

        if(count($parts) === 1 && $parts[0] === $default)
            $parts = array();

        if(count($parts) > 0)
            $this->path = implode('/', $parts);

        $this->params = $params;

    }

    /**
     * Write the URL as a string
     *
     * @param array $params Override the default params with parameters in this array.
     *
     * @param boolean $encode Encode the URL as a Hazaar MVC encoded query string URL.
     *
     * @return string The resulting URL based on the constructor arguments.
     */
    private function renderObject($params = null, $encode = false) {

        $path = ($this->base_path ? $this->base_path . '/' : null);

        if(!is_array($params))
            $params = array();

        if(Url::$rewrite && $encode !== true)
            $path .= $this->path;

        elseif($this->path)
            $params[Request\Http::$pathParam] = $this->path;

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

        $url .= \Hazaar\Application::path($path);

        if(count($params) > 0){

            $params = http_build_query($params);

            if($encode)
                $params = Request\Http::$queryParam . '=' . base64_encode($params);

            $url .= '?' . $params;

        }

        if($this->hash)
            $url .= '#' . $this->hash;

        return $url;

    }

    /**
     * Magic method to output a string URL.
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
     * ```php
     * $url = new \Hazaar\Application\Url('controller', 'action', array('id' => '$id'));
     * echo $url->toString(array('id' => 1234));
     * ```
     *
     * This will output something like:
     *
     * ```
     * http://localhost/controller/action?id=1234
     * ```
     *
     * @param boolean $values
     *
     * @return      string The resulting URL based on the constructor arguments.
     */
    public function toString($values = NULL) {

        $params = array();

        if(is_array($values)) {

            foreach($this->params as $key => $value) {

                if(preg_match('/\$(\w+)/', $value, $matches))
                    $value = ake($values, $matches[1]);

                $params[$key] = $value;

            }

        }

        return $this->renderObject($params, $this->encoded);

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

    /**
     * Toggle encoding of the URL
     * 
     * This will enable a feature that will encode the URL with a serialised base64 parameter list so that the path and parameters are obscured.
     * 
     * This is NOT a security feature and merely obscures the exact destination of the URL using standard reversible encoding functions that
     * "normal" people won't understand.  It can also make your URL look a bit 'tidier' or 'more professional' by making the parameters
     * look weird. ;)
     * 
     * @param bool $encode Boolean to enable/disable encoding.  Defaults to TRUE.
     */
    public function encode($encode = true){

        $this->encoded = $encode;

        return $this;

    }

}


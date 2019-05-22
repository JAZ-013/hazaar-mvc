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

    protected $controller;

    protected $action     = 'index';

    protected $dispatched = FALSE;

    protected $params     = array();

    protected $exception;

    protected $config;

    /**
     * The original path excluding the application base path
     */
    private $base_path;

    /**
     * The path without the controller reference in it
     */
    private $raw_path;

    /**
     * The path excluding the controller and action references
     */
    private $path;

    function __construct() {

        $args = func_get_args();

        if(! ($this->config = array_shift($args)) instanceof Config)
            throw new \Exception('Argument one of the Request constructor MUST be an Application\Config object!');

        if(method_exists($this, 'init'))
            $this->base_path = call_user_func_array(array($this,'init'), $args);

        if($this->base_path)
            $this->evaluate($this->base_path);

    }

    /**
     * @detail      Parses a request URL string and turns it into a controller name, action name, and argument list.
     * This
     *              is essentially the core method of Hazaar that decides what to execute based on
     */
    public function evaluate($string) {

        $this->action = 'index';

        $this->path = null;

        //First, split off any query string
        $url = parse_url($string);

        if(array_key_exists('path', $url)){

            //If there is a fragment here, it is almost entirely likely to be part of the filename, so re-attach it.
            if(array_key_exists('fragment', $url))
                $url['path'] .= '#' . $url['fragment'];

            $nodes = explode('/', $url['path']);

            /* Pull out the first path node and use it to find the controller */
            if(count($nodes) > 0)
                $this->setControllerName(array_shift($nodes));

            foreach($nodes as &$node){

                if(substr($node, 0, 1) == '$'){

                    $mod = substr($node, 1);

                    if($mod === 'path')
                        $node = $this->raw_path;

                }

            }

            /* Keep what we have left so far as the RAW path */
            $this->raw_path = implode('/', $nodes);

            if(!($pos = strpos($this->raw_path, '/')))
                $pos = strlen($this->raw_path);

            if(count($nodes) > 0)
                $this->setActionName(substr($this->raw_path, 0, $pos));

            /* Keep the rest as a path off the controller */
            if($pos < strlen(trim($this->raw_path, '/')))
                $this->path = substr($this->raw_path, $pos + 1);

        }

        if(array_key_exists('query', $url)){

            parse_str($url['query'], $params);

            $this->params = array_merge($this->params, $params);

        }

    }

    public function processRoute() {

        /*
         * Find the controller we are trying to load
         */
        if(! $this->getControllerName()) {

            if(! $default = $this->config->app['defaultController']) {

                return FALSE;

            }

            $this->setControllerName($default);

        }

        $result = TRUE;

        $route = APPLICATION_PATH . DIRECTORY_SEPARATOR . ake($this->config->app->files, 'route', 'route.php');

        if(file_exists($route)) {

            $router = new Router($route);

            $result = $router->exec($this->getControllerName());

        }

        /*
         * Evaluate the router result.  If the result is a string then we evaluate it.  If it is true, then we are safe
         * to
         * evaluate any static routes.  If it is false, then we have been asked not to evaluate any further.
         */
        if(is_string($result)) {

            $this->evaluate($result);

        } elseif($result === TRUE && $this->config->app->has('alias') && $this->config->app->alias->has($this->getControllerName())) {

            $alias = $this->config->app->alias[$this->getControllerName()];

            $this->evaluate($alias);

        }

        return TRUE;

    }

    /**
     * Return the request path suffix.
     *
     * This is the path that comes after the controller and action path elements.  Take the
     * path /myapp/public/index/test/foo/bar for example.  In this case this method would return '/foo/bar'.
     *
     * @param mixed $strip_filename If true, this will cause the function to return anything before the last '/'
     *                              (including the '/') which is the fulle directory path name. (Similar to dirname()).
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
     * @detail      Return the complete raw request URI relative to the application path.  That is the full path
     *              including the controller and action elements.  Take the path /myapp/public/index/test/foo/bar for
     *              example.  In this case this method would return '/index/test/foo/bar'.
     *
     * @since       1.0.0
     *
     * @return      string The raw request URI relative to the application path.
     */
    public function getRawPath() {

        return $this->raw_path;

    }

    public function getBasePath() {

        return $this->base_path;

    }

    public function getControllerName() {

        return $this->controller;

    }

    public function setControllerName($name) {

        $this->controller = $name;

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
     * @return boolean True if the value is set, False otherwise.
     */
    public function has($keys) {

        /**
         * If the parameter is an array, make sure all of the keys exist before returning true
         */

        if(! is_array($keys))
            $keys = array($keys);

        foreach($keys as $key) {

            if(! array_key_exists($key, $this->params)) {

                return FALSE;

            }

        }

        return TRUE;

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
     * @param array $filter Only include parameters with keys specified in this filter.
     *
     * @return array
     */
    public function getParams($filter = NULL) {

        if($filter === NULL)
            return $this->params;

        if(! is_array($filter))
            $filter = array($filter);

        return array_intersect_key($this->params, array_flip($filter));

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

    public function setActionName($name) {

        $this->action = $name;

    }

    public function getActionName() {

        return $this->action;

    }

    public function resetAction() {

        $this->action = NULL;

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

    /**
     * Detect if a request originated on a mobile device
     *
     * This method will return true to indicate that the requesting device is a mobile browser.  It uses the freely
     * available
     * script from detectmobilebrowsers.com
     *
     * @return boolean True to indicate requesting device is a mobile browser, false otherwise.
     */
    public function isMobileDevice() {

        $useragent = $_SERVER['HTTP_USER_AGENT'];

        $ret = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));

        return $ret;

    }

    public function method(){

        return $_SERVER['REQUEST_METHOD'];

    }

    public function isGET(){

        return ($this->method == 'POST');

    }

    public function isPOST(){

        return ($this->method == 'POST');

    }

    public function isPUT(){

        return ($this->method == 'PUT');

    }

    public function isDELETE(){

        return ($this->method == 'DELETE');

    }

    public function referer(){

        return ake($_SERVER, 'HTTP_REFERER');

    }
}
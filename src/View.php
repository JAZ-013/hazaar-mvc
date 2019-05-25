<?php

/**
 * @file        Hazaar/View/View.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar;

class View {

    private $name;

    private $_viewfile;

    protected $_data = array();

    protected $_scripts = array();

    /**
     * View Helpers
     */
    protected $_helpers = array();

    /**
     * Array for storing names of initialised helpers so we only initialise them once
     */
    private $_helpers_init = array();

    private $_rendering = FALSE;

    protected $_methodHandler;

    public function __construct($view, $init_helpers = array()) {

        $this->load($view);

        $this->_helpers['application'] = Application::getInstance();

        if (is_array($init_helpers) && count($init_helpers) > 0){

            foreach($init_helpers as $helper)
                $this->addHelper($helper);

        }

        if ($this->application->config->has('view')) {

            if ($this->application->config->view->has('helper')) {

                $load = $this->application->config->view->helper->load;

                if (!Map::is_array($load))
                    $load = new Map(array($load));

                $helpers = new Map();

                foreach($load as $key => $helper) {

                    //Check if the helper is in the old INI file format and parse it if it is.
                    if (!Map::is_array($helper)){

                        if(preg_match('/(\w*)\[(.*)\]/', trim($helper), $matches)) {

                            $key = $matches[1];

                            $helper = array_unflatten($matches[2], '=', ',');

                            //Fix the values so they are the correct types
                            foreach($helper as &$arg) {

                                if($arg = trim($arg)) {

                                    if (in_array(strtolower($arg), array('yes','no','true','false','on','off'))) {

                                        $arg = boolify($arg);

                                    } elseif (is_numeric($arg)) {

                                        if (strpos($arg, '.') === FALSE)
                                            settype($arg, 'int');
                                        else
                                            settype($arg, 'float');

                                    }

                                }

                            }

                            $helpers[$key]->fromDotNotation($helper);

                            //If there is no config and it is just the helper name, just convert it to the new format
                        }else{

                            $helpers[$helper] = array();

                        }

                    }else{

                        $helpers[$key] = $helper;

                    }

                }

                foreach($helpers as $helper => $args)
                    $this->addHelper($helper, $args->toArray());

            }

        }

    }

    static function getViewPath($view, &$name){

        $viewfile = null;

        $parts = pathinfo($view);

        $name = (($parts['dirname'] !== '.') ? $parts['dirname'] . '/' : '') . $parts['filename'];

        $type = FILE_PATH_VIEW;

        /*
         * If the name begins with an @ symbol then we are trying to load the view from a
         * support file path, not the application path
         */
        if (substr($view, 0, 1) == '@') {

            $view = substr($view, 1);

            $type = FILE_PATH_SUPPORT;

        }else{

            $view = $name;

        }

        if(array_key_exists('extension', $parts)){

            $viewfile = Loader::getFilePath($type, $view . '.' . $parts['extension']);

        }else{

            $extensions = array('phtml', 'tpl');

            foreach($extensions as $extension){

                if($viewfile = Loader::getFilePath($type, $view . '.' . $extension))
                    break;

            }

        }

        return $viewfile;

    }

    public function load($view) {

        if(Loader::isAbsolutePath($view)){

            $this->_viewfile = $view;

        }else{

            $this->_viewfile = View::getViewPath($view, $this->name);

            if (!$this->_viewfile)
                throw new \Exception("File not found or permission denied accessing view '{$this->name}'.");

        }

    }

    public function getName() {

        return $this->name;

    }

    public function getViewFile() {

        return $this->_viewfile;

    }

    public function __get($helper) {

        return $this->get($helper);

    }

    public function get($helper, $default = NULL) {

        if (array_key_exists($helper, $this->_helpers))
            return $this->_helpers[$helper];
        elseif (array_key_exists($helper, $this->_data))
            return $this->_data[$helper];

        return $default;

    }

    public function __set($key, $value) {

        $this->set($key, $value);

    }

    public function set($key, $value) {

        $this->_data[$key] = $value;

    }

    public function __isset($key) {

        return $this->has($key);

    }

    public function has($key) {

        return array_key_exists($key, $this->_data);

    }

    public function __unset($key) {

        $this->unset($key);

    }

    public function remove($key) {

        unset($this->_data[$key]);

    }

    public function populate($array) {

        $this->_data = $array;

    }

    public function extend(array $array) {

        $this->_data = array_merge($this->_data, $array);

    }

    public function getData() {

        return $this->_data;

    }

    /**
     * Registers a controller on the view for method callbacks.
     *
     * @param
     *            $controller
     *
     * @deprecated Replaced by Hazaar\View::registerMethodHandler().
     */
    public function registerController($controller) {

        $this->registerMethodHandler($controller);

    }

    /**
     * Registers an object on the view for method callbacks.
     *
     * @throws \Exception
     *
     * @param
     *            $handler
     */
    public function registerMethodHandler($handler) {

        if (! is_object($handler))
            throw new \Exception('Error trying to register handler that is not an object.');

        $this->_methodHandler = $handler;

    }

    /*
     * Method router. Calls any unknown methods back on the controller
     */
    public function __call($method, $args) {

        if (! $this->_methodHandler)
            throw new \Exception('No method handler defined!');

        if (method_exists($this->_methodHandler, $method)) {

            return call_user_func_array(array(
                $this->_methodHandler,
                $method
            ), $args);

        } elseif (array_key_exists($method, $this->_helpers) && method_exists($this->_helpers[$method], '__default')) {

            return call_user_func_array(array(
                $this->_helpers[$method],
                '__default'
            ), $args);
        }

        throw new \Exception("Method not found calling " . get_class($this->_methodHandler) . ":$method()");

    }

    /*
     * Dealing with Helpers
     */
    public function addHelper($helper, $args = array(), $alias = null) {

        if(is_array($helper)) {

            foreach ($helper as $alias => $h)
                self::addHelper($h, array(), $alias);

        } elseif(is_object($helper)) {

            if (! $helper instanceof View\Helper)
                return NULL;

            if($alias === null)
                $alias = strtolower($helper->getName());

            $this->_helpers[$alias] = $helper;

        } elseif($helper !== null) {

            if($alias === null)
                $alias = strtolower($helper);

            if(! array_key_exists($alias, $this->_helpers)) {

                $class = '\\Hazaar\\View\\Helper\\' . ucfirst($helper);

                $obj = new $class($this, $args);

                $this->_helpers[$alias] = $obj;

            } else {

                if (($obj = $this->_helpers[$alias]) instanceof View\Helper)
                    $obj->extendArgs($args);

            }

        }

    }

    public function hasHelper($helper) {

        return array_key_exists($helper, $this->_helpers);

    }

    public function getHelpers() {

        return array_keys($this->_helpers);

    }

    public function removeHelper($helper){

        if(array_key_exists($helper, $this->_helpers))
            unset($this->_helpers[$helper]);

    }

    public function &getHelper($key) {

        if (array_key_exists($key, $this->_helpers))
            return $this->_helpers[$key];

        return NULL;

    }

    public function initHelpers() {

        foreach ($this->_helpers as $helper) {

            if ($helper instanceof \Hazaar\View\Helper) {

                $this->_priority = 0;

                $name = get_class($helper);

                if (! in_array($name, $this->_helpers_init)) {

                    $helper->initialise();

                    $this->_helpers_init[] = $name;

                }

            }

        }

        $this->_priority = 0;

        return TRUE;

    }

    public function runHelpers() {

        foreach ($this->_helpers as $helper) {

            if ($helper instanceof \Hazaar\View\Helper) {

                $this->_priority = 0;

                $helper->run($this);

            }

        }

        $this->_priority = 0;

        return TRUE;

    }

    /*
     * Rendering the view
     */
    public function render() {

        if ($this->_rendering)
            return $this->__call('render', func_get_args());

        $this->_rendering = TRUE;

        $output = '';

        $parts = pathinfo($this->_viewfile);

        if(ake($parts, 'extension') == 'tpl'){

            $template = new File\Template\Smarty($this->_viewfile);

            $template->registerFunctionHandler($this);

            $template->registerFunctionHandler($this->_methodHandler);

            $output = $template->render($this->_data);

        }else{

            ob_start();

            if (! ($file = $this->getViewFile()) || ! file_exists($file)) {

                throw new \Exception("View does not exist ($this->name)", 404);
            }

            include ($file);

            $output = ob_get_contents();

            ob_end_clean();

        }

        $this->_rendering = FALSE;

        return $output;

    }

    /*
     * Render a partial view
     */
    public function partial($view, array $data = array()) {

        /*
         * This converts "absolute paths" to paths that are relative to FILE_PATH_VIEW.
         *
         * Relative paths are then made relative to the current view (using it's absolute path).
         */
        $fChar = substr($view, 0, 1);

        if ($fChar == '/')
            $view = substr($view, 1);
        else
            $view = dirname($this->_viewfile) . '/' . $view . '.phtml';

        $output = '';

        if ($partial = new View($view)) {

            $partial->registerMethodHandler($this->_methodHandler);

            $partial->addHelper($this->_helpers);

            $partial->extend($data);

            $output = $partial->render();
        }

        return $output;

    }

    public function setRequiresParam($array) {

        $this->_requires_param = array_merge($this->_requires_param, $array);

    }

    public function script($code) {

        $this->_scripts[] = new Html\Script($code);

    }

    /**
     * Render a partial view multiple times on an array
     *
     * @param mixed $view The partial view to render
     * @param array $data A data array.  Usually multi-dimensional
     * @return string
     */
    public function partialLoop($view, array $data) {

        $output = '';

        foreach ($data as $d)
            $output .= $this->partial($view, $d);

        return $output;

    }

    /**
     * Returns a date string formatted to the current set date format
     *
     * @param mixed $date
     * @return string
     */
    public function date($date) {

        if (! ($date instanceof \Hazaar\Date))
            $date = new Date($date);

        return $date->date();

    }

    /**
     * Return a date/time type as a timestamp string.
     *
     * This is for making it quick and easy to output consistent timestamp strings.
     *
     * @param mixed $value
     * @return string
     */
    static public function timestamp($value) {

        if (! ($value instanceof \Hazaar\Date))
            $value = new Date($value);

        return $value->timestamp();

    }

    /**
     * Return a formatted date as a string.
     *
     * @param mixed $value This can be practically any date type.  Either a \Hazaar\Date object, epoch int, or even a string.
     * @param mixed $format Optionally specify the format to display the date.  Otherwise the current default is used.
     * @return string The nicely formatted datetime string.
     */
    static public function datetime($value, $format = NULL) {

        if (! ($value instanceof \Hazaar\Date))
            $value = new Date($value);

        if ($format)
            return $value->format($format);

        return $value->datetime();

    }

    /**
     * Returns 'Yes' or 'No' text based on a boolean value
     *
     * @param mixed $value The boolean value
     * @param mixed $labels Optionally specify your own yes/no text to display
     * @return string
     */
    public function yn($value, $labels = array('Yes','No')) {

        return (boolify($value) ? $labels[0] : $labels[1]);

    }

    /**
     * Display a Gravatar icon for a users email address.
     *
     * @param string $address The email address to show the gravatar image for.
     * @param string $default The default image to use if none is available.  This can be either a URL to a supported image, or one of gravatars built-in
     *                        default images. See the "Default Image" section of https://en.gravatar.com/site/implement/images/ for available options.
     *
     * @return \Hazaar\Html\Img An IMG object so that extra options can be applied.
     */
    public function gravatar($address, $default = null) {

        $url = 'http://www.gravatar.com/avatar/' . md5($address);

        if($default)
            $url .= '?d=' . urlencode($default);

        return new Html\Img($url, $address);

    }

}

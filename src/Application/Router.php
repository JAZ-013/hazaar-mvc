<?php
/**
 * @file        Hazaar/Application/Router.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Application;

class Router {

    private $aliases = array();

    private $file;

    private $route;

    private $controller;

    private $controller_name;

    private $path;

    private $use_default_controller = false;

    private $default_controller;

    static public $internal = array(
        'hazaar'        => 'Hazaar\Controller\Router',
        'media'         => 'Hazaar\Controller\Media',
        'style'         => 'Hazaar\Controller\Style',
        'script'        => 'Hazaar\Controller\Script',
        'favicon.png'   => 'Hazaar\Controller\Favicon',
        'favicon.ico'   => 'Hazaar\Controller\Favicon'
    );

    function __construct(Config $config) {

        if($aliases = $config->app->get('alias'))
            $this->aliases = $aliases->toArray();

        $this->file = APPLICATION_PATH . DIRECTORY_SEPARATOR . ake($config->app->files, 'route', 'route.php');

        $this->use_default_controller = boolify($config->app->useDefaultController);

        $this->default_controller = $config->app->defaultController;

    }

    public function evaluate(Request $request) {

        $this->route = $request->getPath();

        if($this->file && file_exists($this->file))
            include($this->file);

        if($this->route = trim($this->route, '/')){

            $parts = explode('/', $this->route);

            if($this->aliases){

                foreach($this->aliases as $match => $alias){

                    $alias_parts = explode('/', $match);

                    if($alias_parts !== array_slice($parts, 0, count($alias_parts)))
                        continue;

                    $leftovers = array_slice($parts, count($alias_parts));

                    $parts = explode('/', $alias);

                    foreach($parts as &$part){

                        if($part[0] !== '$')
                            continue;

                        if(substr($part, 1) === 'path')
                            $part = implode('/', $leftovers);

                    }

                    break;

                }

            }

            if(array_key_exists($parts[0], self::$internal)){

                $this->controller = array_shift($parts);

            }else{

                $this->controller = $this->findController($parts);

            }

            if(count($parts) > 0)
                $this->path = implode('/', $parts);

            $request->setPath($this->path);

        }else $this->use_default_controller = true;

        //If there is no controller and the default controller is active, search for that too.
        if(!$this->controller && $this->use_default_controller === true){

            $default_parts = explode('/', $this->default_controller);

            $this->controller = $this->findController($default_parts);

            $this->controller_name = $request->popPath();

        }

        return true;

    }

    private function findController(&$parts){

        $index = 0;

        $controller = null;

        $controller_root = \Hazaar\Loader::getFilePath(FILE_PATH_CONTROLLER);

        $controller_path = DIRECTORY_SEPARATOR;

        $controller_index = null;

        foreach($parts as $index => $part){

            $found = false;

            $path = $controller_root . $controller_path;

            $controller_path .= $part . DIRECTORY_SEPARATOR;

            if(is_dir($path . $part)){

                $found = true;

                if(file_exists($controller_root . $controller_path . 'Index.php')){

                    $controller = implode('/', array_slice($parts, 0, $index + 1)) . '/index';

                    $controller_index = $index;

                }

            }

            if(file_exists($path . ucfirst($part) . '.php')){

                $found = true;

                $controller = (($index > 0) ? implode('/', array_slice($parts, 0, $index)) . '/' : null) . $part;

                $controller_index = $index;

            }

            if($found === false)
                break;

        }

        if($controller)
            $parts = array_slice($parts, $controller_index + 1);

        return $controller;

    }
    /**
     * Get the currently set route
     *
     * @return string
     */
    private function get() {

        return $this->route;

    }

    /**
     * Set the route to a specified value
     *
     * @param mixed $route
     */
    private function set($route) {

        $this->route = trim($route, '/');

    }

    public function getController() {

        return $this->controller;

    }

    public function getControllerName(){

        if($this->controller_name)
            return $this->controller_name;

        return $this->controller;

    }

}

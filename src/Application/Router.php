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

    private $defaultController;

    private $useDefaultController = false;

    private $route;

    private $controller;

    private $path;

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

        $this->defaultController = ake($config->app, 'defaultController', 'index');

        $this->useDefaultController = ake($config->app, 'useDefaultController', false);

    }

    public function evaluate(Request $request) {

        $this->route = $request->getPath();

        if($this->aliases && array_key_exists($this->route, $this->aliases))
            $this->route = $this->aliases[$this->route];

        if($this->file && file_exists($this->file))
            include($this->file);

        if(!($this->route = trim($this->route, '/'))){

            $this->controller = $this->defaultController;

            return true;

        }

        $index = 0;

        $controller_root = \Hazaar\Loader::getFilePath(FILE_PATH_CONTROLLER);

        $controller_path = DIRECTORY_SEPARATOR;

        $parts = explode('/', $this->route);

        if(array_key_exists($parts[0], self::$internal)){

            $this->controller = array_shift($parts);

        }else{

            foreach($parts as $index => $part){

                $path = $controller_root . $controller_path;

                //If the path exists as a directory, that becomes our new controller directory
                if(is_dir($path . $part)){

                    $controller_path .= $part . DIRECTORY_SEPARATOR;

                    continue;

                }

                if(file_exists($path . ucfirst($part) . '.php')){

                    $this->controller = (($index > 0) ? implode('/', array_slice($parts, 0, $index)) . '/' : null) . $part;

                    $parts = array_slice($parts, $index + 1);

                    break;

                }elseif($this->useDefaultController){

                    $this->controller = $this->defaultController;

                    break;

                }

                return false;

            }

        }

        //If we still haven't found a controller, look at the last controller path for an index as we may have a path with no args
        if(!$this->controller){

            if(file_exists($controller_root . $controller_path . 'Index.php')){

                $this->controller = implode('/', $parts) . '/index';

                $parts = array();

            }else{

                $this->controller = implode('/', array_slice($parts, 0, $index + 1));

                $parts = array_slice($parts, $index + 1);

            }

        }

        if(count($parts) > 0)
            $this->path = implode('/', $parts);

        $request->setPath($this->path);

        return true;

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

}

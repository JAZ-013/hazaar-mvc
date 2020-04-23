<?php

namespace Hazaar\Console;

class Handler {

    private $modules = array();

    private $libraries = array();

    private $menus = array();

    private $application;

    public function __construct(\Hazaar\Application $application, \Hazaar\Auth\Adapter $auth){

        $this->application = $application;

        $this->auth = $auth;

    }

    public function getUser(){

        return $this->auth->getIdentity();

    }

    public function load(Module $module){

        $name = $module->getName();

        if(array_key_exists($name, $this->modules))
            throw new \Exception('Module ' . $name . ' already loaded!');

        $module->__configure($this);

        $this->modules[$name] = $module;

        $module->load();

    }

    public function loadComposerModules(){

        $installed = ROOT_PATH
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer'
            . DIRECTORY_SEPARATOR . 'installed.json';

        if(file_exists($installed)){

            $this->libraries = json_decode(file_get_contents($installed), true);

            usort($this->libraries, function($a, $b){
                if ($a['name'] == $b['name'])
                    return 0;
                return ($a['name'] < $b['name']) ? -1 : 1;
            });

            foreach($this->libraries as $library){

                if(!(($name = substr(ake($library, 'name'), 18))
                    && ake($library, 'type') == 'library'
                    && $consoleClass = ake(ake($library, 'extra'), 'hazaar-console-class')))
                    continue;

                if(!class_exists($consoleClass))
                    continue;

                if(!($path = $this->getSupportPath($consoleClass)))
                    continue;

                $this->load(new $consoleClass($name, $path . DIRECTORY_SEPARATOR . 'console', $this->application));

            }

        }
        return;

    }

    private function getSupportPath($className = null){

        if(!$className)
            $className = $this->className;

        $reflect = new \ReflectionClass($className);

        $path = dirname($reflect->getFileName());

        while(!file_exists($path . DIRECTORY_SEPARATOR . 'composer.json'))
            $path = dirname($path);

        $libs_path = $path . DIRECTORY_SEPARATOR . 'libs';

        if(file_exists($libs_path))
            return $libs_path;

        return false;

    }

    public function getModules(){

        return $this->modules;

    }

    public function moduleExists($name){

        return array_key_exists($name, $this->modules);

    }

    public function getLibraries(){

        return $this->libraries;

    }

    public function exec(\Hazaar\Controller $controller, $module_name, \Hazaar\Application\Request $request){

        if(!$module_name || $module_name === 'index')
            $module_name = 'app';

        if(!$this->moduleExists($module_name))
            throw new \Hazaar\Exception("Console module '$module_name' does not exist!", 404);

        $module = $this->modules[$module_name];

        if($module->view_path)
            $this->application->loader->setSearchPath(FILE_PATH_VIEW, $module->view_path);

        $module->setBasePath('hazaar/console');

        $module->__initialize($request);

        $response = $module->__run();

        if(!$response instanceof \Hazaar\Controller\Response){

            if(is_array($response)){

                $response = new \Hazaar\Controller\Response\Json($response);

            }else{

                $response = new \Hazaar\Controller\Response\Html();

                $module->_helper->execAllHelpers($module, $response);

            }

        }

        return $response;

    }

    public function getNavItems(){

        return $this->menus;

    }

    public function addMenuItem($module, $label, $url = null, $icon = null, $suffix = null){

        return $this->menus[] = new MenuItem($module, $label, $url, $icon, $suffix);

    }

}
<?php

namespace Hazaar\View;

class Controller extends \Hazaar\Controller {

    private $helper;

    private $method;

    private $params;

    public function __initialize(\Hazaar\Application\Request $request){

        if(!($path = trim($request->getPath(), '/')))
            throw new \Hazaar\Exception('Not allowed!', 400);

        $parts = explode('/', $path);

        $controller = array_shift($parts);

        switch($controller){
            case 'helper':

                $action = array_shift($parts);

                $className = 'Hazaar\\View\\Helper\\' . ucfirst($action);

                if(!class_exists($className))
                    throw new \Hazaar\Exception('Helper class not found!', 404);

                $this->helper = new $className();

                $this->method = array_shift($parts);

                $this->params = array($request);

                break;

            case 'js':
            case 'css':

                $this->helper = new \Hazaar\View\Layout();

                $this->method = 'lib';

                $this->params = array($controller, $request);

                break;

            default:
                throw new \Hazaar\Exception('Method not allowed!', 403);

        }

        $request->setPath(implode('/', $parts));

    }

    public function __run(){

        if(!method_exists($this->helper, $this->method))
            throw new \Hazaar\Exception('Method not found!', 404);

        $response = call_user_func_array(array($this->helper, $this->method), $this->params);

        return $response;

    }

}

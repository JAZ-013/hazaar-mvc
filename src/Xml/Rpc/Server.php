<?php

namespace Hazaar\Xml\Rpc;

abstract class Server extends \Hazaar\Controller {

    protected $request;

    protected $registered_methods = array();

    public function __initialize(\Hazaar\Application\Request $request) {

        $this->request = $request;

        $auto_register = true;

        if(method_exists($this, 'init')) {

            $auto_register = $this->init($request);

        }

        if($auto_register !== false) {

            foreach(get_class_methods($this) as $method) {

                if($method == 'run' || preg_match('/^__/', $method))
                    continue;

                $reflection = new \ReflectionMethod($this, $method);

                if($reflection->isPublic()) {

                    $this->registerMethod($this, $method);

                }

            }

        }

    }

    public function registerMethod($object, $method) {

        $this->registered_methods[$method] = array(
            $object,
            $method
        );

    }

    public function __toString() {

        return get_class($this);

    }

    public function __run() {

        $raw_post_data = file_get_contents("php://input");

        $method = null;

        $result = xmlrpc_decode_request($raw_post_data, $method);

        if(!$method) {

            throw new Exception\InvalidRequest($_SERVER['REMOTE_ADDR']);

        }

        if(!array_key_exists($method, $this->registered_methods)) {

            throw new Exception\MethodNotFound($method);

        }

        $response = call_user_func_array($this->registered_methods[$method], $result);

        return new \Hazaar\Controller\Response\Xml(xmlrpc_encode_request(null, $response));

    }

}

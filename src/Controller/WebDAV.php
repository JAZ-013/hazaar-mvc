<?php

namespace Hazaar\Controller;

use \Hazaar\Controller\Response;
use \Hazaar\Controller\Response\HTTP\OK;

abstract class WebDAV extends Basic {

    private $__propset = '<http://apache.org/dav/propset/fs/1>';

    private $__dav_classes = array(1);

    private $__allowed_methods = array(
        'OPTIONS',
        'GET',
        'HEAD',
        'POST',
        'DELETE',
        'TRACE',
        'PROPFIND',
        'PROPPATCH',
        'COPY',
        'MOVE'
    );

    public function __run(){

        $method = strtolower($this->request->method());

        if(!(in_array(strtoupper($method), $this->__allowed_methods) && method_exists($this, $method)))
            throw new \Exception('Method not supported', 405);

        $response = call_user_func(array($this, $method));

        if(!$response instanceof Response)
            throw new \Exception('Internal Server Error', 500);

        return $response;

    }

    public function options(){

        $response = new OK();

        $response->setContentType('httpd/unix-directory');

        $response->setHeader('DAV', implode(',', $this->__dav_classes));

        $response->setHeader('DAV', $this->__propset, false);

        //Only allow methods that are listed and also have a callable function
        $methods = array_intersect(array_map('strtoupper', get_class_methods($this)), $this->__allowed_methods);

        $response->setHeader('Allow', implode(',', $methods));

        return $response;

    }

    public function propfind(){

        $path = $this->request->getPath();



    }

}
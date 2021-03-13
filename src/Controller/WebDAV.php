<?php

namespace Hazaar\Controller;

use \Hazaar\Controller\Response;
use \Hazaar\Controller\Response\Xml;
use \Hazaar\Controller\Response\HTTP\OK;

use \Hazaar\File;
use \Hazaar\File\Dir;

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
        'MOVE',
        'LOCK'
    );

    protected $manager;

    public function __initialize(\Hazaar\Application\Request $request){

        parent::__initialize($request);

        if($this->__action !== 'index'){

            if(($this->manager = \Hazaar\File\Manager::select($this->__action)) === false)
                throw new \Exception('Unknown media source!', 404);

        }

    }

    public function __runAction(&$action = null){

        $method = strtolower($this->request->method());

        //If the method is not supported, check for a __default handler to pass it off to, or else 405.
        if(!(in_array(strtoupper($method), $this->__allowed_methods) && method_exists($this, $method))){

            if(!method_exists($this, '__default'))
                throw new \Exception('Method not supported', 405);

            return $this->__default($this->name, $this->__action);

        }

        $response = call_user_func(array($this, $method));

        if($this->__stream)
            $response = new Response\Stream($response);

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

        $path = '/'. ltrim($this->request->getPath(), '/');

        $depth = $this->request->hasHeader('Depth') ? $this->request->getHeader('Depth') : 'infinity';

        if(strtolower($depth) === 'infinity')
            throw new \Exception('PROPFIND requests with a Depth of "infinity" are not allowed for ' . $path . '.');

        $depth = intval($depth);

        if($depth !== 0 && $depth !== 1)
            throw new \Exception('Bad request', 400);

        date_default_timezone_set('UTC');

        $object = $this->manager->get($path);

        $xml = new \Hazaar\Xml\Element();

        $xml->addNamespace('D', 'DAV:');

        $ms = $xml->add('D:multistatus');

        $this->writeObjectStatusResponse($object, $ms);

        if($depth === 1 && $object instanceof \Hazaar\File\Dir){

            while($child = $object->read())
                $this->writeObjectStatusResponse($child, $ms);

        }

        $response = new Xml($xml);

        $response->setStatus(207);

        return $response;

    }

    private function writeObjectStatusResponse($object, \Hazaar\Xml\Element $xml){

        $response = $xml->add('D:response');

        $response->addNamespace('lp1', 'DAV:');

        $response->addNamespace('lp2', 'http://apache.org/dav/props/');

        $response->add('D:href', $object->fullpath());

        $propstat = $response->add('D:propstat');

        $prop = $propstat->add('D:prop');

        $type = $prop->add('lp1:resourcetype');

        if($object->is_dir())
            $type->add('D:collection');

        $prop->add('lp1:creationdate', date('c', $object->ctime()));

        $prop->add('lp1:getlastmodified', date('c', $object->mtime()));

        if($object instanceof File){

            $prop->add('lp1:getcontentlength', $object->size());

            $prop->add('lp2:executable', 'F');

        }

        $prop->add('lp1:getetag', '14-5959eb7f60805');

        $prop->add('D:supportedlock');

        $prop->add('D:getcontenttype', $object->mime_content_type());

        $propstat->add('D:status', ($object->exists() ? 'HTTP/1.1 200 OK' : 'HTTP/1.1 404 Not Found'));

        return $response;

    }

    public function proppatch(){

        throw new \Exception(__METHOD__ . ' not currently implemented!', 403);

    }

    public function lock(){

        throw new \Exception(__METHOD__ . ' not currently implemented!', 403);

    }

    public function post(){

        throw new \Exception(__METHOD__ . ' not currently implemented!', 403);

    }

    public function copy(){

        throw new \Exception(__METHOD__ . ' not currently implemented!', 403);

    }

    public function move(){

        throw new \Exception(__METHOD__ . ' not currently implemented!', 403);

    }

    public function delete(){

        throw new \Exception(__METHOD__ . ' not currently implemented!', 403);

    }

    public function trace(){

        throw new \Exception(__METHOD__ . ' not currently implemented!', 403);

    }

}
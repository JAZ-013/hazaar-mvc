<?php

namespace Hazaar\Http;

class WebDAV extends \Hazaar\Http\Client {

    private $authorised  = FALSE;

    private $username;

    private $password;

    private $settings    = array();

    private $allow       = array();

    private $propertyMap = array(
        'collection' => '\Hazaar\Http\WebDAV\Collection',
        'exclusive'  => '\Hazaar\Http\WebDAV\Lock\Scope\Exclusive',
        'write'      => '\Hazaar\Http\WebDAV\Lock\Type\Write'
    );

    function __construct($settings) {

        parent::__construct();

        if(! $settings instanceof \Hazaar\Map)
            $settings = new \Hazaar\Map($settings);

        $this->settings = $settings;

        if(! $this->settings->has('baseuri'))
            throw new \Hazaar\Exception('WebDAV: No baseuri specified');

        $this->settings->baseuri = rtrim($this->settings->baseuri, '/');

        if($this->settings->has('username') && $this->settings->has('password'))
            parent::auth($this->settings->username, $this->settings->password);

        $options = $this->options('/');

        if($options->status != 401) {

            if($options->status != 200)
                throw new \Hazaar\Exception('WebDAV server returned status ' . $options->status . ': ' . $options->name);

            if(! array_key_exists('dav', $options->headers))
                throw new \Hazaar\Exception('Base URI does not support WebDAV protocol.  URI=' . $this->settings->baseuri);

            $this->classes = explode(',', $options->headers['dav'][0]);

            if(! in_array(1, $this->classes))
                throw new \Hazaar\Exception('Server must at least support WebDAV class 1!');

            if(array_key_exists('allow', $options->headers))
                $this->allow = array_map('trim', explode(',', $options->headers['allow']));

            $this->authorised = TRUE;

        }

    }

    public function isAuthorised() {

        return $this->authorised;

    }

    protected function parseProperties(\Hazaar\Xml\Element $dom) {

        $properties = array();

        if($dom->count() > 0) {

            foreach($dom->children() as $propNode) {

                $name = $propNode->getLocalName();

                if($propNode->hasChildren()) {

                    $properties[$name] = $this->parseProperties($propNode);

                } else {

                    $properties[$name] = $propNode->value();

                }

            }

        }

        return $properties;

    }

    protected function parseMultiStatus($xml) {

        if($xml instanceof \Hazaar\Xml\Element) {

            $dom = $xml;

        } else {

            $dom = new \Hazaar\Xml\Element();

            $dom->loadXML($xml);

        }

        $dom->setDefaultNamespace('DAV');

        $result = array();

        $responseList = $dom->child('response');
        if(! is_array($responseList))
            $responseList = array($responseList);

        foreach($responseList as $response) {

            if(! ($href = rawurldecode($response->child('href')->value())))
                throw new \Hazaar\Exception('No HREF in multi-status response');

            $propstat = $response->child('propstat');

            $status = $propstat->child('status')->value();

            list($ver, $status, $message) = explode(' ', $status, 3);

            settype($status, 'int');

            $result[$href][$status] = $this->parseProperties($propstat->child('prop'));

        }

        return $result;

    }

    public function getAbsoluteUrl($url) {

        if(preg_match('/\w\:\/\/.*/', $url))
            return $url;

        return $this->settings->baseuri . '/' . ltrim(implode('/', array_map('rawurlencode', explode('/', trim($url, '/')))), '/');

    }

    public function path($url) {

        $pos = strpos($this->settings->baseuri, '/', 7);

        $basepath = (($pos > 0) ? substr($this->settings->baseuri, $pos) : '/');

        if(substr($url, 0, strlen($basepath)) == $basepath)
            return substr($url, strlen($basepath));

        return FALSE;

    }

    public function options($url) {

        $request = new \Hazaar\Http\Request($this->getAbsoluteurl($url), 'OPTIONS');

        return parent::send($request);

    }

    public function propfind($url, $properties = array(), $depth = 1, $return_response = FALSE, $namespaces = array()) {

        if(!in_array('PROPFIND', $this->allow))
            throw new \Exception('Host does not support PROPFIND command!');

        if(!$this->authorised)
            return FALSE;

        $request = new \Hazaar\Http\Request($this->getAbsoluteUrl($url), 'PROPFIND', 'application/xml; charset="utf-8"');

        $request->setHeader('Depth', $depth);

        $request->setHeader('Prefer', 'return-minimal');

        $xml = new \Hazaar\Xml\Element();

        $xml->addNamespace('d', 'DAV:');

        $propfind = $xml->add('d:propfind');

        if(is_array($namespaces)) {

            foreach($namespaces as $name => $uri)
                $xml->addNamespace($name . ':attr', $uri);

        }

        if(is_array($properties) && count($properties) > 0) {

            $prop = $propfind->add('d:prop');

            foreach($properties as $property) {

                if(strpos($property, ':') === FALSE) {

                    $property = 'd:' . $property;

                }

                $prop->add($property);

            }

        } else {

            $propfind->add('d:allprop');

        }

        $request->setBody($xml->toXML());

        $response = parent::send($request);

        if($response->status != 207)
            throw new \Exception('WebDAV server returned: ' . $response->body(), $response->status);

        if($return_response)
            return $response;

        $result = $this->parseMultiStatus($response->body);

        if($depth === 0) {

            reset($result);

            $result = current($result);

            return isset($result[200]) ? $result[200] : array();

        }

        $newResult = array();

        foreach($result as $href => $statusList) {

            $path = $this->path($href);

            $newResult[$path] = isset($statusList[200]) ? $statusList[200] : array();

        }

        return $newResult;

    }

    public function proppatch($url, array $properties) {

        if(!in_array('PROPPATCH', $this->allow))
            throw new \Exception('Host does not support PROPPATCH command!');

        if(! $this->authorised)
            return FALSE;

        $request = new \Hazaar\Http\Request($this->getAbsoluteUrl($url), 'PROPPATCH', 'application/xml; charset="utf-8"');

        $dom = new \DOMDocument();

        $dom->appendChild($propertyupdate = $dom->createElementNS('DAV:', 'd:propertyupdate'));

        $set = NULL;

        $remove = NULL;

        foreach($properties as $key => $value) {

            if($value === NULL) {

                if(! $remove instanceof \DOMElement) {

                    $propertyupdate->appendChild($remove = $dom->createElement('d:remove'));

                }

                $method = $remove;

            } else {

                if(! $set instanceof \DOMElement) {

                    $propertyupdate->appendChild($set = $dom->createElement('d:set'));

                }

                $method = $set;

            }

            $method->appendChild($prop = $dom->createElement('d:prop'));

            $prop->appendChild($dom->createElement($key, $value));

        }

        $request->setBody($dom->saveXML());

        $response = parent::send($request);

        if($response->status != 207)
            return FALSE;

        return TRUE;

    }

    /**
     * Create a collection object (ie: a directory/folder)
     */
    public function mkcol($url) {

        if(! $this->authorised)
            return FALSE;

        $request = new \Hazaar\Http\Request($this->getAbsoluteUrl($url), 'MKCOL');

        $result = parent::send($request);

        if($result->status == 201)
            return TRUE;

        return FALSE;

    }

    /**
     * Create a new object (ie: a file);
     */
    public function put($url, $content = NULL, $datatype = NULL) {

        if(!in_array('PUT', $this->allow))
            throw new \Exception('Host does not support PUT command!');

        if(! $this->authorised)
            return FALSE;

        $request = new \Hazaar\Http\Request($this->getAbsoluteUrl($url), 'PUT');

        $request->setHeader('Content-Type', $datatype);

        $request->setBody($content);

        $result = parent::send($request);

        if($result->status == 201 || $result->status == 204)
            return TRUE;

        return FALSE;

    }

    public function putFile($dir_url, $file) {

        if(! $this->authorised)
            return FALSE;

        if(! $file instanceof \Hazaar\File)
            $file = new \Hazaar\File($file);

        if(! $file->exists())
            return FALSE;

        if(substr($dir_url, -1, 1) != '/')
            $dir_url .= '/';

        $url = $dir_url . $file->getBasename();

        return self::put($url, $file->getContents(), $file->getMimeType());

    }

    public function delete($url) {

        if(!in_array('DELETE', $this->allow))
            throw new \Exception('Host does not support DELETE command!');

        if(! $this->authorised)
            return FALSE;

        $request = new \Hazaar\Http\Request($this->getAbsoluteUrl($url), 'DELETE');

        $result = parent::send($request);

        if($result->status == 204)
            return TRUE;

        return FALSE;

    }

    public function xml($xml) {

        header('Content-type: application/xml');

        if($xml instanceof \Hazaar\Xml\Element)
            $xml = $xml->toXML();

        echo $xml;

        exit;

    }

}
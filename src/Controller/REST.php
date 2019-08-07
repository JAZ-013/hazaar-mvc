<?php
/**
 * @file        Controller/REST.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2017 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       The RESTful controller class
 *
 * @detail      This controller can be used to create RESTful API endpoints.  It automatically handles
 *              HTTP request methods and can send appropriate responses for invalid requests.  It can
 *              also provide an intelligent API endpoint directory.
 *
 *              ## Overview
 *              Unlike other controllers, the rest controller works using annotations.  Such as:
 *
 *              <code>
 *              class ApiController extends \Hazaar\Controller\REST {
 *
 *                  /**
 *                   * @route('/dothething/<int:thingstodo>', methods=['GET'])
 *                   **\/
 *                  protected function do_the_thing($thingstodo){
 *
 *                      return array('things' => 'Array of things');
 *
 *                  }
 *
 *              }
 *              </code>
 *
 *              This API will be accessible at the URL: http://yourhost.com/api/v1/dothething/1234
 *
 *              ## Versions
 *              It is possible to add your own "version control" to the REST api by defining multiple
 *              functions for the same route.  Using versions allows you to easily update your API without
 *              removing backwards compatibility.  New versions should be used when there is a major change
 *              to either the input or output of your endpoint and renaming it is not reasonable.
 *
 *              Do define another version of the above example using versions you could:
 *
 *              <code>
 *              class ApiController extends \Hazaar\Controller\REST {
 *
 *                  /**
 *                   * @route('/v1/dothething/<int:thingstodo>', methods=['GET'])
 *                   **\/
 *                  protected function do_the_thing($thingstodo){
 *
 *                      return array('things' => 'Array of things');
 *
 *                  }
 *
 *                  /**
 *                   * @route('/v2/dothething/<date:when>/<int:thingstodo>', methods=['GET'])
 *                   **\/
 *                  protected function do_the_thing_v2($thingstodo, $when){
 *
 *                      if($when->year() >= 2023)
 *                          return array('things' => 'Array of FUTURE things');
 *
 *                      return array('things' => 'Array of things');
 *
 *                  }
 *
 *              }
 *              </code>
 *
 *              This API will be accessible at the URL: http://yourhost.com/api/v1/dothething/2040-01-01/1234
 *
 *              ### Endpoints on multiple versions
 *              To allow an endpoint to be available on multiple versions of your API, simply add multple @routes
 *
 *              Such as:
 *
 *              <code>
 *              /**
 *                * @route('/v1/dothething/<date:when>/<int:thingstodo>', methodsGET'])
 *                * @route('/v2/dothething/<date:when>/<int:thingstodo>', methods=['GET'])
 *               **\/
 *
 *              ## Endpoint Directories
 *              Endpoint directories are simply a list of the available endpoints with some basic information
 *              about how they operate such as the HTTP methods allowed, parameter description and a brief description.
 */
abstract class REST extends \Hazaar\Controller {

    protected $request;

    protected $describe_full = true;

    protected $allow_directory = true;

    private $__rest_endpoints = array();

    private $__endpoint;

    private $__rest_cache;

    private $__rest_cache_enable_global = false;

    static private $valid_types = array('boolean', 'bool', 'integer', 'int', 'double', 'float', 'string', 'array', 'null', 'date');

    protected function enableEndpointCaching($boolean){

        $this->__rest_cache_enable_global = boolify($boolean);

    }

    public function __construct($name, \Hazaar\Application $application, $use_app_config = true) {

        $application->setResponseType('json');

        parent::__construct($name, $application);

        $this->__rest_cache = new \Hazaar\Cache();

    }

    public function __initialize(\Hazaar\Application\Request $request) {

        $this->request = $request;

        $class = new \ReflectionClass($this);

        foreach($class->getMethods(\ReflectionMethod::IS_PUBLIC) as $method){

            if(substr($method->getName(), 0, 2) === '__' || $method->isPrivate())
                continue;

            $method->setAccessible(true);

            if(!($comment = $method->getDocComment()))
                continue;

            if(!(preg_match_all('/\*\s*@((\w+).*)/', $comment, $method_matches)))
                continue;

            $endpoint = array(
                'cache' => false,
                'cache_timeout' => null,
                'cache_ignore_params' => false,
                'comment' => $comment,
                'routes' => array()
            );

            if(($pos = array_search('cache', $method_matches[2])) !== false){

                if(preg_match('/cache\s+(.*)/', $method_matches[1][$pos], $cache_matches)){

                    $cache = trim($cache_matches[1]);

                    if(is_numeric($cache)){

                        $endpoint['cache'] = true;

                        $endpoint['cache_timeout'] = intval($cache);

                    }else{

                        $endpoint['cache'] = boolify($cache);

                    }

                    if($endpoint['cache'] === true
                        && (($pos = array_search('cache_ignore_params', $method_matches[2])) !== false)
                        && preg_match('/cache_ignore_params\s+(.*)/', $method_matches[1][$pos], $cache_matches))
                        $endpoint['cache_ignore_params'] = boolify($cache_matches[1]);

                }

            }

            foreach($method_matches[2] as $index => $tag){

                if($tag !== 'route')
                    continue;

                if(!preg_match('/\([\'\"]([\w\<\>\:\/]+)[\'\"]\s*,?\s*(.+)*\)/', $method_matches[1][$index], $route_matches))
                    continue;

                $target = '/' . ltrim($route_matches[1], '/');

                $args = array('methods' => array('GET'));

                if(array_key_exists(2, $route_matches)){

                    $parts = preg_split('/\s*(,(?![^(\[]*[\)\]]))\s*/', $route_matches[2]);

                    foreach($parts as $part){

                        //If there is no equals sign, skip this one.
                        if(strpos($part, '=') > 0){

                            list($key, $value) = explode('=', $part, 2);

                            if($value = json_decode(str_replace("'", '"', $value), true))
                                $args[$key] = $value;

                        }else{

                            if(!array_key_exists('properties', $args))
                                $args['properties'] = array();

                            $args['properties'][] = trim($part);

                        }

                    }

                }

                $endpoint['routes'][$target] = array(
                    'func' => $method,
                    'args' => $args
                );

            }

            if(count($endpoint['routes']) > 0)
                $this->__rest_endpoints[$method->name] = $endpoint;

        }

        if(method_exists($this, 'init'))
            $this->init($request);

        return null;

    }

    public function __run() {

        $full_path = '/' . $this->request->getPath();

        $request_method = $this->request->method();

        if($full_path == '/'){

            if(!$this->allow_directory)
                throw new \Exception('Directory listing is not allowed', 403);

            return new \Hazaar\Controller\Response\Json($this->__describe_api());

        }

        foreach($this->__rest_endpoints as $endpoint){

            foreach($endpoint['routes'] as $target => $route){

                if($this->__match_route($request_method, $full_path, $target, $route, $args)){

                    if($this->request->method() == 'OPTIONS'){

                        $response = new \Hazaar\Controller\Response\Json();

                        $response->setHeader('allow', $route['args']['methods']);

                        if($this->allow_directory)
                            $response->populate($this->__describe_endpoint($target, $route, $this->describe_full));

                        return $response;

                    }else{

                        $this->__endpoint = array($endpoint, $route, $args);

                        break;

                    }

                }

            }

        }

        if(!$this->__endpoint)
            throw new \Exception('REST API Endpoint not found: ' . $full_path, 404);

        return $this->__exec_endpoint($this->__endpoint, $this->request->getParams());

    }

    private function __describe_api(){

        $api = array();

        foreach($this->__rest_endpoints as $endpoint)
            $this->__describe_endpoint($endpoint, $this->describe_full, $api);

        return $api;

    }

    private function __describe_endpoint($endpoint, $describe_full = false, &$api = null){

        if(!$api) $api = array();

        foreach($endpoint['routes'] as $route => $route_data){

            foreach($route_data['args']['methods'] as $methods){

                $info = array(
                    'url' => (string)$this->url($route),
                    'httpMethods' => $methods
                );

                if($describe_full && ($doc = ake($endpoint, 'doc'))){

                    if($doc->hasTag('param')){

                        $info['parameters'] = array();

                        foreach($doc->tag('param') as $name => $param){

                            if(!preg_match('/<(\w+:)*' . substr($name, 1) . '>/', $route))
                                continue;

                            $info['parameters'][$name] = $param['desc'];

                        }

                    }

                    if($brief = $doc->brief())
                        $info['description'] = $brief;

                }

                $api[] = $info;

            }

        }

        return $api;

    }

    private function __match_route($request_method, $path, $route, $endpoint, &$args = null){

        $args = array();

        $path = explode('/', ltrim($path, '/'));

        $route = explode('/', ltrim($route, '/'));

        if(count($path) != count($route))
            return false;

        for($i = 0; $i < count($path); $i++){

            //If this part is identical to the route part, it matches so keep checking
            if($path[$i] === $route[$i])
                continue;

            //If this part of the route is not a variable, there's no match to return
            if(!preg_match('/\<(\w+)(:(\w+))?\>/', $route[$i], $matches))
                return false;

            $value = $path[$i];

            if(count($matches) == 4){

                $key = $matches[3];

                if(!in_array($matches[1], REST::$valid_types))
                    return false;

                if($matches[1] == 'date')
                    $value = new \Hazaar\Date($value);
                elseif($matches[1] == 'bool' || $matches[1] == 'boolean')
                    $value = boolify($value);
                else
                    settype($value, $matches[1]);


            }else{

                $key = $matches[1];

            }

            $args[$key] = $value;

        }

        $http_methods = ake(ake($endpoint, 'args'), 'methods', array('GET'));

        if(!in_array($request_method, $http_methods))
            return false;

        return true;

    }

    private function __exec_endpoint($endpoint, $args = array()){

        list($endpoint, $route, $args) = $endpoint;

        if(!($method = $route['func']) instanceof \ReflectionMethod)
            throw new \Exception('Method is no longer a method!?', 500);

        $params = array();

        foreach($method->getParameters() as $p){

            $key = $p->getName();

            $value = null;

            if(array_key_exists($key, $args))
                $value = $args[$key];
            elseif(array_key_exists('defaults', $route['args']) && array_key_exists($key, $route['args']['defaults']))
                $value = $route['args']['defaults'][$key];
            elseif($p->isDefaultValueAvailable())
                $value = $p->getDefaultValue();
            else
                throw new \Exception("Missing value for parameter '$key'.", 400);

            $params[$p->getPosition()] = $value;

        }

        $result = null;

        /*
         * Enable caching if:
         * * The cache object has been created (ie: the cache library is available)
         * * This endpoint has enabled caching specifically
         * * Global cache is enabled and this endpoint has not disabled caching
         */
        $cache_key = ($this->__rest_cache instanceof \Hazaar\Cache
            && ($endpoint['cache'] === true
            || ($this->__rest_cache_enable_global === true && $endpoint['cache'] !== false))
            ? 'rest_endpoint_' . md5(serialize(array($method, $params, $args, (($endpoint['cache_ignore_params'] !== true) ? $this->request->getParams() : null)))) : null);

        //Try extracting from cache first if caching is enabled
        if($cache_key !== null)
            $result = $this->__rest_cache->get($cache_key);

        //If there is no result yet, execute the endpoint
        if(!$result){

            $result = $method->invokeArgs($this, $params);

            if($result instanceof \Hazaar\Controller\Response)
                return $result;

            //Save the result if caching is enabled.
            if($cache_key !== null)
                $this->__rest_cache->set($cache_key, $result, $endpoint['cache_timeout']);

        }

        $response = new \Hazaar\Controller\Response\Json();

        $response->populate($result);

        return $response;

    }

    protected function getRequestedEndpoint(){

        return $this->__endpoint[1]['func']->name;

    }

    protected function getEndpointTags($name){

        foreach($this->__rest_endpoints as $endpoint){

            foreach($endpoint['routes'] as $route){

                if($route['func']->name === $name){

                    if(!array_key_exists('tags', $route)){

                        $route['tags'] = array();

                        preg_match_all('/\*\s*@((\w+).*)/', $endpoint['comment'], $matches);

                        foreach($matches[1] as $annotation){

                            if(!preg_match('/^(\w+)(\W.*)$/', $annotation, $parts))
                                continue;

                            $route['tags'][$parts[1]] = trim($parts[2]);

                        }

                    }

                    return $route['tags'];

                }

            }

        }

        return false;

    }

}

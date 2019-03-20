<?php
/**
 * @file        Hazaar/Application/Request/Http.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Application\Request;

/**
 * @brief       Controller HTTP Request Class
 *
 * @detail      The HTTP controller request class is a representational object for an HTTP request.  The Application
 *              object will create a HTTP request object upon each execution.  This object contains all details of
 *              the current request including request data, headers and any request body content.
 *
 *              If you want to generate your own HTTP request object to pass to another method or function that requires
 *              one, see [[Hazaar\Http\Request]].
 *
 * @since       1.0.0
 */
class Http extends \Hazaar\Application\Request {

    static public $pathParam = 'hz_path';

    static public $queryParam = 'hzqs';

    /**
     * Request method
     */
    private $method = 'GET';

    /**
     * Array of headers, one line per element.
     */
    private $headers = array();

    /**
     * Request body.  This is only used in certain circumstances such as with XML-RPC or REST.
     * @var string The request body
     */
    public $body;

    /**
     * In the case where the request is of content-type application/json this is the decoded JSON body.
     * @var object|array Body decoded with json_decode()
     */
    public $bodyJSON;

    /**
     * @detail      The HTTP init method takes only a single optional argument which is the
     *              request array provided by PHP ($_REQUEST).
     *
     *              The constructor will also get all the request headers and the request content and
     *              from there will use the [[Hazaar\Application\Request]] parent class to determine the
     *              name of the Controller and Action that is being requested via it's evaluate() method.
     *
     * @since       1.0.0
     *
     * @param       Array $request Optional reference to $_REQUEST
     */
    function init($request = NULL) {

        if($request === NULL){

            /*
             * Check if we require SSL and if so, redirect here.
             */
            if($this->config->app->has('require_ssl') && boolify($_SERVER['HTTPS']) !== boolify($this->config->app->require_ssl)){

                header("Location: https://" . $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"]);

                exit;

            }

            $request = $_REQUEST;

            $this->method = $_SERVER['REQUEST_METHOD'];

            $this->headers = hazaar_request_headers();

            $this->body = @file_get_contents('php://input');

            $encryption_header = ucwords(strtolower(\Hazaar\Http\Client::$encryption_header), '-');

            if(array_key_exists($encryption_header, $this->headers)){

                $iv = base64_decode($this->headers[$encryption_header]);

                if(!($keyfile = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key')))
                    throw new \Exception('Unable to encrypt.  No key provided and no default keyfile!');

                \Hazaar\Controller\Response::$encryption_key = trim(file_get_contents($keyfile));

                $this->body = openssl_decrypt(base64_decode($this->body),
                    \Hazaar\Http\Client::$encryption_default_cipher,
                    \Hazaar\Controller\Response::$encryption_key, OPENSSL_RAW_DATA, $iv);

                if($this->body === false)
                    throw new \Exception('Received an encrypted request but was unable to decrypt the body!', 500);

            }

            if($this->body && ($content_type = explode(';', $this->getHeader('Content-Type')))){

                switch($content_type[0]){

                    case 'text/json':
                    case 'application/json':
                    case 'application/javascript':
                    case 'application/x-javascript':

                        $request = array_merge($request, json_decode($this->body, true));

                        break;

                    case 'text/html':
                    case 'application/x-www-form-urlencoded':

                        parse_str($this->body, $params);

                        $request = array_merge($request, $params);

                        break;

                }

            }

        }

        $this->setParams($request);

        if(array_key_exists(Http::$queryParam, $this->params)){

            parse_str(base64_decode($this->params[Http::$queryParam]), $params);

            $this->params = array_merge($this->params, $params);

            unset($this->params[Http::$queryParam]);

        }

        if(\Hazaar\Application\Url::$rewrite === false && array_key_exists(Http::$pathParam, $this->params))
            return trim($this->params[Http::$pathParam], '/');

        $request_uri = urldecode(ake($_SERVER, 'REQUEST_URI', '/'));

        /*
         * Figure out the PHP environment variables to use to find the controller that's being called
         */
        if($pos = strpos($request_uri, '?'))
            $request_uri = substr($request_uri, 0, $pos);

        $path = pathinfo($_SERVER['SCRIPT_NAME']);

        if($path['basename'] == 'index.php') {

            /*
             * If we are hosted in a sub-directory we need to rip off the base dir to find our relative target
             */
            if(($len = strlen($path['dirname'])) > 1)
                $request_uri = substr($request_uri, $len);

        }

        $this->response_type = ake($request, 'response_type');

        return substr($request_uri, 1);

    }

    /**
     * @detail      Returns the method used to initiate this request on the server.  .
     *
     * @since       1.0.0
     *
     * @return      string The request method. Usually one of GET, POST, PUT or DELETE.
     */
    public function getMethod() {

        return $this->method;

    }

    /**
     * @detail      Test if the request method is GET.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @since       1.0.0
     *
     * @return      boolean True if method is GET.  False otherwise.
     */
    public function isGet() {

        return ($this->method == 'GET');

    }

    /**
     * @detail      Test if the request method is PUT.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @since       1.0.0
     *
     * @return      boolean True if method is PUT.  False otherwise.
     */
    public function isPut() {

        return ($this->method == 'PUT');

    }

    /**
     * @detail      Test if the request method is POST.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @since       1.0.0
     *
     * @return      boolean True if method is POST.  False otherwise.
     */
    public function isPost() {

        return ($this->method == 'POST');

    }

    /**
     * @detail      Test if the request method is DELETE.  This is a convenience method for quickly determining the
     *              request method.
     *
     * @since       1.0.0
     *
     * @return      boolean True if method is DELETE.  False otherwise.
     */
    public function isDelete() {

        return ($this->method == 'DELETE');

    }

    /**
     * @detail      Get all the HTTP request headers sent by the client browser.
     *
     * @since       2.0.0
     *
     * @return      array An array of headers with the key as the header name and the value as the header value.
     */
    public function getHeaders() {

        return $this->headers;

    }

    /**
     * @detail      Check if a header was sent in the HTTP request.
     *
     * @param       $header string The header to check
     *
     * @return      bool TRUE if the header was sent.
     */
    public function hasHeader($header) {

        return array_key_exists($header, $this->headers);

    }

    /**
     * @detail      Get a single header value
     *
     * @param       $header string The header value to get.
     *
     * @return      mixed Returns the header value if it exists.  Null otherwise.
     */
    public function getHeader($header) {

        return ake($this->headers, $header);

    }

    /**
     * Return the current request content type.
     *
     * This is a helpful method for doing a few things in one go as it will only return a content type
     * if the request is a POST method.  Otherwise it will safely return a FALSE value.
     *
     * @return mixed The content type of the POST request.  False if the request is not a POST request.
     */
    public function getContentType(){

        if($this->isPost())
            return $this->getHeader('Content-Type');

        return false;

    }

    /**
     * @detail      Test if the request originated from an XMLHttpRequest object.  This object is used when sending an
     *              AJAX request from withing a JavaScript function.  All of the major JavaScript libraries (jQuery,
     *              extJS, etc) will set the X-Requested-With header to indicate that the request is an AJAX request.
     *
     *              Using this in your application will allow you to determine how to respond to the request.  For
     *              example, you might want to forgo rendering a view and instead return a JSON response.
     *
     * @since       1.0.0
     *
     * @return       boolean True to indicate the X-Requested-With is set.  False otherwise.
     */
    public function isXmlHttpRequest() {

        if(array_key_exists('X-Requested-With', $this->headers)) {

            if($this->headers['X-Requested-With'] == 'XMLHttpRequest')
                return TRUE;

        }

        return FALSE;

    }

    /**
     * @detail      Returns the URI of the page this request was redirected from.
     *
     * @since       1.0.0
     *
     * @return      string Original request URI
     */
    public function redirectURI() {

        $sess = new \Hazaar\Session();

        if($sess->has('REDIRECT_URI') && $sess->REDIRECT_URI != $_SERVER['REQUEST_URI'])
            return $sess->REDIRECT_URI;

        return NULL;

    }

    /**
     * @detail      Returns the body of the request.  This will normally be null unless the request is a POST or PUT.
     *
     * @return      string The request body.
     */
    public function getRequestBody() {

        return $this->body;

    }

    /**
     * Get the remote IP address of the requesting host
     * 
     * This will try to determine the correct IP to return.  By default it will return the $_SERVER['REMOTE_ADDR']
     * value, but if the connection is via a reverse proxy (such as Haproxy) then it will possibly have the standard
     * X-Forwarded-For header, so if that header exists then that value will be returned.
     * 
     * @return mixed
     */
    public function getRemoteAddr(){

        if(array_key_exists('X-Forwarded-For', $this->headers))
            return $this->headers['X-Forwarded-For'];

        return ake($_SERVER, 'REMOTE_ADDR');

    }
}
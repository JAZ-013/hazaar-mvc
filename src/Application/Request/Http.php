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
    function init($request = NULL, $process_request_body = false, $method = null) {

        if($request === NULL)
            $request = $_REQUEST;

        $this->method = is_string($method) ? $method : ake($_SERVER, 'REQUEST_METHOD', 'GET');

        $this->headers = getallheaders();

        if($process_request_body === true)
            $this->body = @file_get_contents('php://input');

        $encryption_header = ucwords(strtolower(\Hazaar\Http\Client::$encryption_header), '-');

        if(array_key_exists($encryption_header, $this->headers)){

            $iv = base64_decode($this->headers[$encryption_header]);

            if(!($keyfile = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key')))
                throw new \Hazaar\Exception('Unable to encrypt.  No key provided and no default keyfile!');

            \Hazaar\Controller\Response::$encryption_key = trim(file_get_contents($keyfile));

            $this->body = openssl_decrypt(base64_decode($this->body),
                \Hazaar\Http\Client::$encryption_default_cipher,
                \Hazaar\Controller\Response::$encryption_key, OPENSSL_RAW_DATA, $iv);

            if($this->body === false)
                throw new \Hazaar\Exception('Received an encrypted request but was unable to decrypt the body!', 500);

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

        if(is_array($request) && count($request) > 0)
            $this->setParams($request);

        if(array_key_exists(Http::$queryParam, $this->params)){

            parse_str(base64_decode($this->params[Http::$queryParam]), $params);

            $this->params = array_merge($this->params, $params);

            unset($this->params[Http::$queryParam]);

        }

        if(array_key_exists(Http::$pathParam, $this->params))
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

    /**
     * Detect if a request originated on a mobile device
     *
     * This method will return true to indicate that the requesting device is a mobile browser.  It uses the freely
     * available
     * script from detectmobilebrowsers.com
     *
     * @return boolean True to indicate requesting device is a mobile browser, false otherwise.
     */
    public function isMobileDevice() {

        $useragent = $_SERVER['HTTP_USER_AGENT'];

        $ret = preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i', $useragent) || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i', substr($useragent, 0, 4));

        return $ret;

    }

    public function method(){

        return $this->method;

    }

    public function referer(){

        return ake($_SERVER, 'HTTP_REFERER');

    }

}
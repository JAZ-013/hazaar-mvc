<?php

namespace Hazaar\Http;

class Client {

    private $debug              = FALSE;

    private $lasterror          = NULL;

    private $buffer_size        = 4096;

    private $connection_timeout = 5;

    private $headers            = array();

    private $cookies            = array();

    private $local_cert         = NULL;

    private $cert_passphrase    = NULL;

    private $auto_redirect      = TRUE;

    private $redirect_methods   = array(
        'GET',
        'OPTIONS'
    );

    private $username;

    private $password;

    private $auth;

    private $encryption_key     = null;

    private $encryption_cipher  = null;

    static public $encryption_default_key;

    static public $encryption_default_cipher = 'AES-256-CBC';

    static public $encryption_header = 'X-HAZAAR-COMKEY';

    function __construct($local_cert = NULL, $passphrase = NULL, $debug = FALSE) {

        $this->local_cert = $local_cert;

        $this->cert_passphrase = $passphrase;

        $this->debug = $debug;

    }

    public function auth($username, $password) {

        $this->username = $username;

        $this->password = $password;

    }

    public function authorisation(\Hazaar\Auth\Adapter $auth){

        $this->auth = $auth;

    }

    public function authorization(\Hazaar\Auth\Adapter $auth){

        return $this->authorisation($auth);

    }

    public function setHeader($header, $value) {

        $this->headers[$header] = $value;

    }

    public function options($uri) {

        return $this->send(new Request($uri, 'OPTIONS'));

    }

    public function get($uri, $redirect_limit = 10, $offset = -1, $maxlen = NULL) {

        $request = new Request($uri, 'GET');

        if($offset >= 0) {

            $range = 'bytes=' . $offset . '-';

            if($maxlen)
                $range .= ($offset + ($maxlen - 1));

            $request->setHeader('Range', $range);

        }

        return $this->send($request, $redirect_limit);

    }

    public function head($uri, $redirect_limit = 10) {

        return $this->send(new Request($uri, 'HEAD'), $redirect_limit);

    }

    public function post($uri, $data = NULL, $datatype = NULL, $redirect_limit = 0) {

        $request = new Request($uri, 'POST');

        if(! $datatype)
            $datatype = 'text/html';

        if($data)
            $request->setBody($data);

        if($datatype)
            $request->setHeader('Content-Type', $datatype);

        return $this->send($request, $redirect_limit);

    }

    public function put($uri, $data = NULL, $datatype = NULL) {

        $request = new Request($uri, 'PUT');

        if(! $datatype && is_array($data))
            $datatype = 'text/html';

        if($data)
            $request->setBody($data);

        if($datatype)
            $request->setHeader('Content-Type', $datatype);

        return $this->send($request);

    }

    public function delete($url) {

        return $this->send(new Request($url, 'DELETE'));

    }

    public function trace($url) {

        return $this->send(new Request($url, 'TRACE'));

    }

    public function send(Request $request, $redirect_limit = 10) {

        if(! $request instanceof Request)
            return FALSE;

        if(is_array($this->headers) && count($this->headers) > 0) {

            foreach($this->headers as $header => $value)
                $request->setHeader($header, $value);

        }

        if($this->local_cert)
            $request->setLocalCertificate($this->local_cert, $this->cert_passphrase);

        if($this->auth)
            $request->authorisation($this->auth);

        elseif($this->username)
            $request->authorise($this->username, $this->password);

        $this->applyCookies($request);

        $sck_fd = @stream_socket_client($request->getHost(), $errno, $errstr, $this->connection_timeout, STREAM_CLIENT_CONNECT, $request->context);

        if($sck_fd) {

            $http_request = $request->toString($this->encryption_key, $this->encryption_cipher);

            fputs($sck_fd, $http_request, strlen($http_request));

            $response = new Response();

            $buffer_size = $this->buffer_size;

            while(($buf = fread($sck_fd, $buffer_size)) !== FALSE) {

                $response->read($buf);

                /*
                 * Dynamic buffer resize.  This fixes a problem when the bytes remaining is less than the buffer size,
                 * but the buffer has already been read.
                 *
                 * If we have a content-length and the buffer len is greater or equal to it, dump out as we have all our
                 * content.
                 */
                if($response->bytes_remaining > 0 && $response->bytes_remaining < $buffer_size) {

                    $buffer_size = $response->bytes_remaining;

                }

                //If the socket is now EOF then break out
                if(feof($sck_fd))
                    break;

            }

            fclose($sck_fd);

            if($this->auto_redirect && ($response->status == 301 || $response->status == 302)) {

                if(in_array($request->method, $this->redirect_methods)) {

                    if($redirect_limit <= 0) {

                        throw new Exception\TooManyRedirects();

                    }

                    if(! preg_match('/^http[s]?\:\/\//', $response->location)) {

                        if(substr($response->location, 0, 1) == '/') {

                            $request->uri->path = $response->location;

                        } else {

                            $request->uri->path .= ((substr($request->url->path, -1, 1) == '/') ? NULL : '/') . $response->location;

                        }

                    } else {

                        $request->uri(new Uri($response->location));

                    }

                    return self::send($request, --$redirect_limit);

                }

                throw new Exception\RedirectNotAllowed($response->status);

            } elseif($response->status == 303) {

                $request->method = 'GET';

                return $this->get($response->location, --$redirect_limit);

            }

        } else {

            throw new \Exception('Error #' . $errno . ': ' . $errstr);

        }

        if($cookie = $response->getHeader('set-cookie'))
            $this->setCookie($cookie);

        if($response->hasHeader(strtolower(\Hazaar\Http\Client::$encryption_header)))
            $response->decrypt($this->encryption_key, $this->encryption_cipher);

        return $response;

    }

    public function getLastError() {

        return $this->lasterror;

    }

    public function getCookies(){

        $list = array();

        foreach($this->cookies as $cookie)
            $list[] = $cookie['name'] . '=' . $cookie['value'];

        if(count($list) > 1)
            return $list;

        return array_pop($list);

    }

    public function getCookie($name){

        $list = array();

        foreach($this->cookies as $cookie){

            if($cookie['name'] === $name)
                $list[] = $cookie['name'] . '=' . $cookie['value'];

        }

        if(count($list) === 0)
            return null;

        elseif(count($list) > 1)
            return $list;

        return array_pop($list);

    }

    public function hasCookie($name){

        return ($this->getCookie($name) !== null);

    }

    public function deleteCookie($name){

        foreach($this->cookies as $index => $cookie){

            if($cookie['name'] === $name)
                unset($this->cookies[$index]);

        }

    }

    public function setCookie($cookie){

        if(is_array($cookie)){

            $cookies = array();

            foreach($cookie as $c)
                $cookies[] = $this->setCookie($c);

            return $cookies;

        }

        $parts = explode(';', $cookie);

        list($name, $value) = explode('=', array_shift($parts), 2);

        $data = array(
            'name' => $name,
            'value' => $value,
            'domain' => null,
            'path' => '/',
            'expires' => null,
            'secure' => false,
            'httponly' => false
        );

        foreach($parts as $part){

            if(strpos($part, '=') !== false){

                list($arg_k, $arg_v) = explode('=', $part);

                $arg_k = strtolower(trim($arg_k));

                if($arg_k === 'expires')
                    $arg_v = new \Hazaar\Date($arg_v);
                elseif($arg_k === 'max-age')
                    $arg_v = (new \Hazaar\Date())->add('PT' . $arg_v . 'S');

                $data[$arg_k] = $arg_v;

            }else{

                $data[strtolower(trim($part))] = true;

            }

        }

        $key = md5($name . ake($data, 'domain') . ake($data, 'path'));

        return $this->cookies[$key] = $data;

    }

    private function applyCookies(Request $request){

        if(!(is_array($this->cookies) && count($this->cookies) > 0))
            return $request;

        $uri = $request->uri();

        $path = explode('/', trim($uri->path(), '/'));

        $cookies = array();

        foreach($this->cookies as $cookie_key => $cookie_data){

            if($expires = ake($cookie_data, 'expires', null, true)){

                if($expires->getTimestamp() < time()){

                    unset($this->cookies[$cookie_key]);

                    continue;

                }

            }

            if($domain = ake($cookie_data, 'domain')){

                $domain = array_reverse(explode('.', $domain));

                if(!(array_slice(array_reverse(explode('.', $uri->host())), 0, count($domain)) === $domain))
                    continue;

            }

            if($cookie_path = trim($cookie_data['path'], '/'))
                $cookie_path = explode('/', $cookie_path);
            else $cookie_path = array();

            if(!(array_slice($path, 0, count($cookie_path)) === $cookie_path))
                continue;

            if($cookie_data['secure'] === true && $uri->scheme() !== 'https')
                continue;

            $cookies[] = $cookie_data['name'] . '=' . ake($cookie_data, 'value');

        }

        $request->setHeader('Cookie', implode(';', $cookies), false);

        return $request;

    }

    public function cacheCookie(\Hazaar\Cache $cache, $cache_all = false) {

        if(!count($this->cookies) > 0)
            return FALSE;

        if($cache_all === true){

            $cache->set('hazaar-http-client-cookies', $this->cookies);

        }else{

            $cachable = array();

            foreach($this->cookies as $key => $cookie){

                if(($cookie['expires'] instanceof \Hazaar\Date && $cookie['expires']->getTimestamp() > time()) || $cookie['expires'] === null)
                    $cachable[$key] = $cookie;

            }

            $cache->set('hazaar-http-client-cookies', $cachable);

        }

        return TRUE;

    }

    public function uncacheCookie(\Hazaar\Cache $cache) {

        if($this->cookies = $cache->get('hazaar-http-client-cookies'))
            return TRUE;

        $this->cookies = array();

        return FALSE;

    }

    public function disableRedirect() {

        $this->auto_redirect = FALSE;

    }

    public function enableEncryption($key = null, $cipher = null){

        if($key === null){

            if(\Hazaar\Http\Client::$encryption_default_key === null){

                if(!($keyfile = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key')))
                    throw new \Exception('Unable to encrypt.  No key provided and no default keyfile!');

                \Hazaar\Http\Client::$encryption_default_key = trim(file_get_contents($keyfile));

            }

            $key = \Hazaar\Http\Client::$encryption_default_key;

        }

        $this->encryption_key = $key;

        if($cipher === null)
            $cipher = Client::$encryption_default_cipher;

        $this->encryption_cipher = $cipher;

    }

}

<?php

namespace Hazaar\Http;

class Request extends \Hazaar\Map {

    public  $method  = 'GET';

    private $headers = array(
        'User-Agent'   => 'hazaarmvc-php/' . HAZAAR_VERSION . ' (PHP)',
        'Content-Type' => 'text/html',
        'Connection'   => 'close'
    );

    private $raw_uri;

    private $uri;

    private $fsock_host;

    private $body    = null;

    private $multipart = false;

    public  $context = null;

    /**
     * HTTP request constructor
     *
     * @param mixed $uri The URI of the resource that will be requested
     * @param mixed $method The request method to use.  Typically GET, POST, etc
     * @param mixed $content_type Optionally set the content type header.
     * @param mixed $custom_context Optionally use a custom context.  Allows to define private SSL certificates.
     */
    function __construct($uri = null, $method = 'GET', $content_type = null, $custom_context = null) {

        if($method)
            $this->method = $method;

        if(! $content_type) {

            if($this->method == 'POST') {

                $content_type = 'application/x-www-form-urlencoded';

            } else {

                $content_type = 'text/html';

            }

        }

        $this->setHeader('Content-Type', $content_type);

        $this->uri($uri);

        $this->context = is_resource($custom_context) ? $custom_context : stream_context_create();

    }

    /**
     * Set the URI of the resource that is being requested
     *
     * @param mixed $uri
     *
     * @throws Exception\HostNotFound
     * @return \Hazaar\Http\Uri
     */
    public function uri($uri = null){

        if($uri === null)
            return $this->uri;

        $this->raw_uri = $uri;

        $this->uri = ($uri instanceof Uri) ? $uri : new Uri($uri);

        /**
         * !!NOTE!!
         * This is a hack to prevent bad hosts from throwing a FATAL error when trying to connect to them.
         * I don't know why a FATAL error is thrown just because host we're connecting to is not found, but this
         * will check that the hostname exists first before trying to continue.
         */
        if(ip2long(gethostbyname($this->uri->host())) == false)
            throw new Exception\HostNotFound($this->uri->host());

        //If the protocol ends in s, we're going to assume it is meant for SSL
        if($this->uri->isSecure()) {

            $this->fsock_host = 'ssl://' . $this->uri->host() . ':' . $this->uri->port();

        } else {

            $this->fsock_host = 'tcp://' . $this->uri->host() . ':' . $this->uri->port();

        }

        $this->setHeader('Host', $this->uri->host());

        return $this->uri;

    }

    /**
     * Sets the Content-Type header for the request.
     *
     * @param mixed $content_type
     * @return void
     */
    public function setContentType($content_type){

        return $this->setHeader('Content-Type', $content_type);

    }

    /**
     * Returns the current Content-Type header for the request.
     * @return mixed
     */
    public function getContentType(){

        return $this->getHeader('Content-Type');

    }

    /**
     * Enable multipart mime request body optionally using the specified boundary and content type.
     *
     * @param mixed $content_type Optional request content type to use.  Defaults to multipart/form-data.
     * @param mixed $boundary Optional boundary identifier. Defaults to HazaarMVCMultipartBoundary_{uniqid}
     *
     * @return bool True if multipart was enabled.  False if it was already enabled.
     */
    public function enableMultipart($content_type = null, $boundary = null){

        if($this->multipart === true)
            return false;

        $this->multipart = true;

        $this->body = ($this->body === null) ? array() : array($this->getContentType(), $this->body);

        if(!$boundary)
            $boundary = 'HazaarMVCMultipartBoundary_' . uniqid();

        if(!$content_type)
            $content_type = 'multipart/form-data';

        $this->setContentType($content_type . '; boundary="' . $boundary . '"');

        return true;

    }

    /**
     * Returns a boolean indicating if the request is a multipart request
     *
     * @return bool
     */
    public function isMultipart(){

        return $this->multipart;

    }

    /**
     * Return the current multipart boundary name
     *
     * @return mixed
     */
    public function getMultipartBoundary(){

        if($this->multipart !== true)
            return false;

        $content_type = $this->getHeader('Content-Type');

        if(!preg_match('/^multipart\/.*boundary\s*=\s*"(.*)"/', $content_type, $matches))
            return false;

        return $matches[1];

    }

    /**
     * Add a multipart chunk to the request
     *
     * @param mixed $data The data to add to the request.
     * @param mixed $content_type The content type of the added data.
     */
    public function addMultipart($data, $content_type = null, $headers = null) {

        if(!$content_type) {

            if(is_array($data))
                $content_type = 'application/json';
            else
                $content_type = 'text/text';

        }

        if($this->multipart !== true)
            $this->enableMultipart();

        if(!is_array($headers))
            $headers = array();

        $headers['Content-Type'] = $content_type;

        $part = array($headers, $data);

        $this->body[] = $part;

    }

    /**
     * Returns the name of the host that will be sent this request
     *
     * @return string
     */
    public function getHost(){

        return $this->fsock_host;

    }

    /**
     * Set the request body.
     *
     * If multipart is enabled, then the body will be added as a new chunk.
     *
     * @param mixed $body
     */
    public function setBody($body, $content_type = null) {

        if($body instanceof \Hazaar\Xml\Element){

            if(!$content_type)
                $content_type = 'application/xml';

            $body = $body->toXML();

        }

        if(is_array($this->body)){

            $this->body[] = array($content_type, $body);

        }else{

            $this->setContentType($content_type);

            $this->body = $body;

        }

    }

    /**
     * Return the body of the request.
     *
     * If multipart is enabled, this will return an array containing request body and content type.
     * @return mixed
     */
    public function getBody() {

        return $this->body;

    }

    /**
     * Returns all the headers currently set on the request
     *
     * @return array
     */
    public function getHeaders() {

        return $this->headers;

    }

    /**
     * Returns the value of the header specified by $key
     *
     * @param mixed $key The name of the header to return
     * @return mixed
     */
    public function getHeader($key) {

        return ake($this->headers, $key);

    }

    /**
     * Sets the value of a header
     *
     * @param mixed $key The name of the header to set.
     * @param mixed $value The value to set on the header.
     * @param mixed $allow_multiple Whether multiple instances of the header are allowed.  Defaults to false, meaning if the
     *                              header exists, it will be updated.  Multiple headers are rare but the main one is 'Cookie'.
     */
    public function setHeader($key, $value, $allow_multiple = false) {

        if($allow_multiple === true && array_key_exists($key, $this->headers)){

            if(!is_array($this->headers[$key]))
                $this->headers[$key] = array($this->headers[$key]);

            $this->headers[$key][] = $value;

        }else{

            $this->headers[$key] = $value;

        }

    }

    /**
     * Output the request as a string
     *
     * This is the method that renders the request as a HTTP/1.1 compliant request.
     *
     * @param mixed $encryption_key Optionally encrypt the request using this encryption key.
     * @param mixed $encryption_cipher Optionally specifiy the cipher used to encrypt the request.
     * @return string
     */
    public function toString($encryption_key = null, $encryption_cipher = null) {

        $uri = clone $this->uri;

        $body = '';

        /*
         * Convert any parameters into a HTTP POST query
         */

        if($this->method === 'GET' && $this->count() > 0 && $encryption_key === null) {

            $uri->setParams($this->toArray());

        } elseif($this->body !== null) {

            if($this->count() > 0)
                $uri->setParams($this->toArray());

            if($this->multipart === true){

                $boundary = $this->getMultipartBoundary();

                foreach($this->body as $part) {

                    $body .= "--$boundary\r\n";

                    if(! is_array($part[0]))
                        $part[0] = array('Content-Type' => $part[0]);

                    if($content_type = ake($part[0], 'Content-Type')) {

                        if(($pos = strpos($content_type, ';')) > 0)
                            $content_type = substr($content_type, 0, $pos);

                        switch($content_type) {

                            case 'text/json' :
                            case 'application/json' :

                                $data = json_encode($part[1]);

                                break;

                            case 'text/html' :
                            case 'application/x-www-form-urlencoded':

                                $data = is_array($part[1]) ? http_build_query($part[1]) : $part[1];

                                break;

                            default:

                                $data =& $part[1];

                                break;

                        }

                    } else {

                        $data = $part[1];

                    }

                    foreach($part[0] as $key => $value)
                        $body .= $key . ': ' . $value . "\r\n";

                    $body .= "\r\n$data\r\n";

                }

                $body .= "--$boundary--\r\n";

            } elseif(is_array($this->body) || $this->body instanceof \stdClass) {

                $this->setHeader('Content-Type', 'application/json');

                $body = json_encode($this->body);

            } else { //Otherwise use the raw content body

                $body = $this->body;

            }

        } elseif($this->count() > 0) {

            $content_type = $this->getHeader('Content-Type');

            if(($pos = strpos($content_type, ';')) > 0)
                $content_type = substr($content_type, 0, $pos);

            switch($content_type) {

                case 'text/json' :
                case 'application/json' :
                case 'application/javascript' :
                case 'application/x-javascript' :

                    $body = $this->toJSON();

                    break;

                case 'text/html' :
                case 'application/x-www-form-urlencoded':
                default:

                    $body = http_build_query($this->toArray());

                    break;

            }

        }

        if($encryption_key !== null){

            if($encryption_cipher === null)
                $encryption_cipher = Client::$encryption_default_cipher;

            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($encryption_cipher));

            $body = base64_encode(openssl_encrypt($body, $encryption_cipher, $encryption_key, OPENSSL_RAW_DATA, $iv));

            $this->setHeader(Client::$encryption_header, base64_encode($iv));

        }

        //Always include a content-length header.  Fixes POST to IIS returning 411 (length required).
        $this->setHeader('Content-Length', strlen($body));

        /*
         * Build the header section
         */
        $access_uri = $uri->path() . $uri->queryString();

        $http_request = "{$this->method} {$access_uri} HTTP/1.1\r\n";

        foreach($this->headers as $header => $value){

            if(!is_array($value))
                $value = array($value);

            foreach($value as $hdr)
                $http_request .= $header . ': ' . $hdr . "\r\n";

        }

        $http_request .= "\r\n" . $body;

        return $http_request;

    }

    /**
     * Add Basic HTTP authentication
     *
     * Basic authentication should ONLY be used over HTTPS.
     *
     * Other methods are not yet supported, however Bearer is implemented using Request::authorization() method.
     *
     * @param mixed $username The username to send
     * @param mixed $password The password to send
     */
    public function authorise($username, $password) {

        $auth = 'Basic ' . base64_encode($username . ':' . $password);

        $this->setHeader('Authorization', $auth);

    }

    /**
     * Alias for 'authorisation' for the bad 'merican spells.
     *
     * @param mixed $user
     * @param mixed $type
     * @return bool
     */
    public function authorization($user, $type = 'Bearer'){

        return $this->authorisation($user, $type);

    }

    /**
     * Use an auth adapter to set an Oauth token on the request
     *
     * @param \Hazaar\Auth\Adapter $user
     * @param string $type
     * @return bool
     */
    public function authorisation(\Hazaar\Auth\Adapter $user, $type = null){

        if($token = $user->getToken()){

            if(!$type)
                $type = $user->getTokenType();

            $this->setHeader('Authorization', $type . ' ' . $token);

            return true;

        }

        return false;

    }

    /**
     * Set a local PEM encoded certificate to use for SSL communication
     *
     * @param mixed $local_cert
     * @param mixed $passphrase
     * @throws Exception\CertificateNotFound
     * @return boolean
     */
    public function setLocalCertificate($local_cert, $passphrase = null, $local_pk = null){

        if(!file_exists((string)$local_cert))
            throw new Exception\CertificateNotFound();

        $result = stream_context_set_option($this->context, 'ssl', 'local_cert', $local_cert);

        if($local_pk){

            if(!file_exists((string)$local_pk))
                throw new \Exception('Local private key specified but the file does not exist!');

            stream_context_set_option($this->context, 'ssl', 'local_pk', $local_pk);

        }

        if($passphrase)
            stream_context_set_option($this->context, 'ssl', 'passphrase', $passphrase);

        return $result;

    }

    /**
     * Wrapper function to the internal PHP function stream_context_set_option() function.
     *
     * See http://php.net/manual/en/context.ssl.php documentation for all the available wrappers and options.
     *
     * @param mixed $options Must be an associative array in the format $arr['wrapper']['option'] = $value;
     *
     * @return boolean Returns TRUE on success or FALSE on failure.
     */
    public function setContextOption($options){

        return stream_context_set_option($this->context, $options);

    }

    /**
     * When using SSL communications, this allows the use of self-signed certificates.
     *
     * @param boolean $value
     *
     * @return boolean
     */
    public function allowSelfSigned($value = true){

        return stream_context_set_option($this->context, 'ssl', 'allow_self_signed', $value);

    }

    /**
     * When using SSL communications, this sets whether peer certificate verification is used.
     *
     * @param boolean $value
     *
     * @return boolean
     */
    public function verifyPeer($value = true){

        return stream_context_set_option($this->context, 'ssl', 'verify_peer', $value);

    }

    /**
     * When using SSL communications, this sets whether peer name verification is used.
     *
     * @param boolean $value
     *
     * @return boolean
     */
    public function verifyPeerName($value = true){

        return stream_context_set_option($this->context, 'ssl', 'verify_peer_name', $value);

    }

}

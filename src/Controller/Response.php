<?php

namespace Hazaar\Controller;

abstract class Response implements Response\_Interface {

    /*
     * Use text/html as the default type as it is the most widely accepted.
     */
    protected $headers      = array();

    protected $headers_set  = FALSE;

    protected $content;

    protected $content_type = 'text/plain';

    protected $status_code;

    protected $controller;

    protected $compress     = false;

    protected $tidy         = FALSE;

    public static $encryption_key;

    function __construct($type = "text/html", $status = 501) {

        $this->content_type = $type;

        $this->status_code = $status;

    }

    public function setController($controller) {

        $this->controller = $controller;

    }

    /**
     * Add Header Directive
     */
    public function setHeader($key, $value, $overwrite = TRUE) {

        if($overwrite) {

            $this->headers[$key] = $value;

        } else {

            if(! is_array($this->headers[$key]))
                $this->headers[$key] = array();

            $this->headers[$key][] = $value;

        }

    }

    public function removeHeader($key) {

        if(array_key_exists($key, $this->headers))
            unset($this->headers[$key]);

    }

    public function & getHeader($key) {

        if($header = ake($this->headers, $key)) {

            if(is_array($header))
                return $header[0];

            return $this->headers[$key];

        }

        $null = NULL;

        return $null;

    }

    public function clearHeaders() {

        $this->headers = array();

    }

    public function setCompression($toggle) {

        $this->compress = boolify($toggle);

    }

    public function modified() {

        return ($this->status_code !== 304);

    }

    /**
     * Quick method to set the content type
     */
    public function setContentType($type = NULL) {

        if(! $type) {

            /*
             * Try and detect the mimetype of the data we have.
             */

            $finfo = new \finfo(FILEINFO_MIME);

            $type = $finfo->buffer($this->getContent());

        }

        $this->content_type = $type;

    }

    /**
     * Quick method to get the content type
     */
    public function getContentType() {

        return $this->content_type;

    }

    public function getContent() {

        return $this->content;

    }

    public function setContent($content) {

        $this->content = $content;

    }

    public function getContentLength() {

        return strlen($this->content);

    }

    public function hasContent() {

        return (strlen($this->content) > 0);

    }

    public function addContent($content) {

        $this->content .= $content;

    }

    public function setStatus($status) {

        $this->status_code = intval($status);

    }

    public function getStatus() {

        return $this->status_code;

    }

    public function getStatusMessage() {

        return http_response_text($this->status_code);

    }

    public function enableTidy($state = TRUE) {

        if($state && in_array('tidy', get_loaded_extensions())) {

            $this->tidy = TRUE;

        } else {

            $this->tidy = FALSE;

        }

    }

    public function setHeaders($content_length = NULL) {

        http_response_code($this->status_code);

        if($content_type = $this->getContentType())
            header('Content-Type: ' . $content_type);

        if($content_length === null)
            $content_length = $this->getContentLength();

        header('Content-Length: ' . $content_length);

        foreach($this->headers as $name => $header) {

            if(is_array($header)) {

                foreach($header as $value)
                    header($name . ': ' . $value, FALSE);

            } else {

                header($name . ': ' . $header);

            }

        }

        $this->headers_set = TRUE;

    }

    public function ignoreHeaders() {

        $this->headers_set = TRUE;

    }

    /**
     * Write the response to the output buffer
     *
     * @return null
     */
    public function __writeOutput() {

        $content = '';

        if(method_exists($this, '__prepare'))
            $this->__prepare($this->controller);

        if($this->modified()){

            if($this->tidy && substr($this->content_type, 0, 4) == 'text') {

                $tidy = new \tidy();

                $config = array(
                    'indent'         => TRUE,
                    'vertical-space' => 'no',
                    'doctype'        => 'auto',
                    'wrap'           => 0
                );

                $content = $tidy->repairString($this->getContent(), $config);

            } else {

                $content = $this->getContent();

            }

            if(! $content)
                $content = '';

        }

        if(Response::$encryption_key !== null){

            $encryption_cipher = \Hazaar\Http\Client::$encryption_default_cipher;

            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($encryption_cipher));

            $content = base64_encode(openssl_encrypt($content, $encryption_cipher, Response::$encryption_key, OPENSSL_RAW_DATA, $iv));

            $this->setHeader(\Hazaar\Http\Client::$encryption_header, base64_encode($iv));

        }

        if(php_sapi_name() !== 'cli' && $this->headers_set !== true) {

            if(headers_sent())
                throw new Exception\HeadersSent();

            $this->setHeaders(strlen($content));

        }

        echo $content;

        flush();

    }

    public function __sleep(){

        return array('content', 'content_type', 'headers', 'status_code', 'tidy');

    }

}


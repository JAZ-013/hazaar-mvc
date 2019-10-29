<?php

namespace Hazaar\Http;

class Response {

    //Status code of the response
    public $status;

    //Status message of the response
    public $name;

    //HTTP Version of the response
    public $version;

    public $headers = array();

    //Temporary buffer used while parsing responses
    private $buffer;

    //The actual body of the response
    public $body;

    //Parsing input
    private $headers_parsed = FALSE;

    //Used for chunked data parsing
    private $chunked = FALSE;

    //Length of the current chunk
    private $chunk_len = 0;

    private $chunk_offset = 0;

    //Public variables
    public $content_length  = 0;

    public $bytes_remaining = -1;

    function __construct(int $status = NULL, array $headers = array(), $version = 'HTTP/1.1') {

        $this->status = $status;

        $this->name = $this->getStatusMessage($this->status);

        $this->version = $version;

        $this->headers = $headers;

        if(count($this->headers) > 0)
            $this->headers_parsed = TRUE;

    }

    public function write($string) {

        $this->body .= $string;

        $this->content_length = strlen($this->body);

    }

    public function read($buffer) {

        $this->buffer .= $buffer;

        $param = null;

        if(! $this->header_parsed) {

            $offset = 0;

            while(($del = strpos($this->buffer, "\r\n", $offset)) !== FALSE) {

                //If we get an empty line, that's the end of the headers
                if($del == $offset) {

                    $this->header_parsed = TRUE;

                    $offset += 2;

                    break;

                }

                $header = substr($this->buffer, $offset, $del - $offset);

                if($this->status === null){

                    if(!preg_match('/(HTTP\/[\d\.]+)\s+(\d+)\s+(.*)/', $header, $matches))
                        throw new \Hazaar\Exception('Got bad HTTP response: ' . $header);

                    //Parse the response header so we can throw errors if needed
                    list($null, $this->version, $this->status, $this->name) = $matches;

                    settype($this->status, 'integer');

                }elseif($split = strpos($header, ':')) {

                    $param = strtolower(substr($header, 0, $split));

                    $value = trim(substr($header, $split + ($param ? 1 : 0)));

                    if(array_key_exists($param, $this->headers)) {

                        if(! is_array($this->headers[$param])) {

                            $this->headers[$param] = array($this->headers[$param]);

                        }

                        $this->headers[$param][] = $value;

                    } else {

                        $this->headers[$param] = $value;

                    }

                    switch($param) {
                        case 'content-length' :
                            $this->content_length = (int)$value;

                            break;

                        case 'transfer-encoding' :
                            if($value == 'chunked') {

                                $this->chunked = TRUE;

                                $this->chunk_len = -1;

                            }

                            break;

                    }

                } elseif($param !== null) {

                    $this->headers[$param] .= ' ' . trim($header);

                } else {

                    throw new \Hazaar\Exception('Got bad HTTP response header: ' . $header);

                }

                $offset = $del + 2;
                //Set the offset to the end of the last delimiter

            }

            //Truncate the buffer to remove the crap we've already just processed
            if($offset > 0)
                $this->buffer = substr($this->buffer, $offset);

        }

        /*
         * Now process the content.  We check if the headers are set first.
         */
        if($this->header_parsed) {

            /*
             * Check the length of our content, either chunked or normal
             */

            if($this->chunked) {

                while(strlen($this->buffer) > 0) {

                    //Get the current chunk length
                    $chunk_len_end = strpos($this->buffer, "\r\n", $this->chunk_offset + 1) + 2;

                    $chunk_len_string = substr($this->buffer, 0, $chunk_len_end - $this->chunk_offset - 2);

                    $chunk_len = hexdec($chunk_len_string) + 2;

                    //If we don't have the whole chunk, bomb out for now.  This expects that this read method will be
                    // called again later with more of the response body.  The +2 includes the CRLF chunk terminator.
                    if((strlen($this->buffer) - $chunk_len_end) < $chunk_len)
                        break;

                    if($chunk_len == 0) {

                        $this->buffer = NULL;

                        if(! $this->content_length)
                            $this->content_length = strlen($this->body);

                        return TRUE;

                    } else {

                        //Get the current chunk
                        $chunk = substr($this->buffer, $chunk_len_end, $chunk_len - 2);

                        //TODO: This is where we could fire off a callback with the current data chunk;
                        //call_user_func($callback, $chunk);

                        //Append the current chunk onto the body
                        $this->body .= $chunk;

                        //Remove the processed chunk from the buffer
                        $this->buffer = substr($this->buffer, $chunk_len_end + $chunk_len);

                    }

                }

            } else {

                //If we have a content length, check how many bytes are left to retrieve
                if($this->content_length > 0) {

                    $len = strlen($this->buffer);

                    if($len >= $this->content_length) {

                        $encoding = (array_key_exists('content-encoding', $this->headers) ? strtolower($this->headers['content-encoding']) : NULL);

                        switch($encoding) {
                            case  'gzip' :
                                $this->body = gzdecode($this->buffer);

                                break;

                            default :
                                $this->body .= $this->buffer;

                                break;
                        }

                        $this->buffer = NULL;

                        return TRUE;

                    }

                    $this->bytes_remaining = $this->content_length - $len;

                    //Otherwise just start filling the body with data
                } else {

                    $this->body .= $this->buffer;

                    $this->buffer = NULL;

                }

            }

        }

        return FALSE;
        //Return false to indicate that we haven't received all the content body

    }

    public function __get($key) {

        return $this->getHeader($key);

    }

    public function hasHeader($key){

        return array_key_exists(strtolower($key), $this->headers);

    }

    public function getHeader($header) {

        $header = strtolower($header);

        if(array_key_exists($header, $this->headers))
            return $this->headers[$header];

        return NULL;

    }

    public function size() {

        return strlen($this->body);

    }

    public function toString() {

        $http_response = "{$this->version} {$this->status} {$this->name}\r\n";

        foreach($this->headers as $header => $value)
            $http_response .= $header . ': ' . $value . "\r\n";

        $content_len = strlen($this->body);

        if($content_len > 0)
            $http_response .= 'Content-Length: ' . $content_len . "\r\n";

        $http_response .= "\r\n" . $this->body;

        return $http_response;

    }

    public function getStatusMessage($code = NULL) {

        if(!$code)
            $code = $this->status_code;

        return http_response_text($code);

    }

    public function getContentType(&$args = array()){

        $header = str_replace('; ', ';', $this->getHeader('content-type'));

        if(($start = strpos($header, ';')) === false)
            return $header;

        $content_type = substr($header, 0, $start);

        $args = array_unflatten(substr($header, $start + 1), '=', ';');

        return $content_type;

    }

    public function body($raw = false){

        if($raw === true)
            return $this->body;

        $content_type = $this->getContentType($args);

        if($content_type === 'application/json'){

            return json_decode($this->body);

        }elseif(substr($content_type, 0, 9) === 'multipart'){

            if(!array_key_exists('boundary', $args))
                throw new \Hazaar\Exception('Received multipart content type with no boundary!');

            $parts = explode('--' . $args['boundary'], trim($this->body));

            if(!($parts[0] === '' && $parts[count($parts)-1] === '--'))
                throw new \Hazaar\Exception('Invalid multipart response received!');

            $this->body = array();

            for($i = 1; $i < (count($parts) - 1); $i++){

                $offset = 2;

                $headers = array();

                while(($del = strpos($parts[$i], "\r\n", $offset)) !== FALSE) {

                    //If we get an empty line, that's the end of the headers
                    if($del == $offset) {

                        $offset += 2;

                        break;

                    }

                    $header = substr($parts[$i], $offset, $del - $offset);

                    if($split = strpos($header, ':')){

                        $param = strtolower(substr($header, 0, $split));

                        $value = trim(substr($header, $split + ($param ? 1 : 0)));

                        $headers[$param] = $value;

                    }

                    $offset = $del + 2;

                }

                $this->body[] = array('headers' => $headers, 'body' => substr($parts[$i], $offset));

            }

        }

        return $this->body;

    }

    public function decrypt($key, $cipher = null){

        if(!($iv = $this->getHeader(Client::$encryption_header)))
            return false;

        if($cipher === null)
            $cipher = Client::$encryption_default_cipher;

        $iv = base64_decode($iv);

        $this->body = openssl_decrypt(base64_decode($this->body), $cipher, $key, OPENSSL_RAW_DATA, $iv);

        return true;

    }

}


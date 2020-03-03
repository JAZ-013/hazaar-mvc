<?php

namespace Hazaar\Mail\Mime;

class Part {

    protected $headers = array();

    protected $content;

    protected $crlf = "\r\n";

    function __construct($content = null, $content_type = 'text/plain') {

        $this->setContent($content);

        $this->setContentType($content_type);

    }

    public function setHeader($header, $content){

        $this->headers[$header] = $content;

    }

    public function getHeader($header){

        return ake($this->headers, $header);

    }

    public function setContentType($type) {

        $this->headers['Content-Type'] = $type . '; charset=utf-8';

    }

    public function getContentType() {

        return ake($this->headers, 'Content-Type');

    }

    public function setDescription($text){

        $this->headers['Content-Description'] = $text;

    }

    public function setContent($content) {

        $this->content = $content;

    }

    public function detect_break($content, $default = "\r\n"){

        if(($pos = strpos($content, "\n")) == false)
            return $default;

        if($pos > 0 && substr($content, $pos - 1, 1) == "\r")
            return "\r\n";

        return "\n";

    }

    public function encode($width_limit = 76) {

        $message = 'Date: ' . date('r', time()) . $this->crlf;

        foreach($this->headers as $header => $content)
            $message .= $header . ': ' . $content . $this->crlf;

        $message .= $this->crlf . utf8_encode(($width_limit > 0) ? wordwrap($this->content, $width_limit, $this->detect_break($this->content), true) : $this->content) . $this->crlf;

        return $message;

    }

}

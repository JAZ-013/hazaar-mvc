<?php

namespace Hazaar\Mail;

class Html extends \Hazaar\Mail\Mime\Part {

    private $boundary;

    private $html;

    function __construct($html) {

        parent::__construct();

        $this->html = $html instanceof \Hazaar\Mail\Template ? $html : new \Hazaar\Mail\Template($html);

        $this->boundary = '----alt_border_' . uniqid();

        parent::setContentType('multipart/alternative; boundary="' . $this->boundary . '"');

    }

    public function encode($width_limit = 998, $params = null) {

        $html = $this->html->render($params);

        $text = new \Hazaar\Mail\Mime\Part(str_replace('<br>', "\r\n", strip_tags($html, '<br>')), 'text/plain');

        $html = new \Hazaar\Mail\Mime\Part($html, 'text/html');

        $message = '--' . $this->boundary . $this->crlf . $text->encode($width_limit) . $this->crlf;

        $message .= '--' . $this->boundary . $this->crlf . $html->encode($width_limit) . $this->crlf . "--{$this->boundary}--" . $this->crlf . $this->crlf;

        $this->setContent($message);

        return parent::encode(0);

    }

}

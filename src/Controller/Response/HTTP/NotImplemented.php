<?php

namespace Hazaar\Controller\Response\HTTP;

class NotImplemented extends \Hazaar\Controller\Response {

    function __construct($content_type = "text/html") {

        parent::__construct($content_type, 501);

    }

}
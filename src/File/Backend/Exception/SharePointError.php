<?php

namespace Hazaar\File\Backend\Exception;

class SharePointError extends \Hazaar\Exception {

    private $response;
    
    function __construct($message, $response = null, $code = 500){

        $this->response = $response;

        return parent__construct($message, $code);

    }

    public function getResponse(){

        return $this->response;

    }

}

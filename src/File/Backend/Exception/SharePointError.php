<?php

namespace Hazaar\File\Backend\Exception;

class SharePointError extends \Hazaar\Exception {

    private $response;
    
    function __construct($message, $response = null, $code = 500){

        $this->response = $response;

        return parent::__construct($message, $code);

    }

    public function getResponse(){

        return $this->response;

    }

}

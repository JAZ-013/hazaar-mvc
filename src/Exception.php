<?php

namespace Hazaar;

/**
 * Enhanced Exception class for Hazaar MVC
 * 
 * This exception class contains some useful additions to the standard [[Exception]] class.  Firstly,
 * it supports a new "short message" that will be used when the full error display is disabled, such
 * as it would be in a production environment.  This allows a more simplified message to be displayed
 * to users that can not really be used for debugging, but looks nicer.
 * 
 * This class also allows the line, file, and original message to be updated.  This is used in templates
 * when parsing the template throws an error, we are able to set the line and file to that of the template.
 */
class Exception extends \Exception {

    protected $name;

    protected $short_message;

    /**
     * \Hazaar\Exception constructor
     * @param mixed $message The exception message
     * @param mixed $code The error code, also used as the HTTP response code
     * @param mixed $short_message An optional short message to display when full error display is disabled.
     */
    function __construct($message, $code = 500, $short_message = null) {

        parent::__construct($message, $code);

        $this->short_message = $short_message;

    }

    /**
     * Returns the name of the Exception class in the case it has been extended
     * @return mixed
     */
    public function getName() {

        if (! $this->name)
            return preg_replace('/Hazaar\\\\Exception\\\\/', '', get_class($this));

        return $this->name;

    }

    /**
     * Returns the short message.
     * 
     * @return mixed
     */
    public function getShortMessage() {

        if ($this->short_message)
            return $this->short_message;

        $message = null;

        if ($this->code == 404)
            $message = "We can't seem to find the page you are looking for!";
        elseif ($this->code == 500)
            $message = "It would appear that we may have broken something!";

        return $message;

    }
    
    /**
     * Set the error code
     * 
     * @param int $code 
     */
    public function setCode($code){

        $this->code = intval($code);

    }

    /**
     * Set the error message
     * 
     * @param string $message 
     */
    public function setMessage($message){

        $this->message = $message;

    }

    /**
     * Set the file name that the exception occurred in.
     * 
     * @param string $file 
     */
    public function setFile($file){

        $this->file = $file;

    }

    /**
     * Set the line number that threw the exception
     * 
     * @param int $line 
     */
    public function setLine($line){

        $this->line = intval($line);

    }

}

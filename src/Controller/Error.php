<?php

/**
 * @file        Controller/Error.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Controller;

define('ERR_TYPE_ERROR', 0);

define('ERR_TYPE_EXCEPTION', 1);

define('ERR_TYPE_SHUTDOWN', 2);

/**
 * @brief Basic controller class
 *
 * @detail This controller does basic stuff
 */
class Error extends \Hazaar\Controller\Action {

    protected $type = ERR_TYPE_ERROR;

    protected $errno, $errstr, $errfile = NULL, $errline = NULL, $errcontext = NULL, $errtype = NULL, $errclass = NULL, $callstack, $short_message = "";

    // The HTTP error code to throw. By default it is 500 Internal Server Error.
    protected $code = 500;

    protected $status = 'Internal Error';

    protected $response = 'html';

    private $status_codes = array();

    function __construct($name, \Hazaar\Application $application){

        parent::__construct($name, $application, false);

    }

    function __initialize(\Hazaar\Application\Request $request = NULL) {

        parent::__initialize($request);

        if ($request instanceof \Hazaar\Application\Request\Http && function_exists('apache_request_headers')) {

            if(!($this->response = $this->application->getResponseType())){

                $h = apache_request_headers();

                if (array_key_exists('X-Requested-With', $h)) {

                    switch ($h['X-Requested-With']) {
                        case 'XMLHttpRequest' :
                            $this->response = 'json';

                            break;

                        case 'XMLRPCRequest' :
                            $this->response = 'xmlrpc';

                            break;
                    }
                }

            }

        } elseif (getenv('HAZAAR_SID')) {

            $this->response = 'runner';

        } else {

            $this->response = 'text';

        }

        $this->status_codes = $this->loadStatusCodes();

    }

    private function loadStatusCodes() {

        $status_codes = array();

        if ($file = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'Http_Status.dat')) {

            $h = fopen($file, 'r');

            while($line = fgets($h)) {

                if (preg_match('/^(\d*)\s(.*)$/', $line, $matches)) {

                    $status_codes[$matches[1]] = $matches[2];
                }
            }
        }

        return $status_codes;

    }

    public function getStatusMessage($code = NULL) {

        if($code === null)
            $code = $this->code;

        return (array_key_exists($code, $this->status_codes) ? $this->status_codes[$code] : NULL);

    }

    public function setError() {

        $args = func_get_args();

        if ($args[0] instanceof \Exception) {

            $e = $args[0];

            $this->type = ERR_TYPE_EXCEPTION;

            $this->errno = $e->getCode();

            $this->errstr = $e->getMessage();

            $this->errfile = $e->getFile();

            $this->errline = $e->getLine();

            if ($e instanceof \Hazaar\Exception)
                $this->short_message = $e->getShortMessage();

            if ($e instanceof \Hazaar\Exception) {

                $context = $e->getName();
            } else {

                $context = get_class($e);
            }

            $this->errclass = $context;

            $this->callstack = $e->getTrace();

            if (!array_key_exists($this->code = $e->getCode(), $this->status_codes)) {

                $this->code = 500;
            }
        } elseif (is_array($args[0])) {

            $this->type = ERR_TYPE_SHUTDOWN;

            $this->errno = $args[0]['type'];

            $this->errstr = $args[0]['message'];

            $this->errfile = $args[0]['file'];

            $this->errline = $args[0]['line'];

            $this->errtype = 'ERROR::FATAL';

            $this->callstack = $args[1];
        } else {

            $this->type = ERR_TYPE_ERROR;

            if ($args[0] instanceof \Error) { // PHP7 Error Class

                $this->errno = $args[0]->getCode();

                $this->errstr = $args[0]->getMessage();

                $this->errfile = $args[0]->getFile();

                $this->errline = $args[0]->getLine();

                $this->callstack = $args[0]->getTrace();
            } else {

                $this->errno = ake($args, 0);

                $this->errstr = ake($args, 1);

                $this->errfile = ake($args, 2);

                $this->errline = ake($args, 3);

                $this->errcontext = '<pre>' . print_r($args[4], TRUE) . '</pre>';

                $this->callstack = $args[5];
            }
        }

        $this->status = $this->getStatusMessage($this->code);

        return NULL;

    }

    public function getErrorMessage() {

        return $this->errstr . ' on line ' . $this->errline . ' in file ' . $this->errfile;

    }

    public function getTrace() {

        return $this->callstack;

    }

    final public function __run() {

        if ($this->response && method_exists($this, $this->response))
            $response = call_user_func(array($this, $this->response));
        elseif (method_exists($this, 'run'))
            $response = $this->run();
        else
            $response = $this->html();

        if(!$response instanceof \Hazaar\Controller\Response){

            if(is_array($response)) {

                $response = new Response\Json($response, $this->code);

            } else {

                $response = new Response\Html($this->code);

                /*
                 * Execute the action helpers.  These are responsible for actually rendering any views.
                 */
                $this->_helper->execAllHelpers($this, $response);

            }

        }

        $response->setController($this);

        return $response;

    }

    public function __shutdown($response = null){

        $this->report();

    }

    public function clean_output_buffer() {

        while(count(ob_get_status()) > 0) {

            ob_end_clean();
        }

    }

    private function runner(){

        echo "Runner Error #{$this->errno} at line #{$this->errline} in file {$this->errfile}\n\n{$this->errstr}\n\n";

        exit($this->errno);

    }

    private function json(){

        $error = array(
            'ok' => FALSE,
            'error' => array(
                'type' => $this->errno,
                'status' => $this->status,
                'str' => $this->errstr
            ),
            'timestamp' => time()
        );

        if(ini_get('display_errors')){

            $error['error']['line'] = $this->errline;

            $error['error']['file'] = $this->errfile;

            $error['error']['context'] = $this->errcontext;

            $error['trace'] = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);

            $error['config'] = $this->application->config->toArray();

        }

        return new Response\Json($error, $this->code);

    }

    private function xmlrpc(){

        $xml = new \SimpleXMLElement('<xml/>');

        $struct = $xml->addChild('fault')->addChild('value')->addChild('struct');

        $code = $struct->addChild('member');

        $code->addChild('name', 'faultCode');

        $code->addChild('value')->addChild('int', $this->errno);

        $status = $struct->addChild('member');

        $status->addChild('name', 'faultString');

        $status->addChild('value')->addChild('string', $this->status);

        $string = $struct->addChild('member');

        $string->addChild('name', 'faultString');

        $string->addChild('value')->addChild('string', $this->errstr);

        $file = $struct->addChild('member');

        $file->addChild('name', 'faultFile');

        $file->addChild('value')->addChild('string', $this->errfile);

        $line = $struct->addChild('member');

        $line->addChild('name', 'faultLine');

        $line->addChild('value')->addChild('int', $this->errline);

        return new Response\Xml($xml->asXML());

    }

    private function text(){

        $out = "*****************************\n\tEXCEPTION\n*****************************\n\n";

        $out .= "Environment:\t" . APPLICATION_ENV . "\n";

        $out .= "Timestamp:\t" . date('c') . "\n";

        if ($this->errno > 0)
            $out .= "Error:\t\t#" . $this->errno . "\n";

        $out .= "File:\t\t" . $this->errfile . "\n";

        $out .= "Line:\t\t" . $this->errline . "\n";

        $out .= "Message:\t" . $this->errstr . "\n";

        $out .= "Context:\t" . $this->errcontext . "\n\n";

        $out .= "Backtrace:\n\n";

        foreach($this->callstack as $id => $call) {

            if (array_key_exists('class', $call))
                $out .= "$id - " . str_pad(ake($call, 'file'), 75, ' ', STR_PAD_RIGHT) . ' ' . str_pad(ake($call, 'line'), 4, ' ', STR_PAD_RIGHT) . " $call[class]::$call[function]\n";

            else
                $out .= "$id - $call[function]\n";
        }

        $out .= "\n";

        return new Response\Text($out, $this->code);

    }

    private function html(){

        $response = new Response\Html($this->code);

        $view = new \Hazaar\View\Layout('@error/error');

        $view->registerMethodHandler($this);

        $view->type = $this->type;

        $view->err = array(
            'code' => $this->errno,
            'message' => $this->errstr,
            'file' => $this->errfile,
            'line' => $this->errline,
            'context' => $this->errcontext,
            'class' => $this->errclass,
            'type' => $this->errtype,
            'short_message' => ($this->short_message ? $this->short_message : $this->status)
        );

        $view->trace = $this->callstack;

        $view->code = $this->code;

        $view->status = $this->status;

        $response->setContent($view->render($this));

        return $response;

    }

    /**
     * Report the error to the Hazaar error tracker API.
     *
     * This looks for the most unobtrusive way to report the error.  Using either CURL or file_get_contents
     * if one of them is available.  If not, then we don't bother doing this at all.
     */
    private function report(){

        $check_path = ROOT_PATH
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'hazaarlabs';

        if(substr($this->errfile, 0, strlen($check_path)) !== $check_path || $this->code < 500)
            return false;

        $type = 'error';

        switch($this->type){
            case ERR_TYPE_EXCEPTION:

                $type = 'exception';

                break;

            case ERR_TYPE_SHUTDOWN:

                $type = 'shutdown';

                break;

            case ERR_TYPE_ERROR:
            default:

                $type = 'error';

                break;

        }

        $url = 'http://api.hazaarmvc.com/api/report/' . $type;

        $data = json_encode(array(
            'status' => $this->code,
            'error' => array(
                'type' => $this->errno,
                'status' => $this->status,
                'line' => $this->errline,
                'file' => $this->errfile,
                'context' => $this->errcontext,
                'str' => $this->errstr
            ),
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            'config' => $this->application->config->toArray()
        ));

        if(ini_get('allow_url_fopen') ) {

            $options = array(
                    'http' => array(
                    'header'  => "Content-type: application/json\r\n",
                    'method'  => 'POST',
                    'content' => $data,
                )
            );

            $result = file_get_contents($url, false, stream_context_create($options));

            return ($result);

        }elseif(function_exists('curl_version')){

            /**
             * POST error data to the Hazaar error tracker
             */
            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");

            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($data))
            );

            return curl_exec($ch);

        }

        return false;

    }

}


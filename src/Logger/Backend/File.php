<?php

namespace Hazaar\Logger\Backend;

class File extends \Hazaar\Logger\Backend {

    private $hLog;

    private $hErr;

    public function init() {

        $this->addCapability('write_trace');

        $this->setDefaultOption('write_ip', TRUE);

        $this->setDefaultOption('write_timestamp', TRUE);

        $this->setDefaultOption('write_uri', TRUE);

        $log_file = \Hazaar\Application::getInstance()->runtimePath('hazaar.log');

        $this->setDefaultOption('logfile', $log_file);

        if(($log_file && $this->getOption('logfile')) && is_writable($log_file)){

            if(($this->hLog = fopen($log_file, 'a')) == FALSE)
                throw new Exception\OpenLogFileFailed($log_file);

        }
        
        $error_file = \Hazaar\Application::getInstance()->runtimePath('error.log');

        $this->setDefaultOption('errfile', $error_file);

        if(($error_file = $this->getOption('errfile')) && is_writable($error_file)){

            if(($this->hErr = fopen($error_file, 'a')) == FALSE)
                throw new Exception\OpenLogFileFailed($error_file);

        }

    }

    public function postRun() {

        if($this->hLog)
            fclose($this->hLog);

        if($this->hErr)
            fclose($this->hErr);

    }

    public function write($tag, $message, $level = E_NOTICE) {

        if(!$this->hLog)
            return false;

        $remote = ake($_SERVER, 'REMOTE_ADDR', '--');

        $line = array();

        if($this->getOption('write_ip'))
            $line[] = $remote;

        if($this->getOption('write_timestamp'))
            $line[] = date('Y-m-d H:i:s');

        $line[] = str_pad(strtoupper($this->getLogLevelId($level)), 6, ' ', STR_PAD_RIGHT);

        if($this->getOption('write_uri'))
            $line[] = ake($_SERVER, 'REQUEST_URI');

        $line[] = $tag;

        $line[] = $message;

        fwrite($this->hLog, implode(' | ', $line) . "\r\n");

        if($this->hErr && $level == E_ERROR)
            fwrite($this->hErr, implode(' | ', $line) . "\r\n");

        return true;

    }

    public function trace() {

        ob_start();

        debug_print_backtrace();

        $trace = ob_get_clean();

        fwrite($this->hLog, $trace);

    }

}

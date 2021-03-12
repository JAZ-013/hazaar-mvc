<?php

namespace Hazaar\Logger\Backend;

class File extends \Hazaar\Logger\Backend {

    private $hLog;

    private $hErr;

    private $level_padding = 0;

    public function init() {

        $this->addCapability('write_trace');

        $this->setDefaultOption('write_ip', true);

        $this->setDefaultOption('write_timestamp', true);

        $this->setDefaultOption('write_pid', false);

        $this->setDefaultOption('logfile', \Hazaar\Application::getInstance()->runtimePath('hazaar.log'));

        if(($log_file = $this->getOption('logfile')) && is_writable(dirname($log_file))){

            if(($this->hLog = fopen($log_file, 'a')) == false)
                throw new Exception\OpenLogFileFailed($log_file);

        }
        
        $this->setDefaultOption('errfile', \Hazaar\Application::getInstance()->runtimePath('error.log'));

        if(($error_file = $this->getOption('errfile')) && is_writable(dirname($error_file))){

            if(($this->hErr = fopen($error_file, 'a')) == false)
                throw new Exception\OpenLogFileFailed($error_file);

        }

        $this->level_padding = max(array_map('strlen', array_keys($this->levels))) - strlen(self::LOG_LEVEL_PREFIX);

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

        if($this->getOption('write_pid'))
            $line[] = getmypid();

        $line[] = str_pad(strtoupper($this->getLogLevelName($level)), $this->level_padding, ' ', STR_PAD_RIGHT);

        $line[] = str_pad($tag, 8, ' ', STR_PAD_RIGHT);

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

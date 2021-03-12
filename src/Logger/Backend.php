<?php

namespace Hazaar\Logger;

abstract class Backend implements Backend\_Interface {

    private $options      = array();

    private $capabilities = array();

    protected $levels;

    protected const LOG_LEVEL_PREFIX = 'LOG_';

    function __construct($options) {

        $this->levels = array_filter(get_defined_constants(), function($value){ 
            return substr($value, 0, strlen(self::LOG_LEVEL_PREFIX)) === self::LOG_LEVEL_PREFIX; 
        }, ARRAY_FILTER_USE_KEY);

        /*
         * Set the options we were given which will overwrite any defaults
         */
        if(! is_array($options))
            $options = array();

        $this->options = $options;

        $this->init();

    }

    public function postRun() {

        //do nothing

    }

    public function setDefaultOption($key, $value) {

        if(! array_key_exists($key, $this->options)) {

            $this->setOption($key, $value);

        }

    }

    public function setOption($key, $value) {

        $this->options[$key] = $value;

    }

    public function getOption($key) {

        if(! array_key_exists($key, $this->options))
            return NULL;

        return $this->options[$key];

    }

    public function hasOption($key) {

        return array_key_exists($key, $this->options);

    }

    public function getLogLevelId($level) {

        $level = strtoupper($level);

        if(substr($level, 0, strlen(self::LOG_LEVEL_PREFIX)) !== self::LOG_LEVEL_PREFIX)
            $level = self::LOG_LEVEL_PREFIX . $level;

        return defined($level) ? constant($level) : 0;

    }

    public function getLogLevelName($level) {

        return substr(array_search($level, $this->levels), strlen(self::LOG_LEVEL_PREFIX));

    }

    protected function addCapability($capability) {

        $this->capabilities[] = $capability;

    }

    public function getCapabilities() {

        return $this->capabilities;

    }

    public function can($capability) {

        return in_array($capability, $this->capabilities);

    }

}

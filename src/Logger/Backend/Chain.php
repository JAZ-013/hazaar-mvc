<?php

namespace Hazaar\Logger\Backend;

class Chain extends \Hazaar\Logger\Backend {

    private $backends = array();

    public function init() {

        $this->setDefaultOption('chain', array('backend' => array('file')));

        $chain = $this->getOption('chain');

        if(is_array($chain['backend'])) {

            foreach($chain['backend'] as $backend_name) {

                $backend_class = 'Hazaar_Logger_Backend_' . ucfirst($backend_name);

                $backend = new $backend_class( array());

                $this->backends[] = $backend;

                foreach($backend->getCapabilities() as $capability)
                    $this->addCapability($capability);

            }

        }

    }

    public function postRun() {

        foreach($this->backends as $backend) {

            $backend->postRun();

        }

    }

    public function write($message, $level = LOG_NOTICE) {

        foreach($this->backends as $backend) {

            if(!$backend->can('write_objects') && (is_array($message) || is_object($message))) {

                $backend->write(preg_replace('/\s+/', ' ', print_r($message, true)), $level);

            } else {

                $backend->write($message, $level);

            }

        }

    }

    public function trace() {

        foreach($this->backends as $backend) {

            if($backend->can('write_trace'))
                $backend->trace();

        }

    }

}

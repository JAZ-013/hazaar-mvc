<?php

namespace Hazaar\Mail;

abstract class Transport implements Transport\_Interface {

    protected $options;

    final function __construct($options){

        if(!$options instanceof \Hazaar\Map)
            $options = new \Hazaar\Map($options);
            
        $this->options = $options;

    }

}

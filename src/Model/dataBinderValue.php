<?php

namespace Hazaar\Model;

/**
 * dataBinderValue short summary.
 *
 * dataBinderValue description.
 *
 * @version 1.0
 * @author jamie
 */
class dataBinderValue {

    public $value;

    public $label;

    function __construct($value, $label = null){

        $this->value = $value;

        $this->label = $label;

    }

    public function __toString(){

        return coalesce($this->label, $this->value);

    }

    public function toArray(){

        return array('__hz_value' => $this->value, '__hz_label' => $this->label);

    }

}
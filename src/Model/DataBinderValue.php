<?php

namespace Hazaar\Model;

/**
 * DataBinderValue short summary.
 *
 * DataBinderValue description.
 *
 * @version 1.0
 * @author jamie
 */
class DataBinderValue implements \JsonSerializable {

    public $name;

    public $value;

    public $label;

    public $other;

    function __construct($value, $label = null, $other = null){

        if(is_array($value)){

            if(array_key_exists('__hz_other', $value))
                $other = $value['__hz_other'];

            if(array_key_exists('__hz_label', $value))
                $label = $value['__hz_label'];

            if(array_key_exists('__hz_value', $value))
                $value = $value['__hz_value'];

        }elseif($value instanceof \stdClass){

            if(property_exists($value, '__hz_other'))
                $other = $value->__hz_other;

            if(property_exists($value, '__hz_label'))
                $label = $value->__hz_label;

            if(property_exists($value, '__hz_value'))
                $value = $value->__hz_value;

        }

        $this->value = $value;

        $this->label = $label;

        $this->other = $other;

    }

    /**
     * Get a new DataBinderValue object if the supplied value is valid and non-null
     *
     * If the value is NULL, then a new DataBinderValue will not be returned.  This method is useful for
     * executing code conditionally if the value is a valid DataBinderValue value.
     *
     * @param mixed $value
     * @param mixed $label
     * @return DataBinderValue|null
     */
    static function create($value, $label = null, $other = null){

        if($value === null)
            return null;

        if(is_array($value)){

            if(array_key_exists('__hz_other', $value))
                $other = $value['__hz_other'];

            if(array_key_exists('__hz_label', $value))
                $label = $value['__hz_label'];

            if(array_key_exists('__hz_value', $value))
                $value = $value['__hz_value'];

        }elseif($value instanceof \stdClass){

            if(property_exists($value, '__hz_other'))
                $other = $value->__hz_other;

            if(property_exists($value, '__hz_label'))
                $label = $value->__hz_label;

            if(property_exists($value, '__hz_value'))
                $value = $value->__hz_value;

        }

        return new DataBinderValue($value, $label, $other);

    }

    public function __toString(){

        if($this->name)
            return (string)new \Hazaar\Html\Span(coalesce($this->label, $this->value), array('data-bind' => $this->name));

        if(!$this->value && $this->other)
            return $this->other;

        return (string)coalesce($this->label, $this->value);

    }

    public function jsonSerialize(){

        return $this->toArray();

    }

    public function toArray(){

        if(!($this->label || $this->other)) return $this->value;

        $array = array('__hz_value' => $this->value, '__hz_label' => $this->label);

        if($this->other) $array['__hz_other'] = $this->other;

        return $array;

    }

    /**
     * Resolve an array and look for saved value/label arrays and convert them.
     *
     * @param mixed $object The object/array to resolve
     * @param boolean $recursive Recurse into normal arrays.  Defaults to TRUE.
     * @return array
     */
    static function resolve($object, $recursive = true){

        $array = array();

        if(is_array($object) || $object instanceof \stdClass){

            foreach($object as $key => $value){

                if(is_array($value) || $value instanceof \stdClass){

                    if((is_array($value) && array_key_exists('__hz_value', $value)) || ($value instanceof \stdClass && property_exists($value, '__hz_value')))
                        $value = (DataBinderValue::create($value))->value;
                    elseif($recursive === true)
                        $value = DataBinderValue::resolve($value);

                }

                $array[$key] = $value;

            }

        }

        if(count($array) === 0)
            return null;

        return $array;

    }

    public function export(){

        return coalesce($this->value, $this->other, $this->label);

    }

}
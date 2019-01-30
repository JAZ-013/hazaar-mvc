<?php

namespace Hazaar\Model;

abstract class DataTypeConverter  {

    /**
     * The list of known variable types that are supported by strict models.
     * @var mixed
     */
    protected static $known_types = array(
        'boolean',
        'integer',
        'int',
        'float',
        'double',  // for historical reasons "double" is returned in case of a float, and not simply "float"
        'string',
        'text',
        'array',
        'list',
        'object',
        'resource',
        'NULL',
        'model',
        'mixed'
    );

    /**
     * Aliases for any special variable types that we support that will be used for system functions.
     * @var mixed
     */
    protected static $type_aliases = array(
        'bool' => 'boolean',
        'number' => 'float',
        'text' => 'string',
        'date' => 'Hazaar\Date'
    );

    /**
     * Convert a variable to the request type.
     *
     * This also allows us to convert complex types, such as arrays, into objects.
     *
     * @param mixed $value The value to convert.
     * @param mixed $type  The type to convert it to.
     *
     * @throws Exception\InvalidDataType
     * @throws \Exception
     * @return mixed
     */
    protected static function convertType(&$value, $type) {

        if($value === null || $type === null) return $value;

        if(array_key_exists($type, DataTypeConverter::$type_aliases))
            $type = DataTypeConverter::$type_aliases[$type];

        if (in_array($type, DataTypeConverter::$known_types)) {

            if((is_array($value) && array_key_exists('__hz_value', $value))
            || ($value instanceof \stdClass && property_exists($value, '__hz_value'))){

                if($type !== 'array'){

                    $value = DataBinderValue::create($value);

                    if($value->value !== null)
                        DataTypeConverter::convertType($value->value, $type);

                }else{

                    $value = null;

                }

            }else{

                /*
                 * The special type 'mixed' specifically allow
                 */
                if ($type === 'mixed' || $type === 'model')
                    return $value;

                if($type === 'text' )
                    $type = 'string';

                if($value instanceof DataBinderValue){

                    if($value->value === null)
                        return $value;

                    $o = $value;

                    $value = $o->value;

                }

                if ($type == 'boolean') {

                    $value = boolify($value);

                }elseif($type == 'list'){

                    if(!$value instanceof ChildArray){

                        if(!is_array($value))
                            @settype($value, 'array');
                        else
                            $value = array_values($value);

                    }

                } elseif ($type == 'string' && is_object($value) && method_exists($value, '__tostring')) {

                    if ($value !== null)
                        $value = (string) $value;

                } elseif ($type !== 'string' && ($value === '' || $value === 'null')){

                    $value = null;

                } elseif (!@settype($value, $type)) {

                    throw new Exception\InvalidDataType($type, get_class($value));

                }

                if(isset($o)){

                    $o->value = $value;

                    $value = $o;

                }

            }

        } elseif (class_exists($type)) {

            if(is_array($value) && array_key_exists('__hz_value', $value))
                $value = $value['__hz_value'];
            elseif($value instanceof \stdClass && property_exists($value, '__hz_value'))
                $value = $value->__hz_value;

            if (!is_a($value, $type)) {

                try {

                    $value = new $type($value);

                }
                catch(\Exception $e) {

                    $value = null;

                }

            }

        }else{

            throw new \Exception("Unable to convert value to unknown type or class '$type'.");

        }

        return $value;

    }

}

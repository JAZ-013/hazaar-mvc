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
        'text' => 'string'
    );

    protected static function convertType(&$value, $type) {

        if(array_key_exists($type, DataTypeConverter::$type_aliases))
            $type = DataTypeConverter::$type_aliases[$type];

        if (in_array($type, DataTypeConverter::$known_types)) {

            if(is_array($value) && array_key_exists('__hz_value', $value) && array_key_exists('__hz_label', $value)){

                if($type !== 'array'){

                    $value = new DataBinderValue(ake($value, '__hz_value'), ake($value, '__hz_label'));

                    DataTypeConverter::convertType($value->value, $type);

                    return $value;

                }

                $value = null;

            }

            /*
             * The special type 'mixed' specifically allow
             */
            if ($type == 'mixed' || $type == 'model')
                return $value;

            if ($type == 'boolean') {

                $value = boolify($value);

            }elseif($type == 'list'){

                if(!is_array($value))
                    @settype($value, 'array');
                else
                    $value = array_values($value);

            } elseif ($type == 'string' && is_object($value) && method_exists($value, '__tostring')) {

                if ($value !== null)
                    $value = (string) $value;

            } elseif ($type !== 'string' && ($value === '' || $value === 'null')){

                $value = null;

            } elseif (!@settype($value, $type)) {

                throw new Exception\InvalidDataType($type, get_class($value));

            }

        } elseif (class_exists($type)) {

            if (!is_a($value, $type)) {

                try {

                    $value = new $type($value);

                }
                catch(\Exception $e) {

                    $value = null;

                }

            }

        }

        return $value;

    }

}
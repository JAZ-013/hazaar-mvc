<?php

namespace Hazaar\Model;

abstract class Strict extends DataTypeConverter implements \ArrayAccess, \Iterator, \Countable, \JsonSerializable {

    /**
     * Stored arguments provided to the constructor
     *
     * @var array
     */
    protected $args;

    /**
     * Undefined values will be ignored. This is checked first.
     * @var bool
     */
    protected $ignore_undefined = true;

    /**
     * Undefined values will be automatically added and their type will be detected.
     * @var bool
     */
    protected $allow_undefined = false;

    /**
     * Disable all callbacks while this is true.
     * @var bool
     */
    protected $disable_callbacks = false;

    /**
     * Automatically convert null values to empty strings if the field is of type string.
     * @var bool
     */
    protected $convert_nulls = false;

    /**
     * Automatically convert empty strings to nulls
     * @var bool
     */
    protected $convert_empty = false;

    /**
     * The field definition.
     * @var mixed
     */
    protected $fields = array();

    /**
     * The current values of all defined fields.
     * @var mixed
     */
    protected $values = array();

    /**
     * Internal loaded flag.  This allows read only fields to be set during startup.
     * @var mixed
     */
    private $loaded = false;

    /**
     * The current value for array access returned by Strict::each().
     * @var mixed
     */
    protected $current;

    /**
     * Scopes can be defined so that some fields are not available if the scope is not set.
     * @var array
     */
    protected $scopes = array();

    /**
     * Strict model constructor
     *
     * The constructor has optional parameters that can vary depending on the implementation.  The first parameter is
     * an array containing the initial data to be loaded into the model.  Any subsequent arguments are passed directly
     * and "as is" to the Hazaar\Model\Strict::construct() method implemented by the extending class.
     *
     * @param $data array Initial data to be loaded into the model.
     *
     * @throws Exception\InitMissing
     *
     * @throws Exception\BadFieldDefinition
     */
    function __construct() {

        $this->args = func_get_args();

        $data = array_shift($this->args);

        if($data instanceof Strict)
            $data = $data->values;

        $field_definition = $this->__init();

        if (!is_array($field_definition))
            throw new Exception\BadFieldDefinition();

        $this->loadDefinition($field_definition);

        if (is_object($data))
            $data = (array) $data;

        $this->prepare($data);

        if (is_array($data) && count($data) > 0)
            $this->populate($data, false);

        $this->loaded = true;

        if (method_exists($this, 'construct'))
            call_user_func_array(array($this, 'construct'), $this->args);

    }

    private function __init(){

        if (!method_exists($this, 'init'))
            throw new Exception\InitMissing(get_class($this));

        $params = array();

        $parent = new \ReflectionClass($this);

        while($parent && $parent->name !== 'Hazaar\Model\Strict'){

            if($parent->hasMethod('init')){

                $init = $parent->getMethod('init');

                $init_params = $init->invoke($this);

                if(is_array($init_params))
                    $params = array_merge($init_params, $params);

            }

            $parent = $parent->getParentClass();

        }

        return $params;

    }

    /**
     * Strict model destructor
     *
     * The destructor calls the shutdown method to allow the extending class to do any cleanup functions.
     */
    final function __destruct() {

        if (method_exists($this, 'shutdown'))
            $this->shutdown();

        if (method_exists($this, 'destruct'))
            $this->destruct();

    }

    public function prepare(&$data){

    }

    /**
     * Populate the model with data contained in the supplied array.
     *
     * @param mixed $data The array of data.
     *
     * @param mixed $exec_filters Execute any callback filters.
     *
     * @return boolean
     */
    public function populate($data, $exec_filters = true) {

        if (!\Hazaar\Map::is_array($data))
            return false;

        foreach($data as $key => $value)
            $this->set($key, $value, $exec_filters);

        return true;

    }

    /**
     * Loads the provided field definition into the model.
     *
     * @param array $field_definition
     */
    public function loadDefinition(array $field_definition) {

        $this->fields = $field_definition;

        $this->values = array();

        if (is_array($this->fields) && count($this->fields) > 0) {

            foreach($this->fields as $field => &$def) {

                if ($field == '*')
                    continue;

                if (!is_array($def))
                    $def = array('type' => $def);

                $value = (array_key_exists('value', $def) ? $def['value'] : ake($def, 'default'));

                if ($type = ake($def, 'type')){

                    /*
                     * If a type is an array or list, then prepare the value as an empty Strict\ChildArray class.
                     */
                    if (($type == 'array' || $type == 'list' ) && array_key_exists('arrayOf', $def)){

                        $value = new ChildArray($def['arrayOf'], $value);

                        //If the type is a model then we use the ChildModel class
                    }elseif($type == 'model') {

                        $value = new ChildModel(ake($def, 'items', 'any'), $value);

                        //Otherwise, just convert the type
                    } elseif($value !== null) {

                        DataTypeConverter::convertType($value, $type);

                    }

                }

                $this->values[$field] = $value;

            }

        }

    }

    /**
     * Return the field definition for the requested field.
     *
     * @param mixed $key The key of the field to return the definition for.
     *
     * @return array
     */
    public function getDefinition($key){

        $def = ake($this->fields, $key);

        if(is_string($def))
            $def = array('type' => $def);

        return $def;

    }

    /**
     * Return true/false indicating if a field has been defined.
     *
     * If the field is not "defined" but instead $allow_undefined has been enabled and the field was added, this will also return true.
     *
     * @param mixed $key The field name to check.
     *
     * @return boolean
     */
    public function has($key) {

        if(strpos($key, '.') !== false){

            $value = $this;

            $parts = explode('.', $key);

            end($parts);

            $lastKey = key($parts);

            foreach($parts as $key => $part){

                if($value instanceof Strict)
                    $value = ($lastKey === $key) ? $value->has($part) : $value->get($part, false);
                elseif(is_array($value))
                    $value = ($lastKey === $key) ? array_key_exists($part, $value) : ake($value, $part);
                else
                    $value = false;

            }

            return $value;

        }

        return array_key_exists($key, $this->fields);

    }

    /**
     * Test if any fields have non-null values
     *
     * @return boolean
     */
    public function hasValues(){

        foreach($this->fields as $key => $def){

            if(($value = ake($this->values, $key)) === null)
                continue;

            if(($value instanceof Strict && $value->hasValues() === false)
                || ($value instanceof ChildArray && $value->count() === 0))
                continue;

            return true;

        }

        return false;

    }

    public function ake($key, $default = null, $non_empty = false) {

        $value = $this->get($key);

        if ($value !== null && (!$non_empty || ($non_empty && trim($value))))
            return $value;

        return $default;

    }

    public function &__get($key) {

        return $this->get($key, !$this->disable_callbacks);

    }

    public function &get($key, $exec_filters = true) {

        if(strpos($key, '.') !== false){

            $value = $this;

            $parts = explode('.', $key);

            end($parts);

            $lastKey = key($parts);

            foreach($parts as $key => $part){

                if($value instanceof Strict){

                    $value = $value->get($part, (($lastKey === $key) ? $exec_filters : false));

                }elseif($value instanceof DataBinderValue){

                    $value = ake($value, $part);

                }elseif(($value = ake($value, $part)) === null){

                    return $value;

                }

            }

            return $value;

        }

        $null = null;

        if (!array_key_exists($key, $this->values))
            return $null;

        $def = ake($this->fields, $key, ake($this->fields, '*'));

        if (!is_array($def))
            $def = array('type' => $def);

        if(array_key_exists('scope', $def) && !in_array($def['scope'], $this->scopes))
            return $null;

        if(array_key_exists('value', $def)){

            $value = $def['value'];

            if($type = ake($def, 'type'))
                DataTypeConverter::convertType($value, $type);

        }else{

            $value = &$this->values[$key];

        }

        /*
         * Run any pre-read callbacks
         */
        if ($exec_filters && is_array($def) && array_key_exists('read', $def))
            $value = $this->execCallback($def['read'], $value, $key);

        return $value;

    }

    public function __set($key, $value) {

        return $this->set($key, $value, !$this->disable_callbacks);

    }

    public function isObject($key){

        $type = $this->getType($key);

        if($type == 'object' || !(in_array($type, DataTypeConverter::$known_types) || array_key_exists($type, DataTypeConverter::$type_aliases)))
            return true;

        return false;

    }

    public function getType($key){

        if($field = ake($this->fields, $key)){

            if(!is_array($field))
                return $field;

            return ake($field, 'type', gettype($this->values[$key]));

        }

        return gettype(ake($this->values, $key, false));

    }

    public function set($key, $value, $exec_filters = true) {

        if(strpos($key, '.') !== false){

            $item = $this;

            $parts = explode('.', $key);

            $key = array_pop($parts);

            foreach($parts as $part){

                if(!($item = $item->get($part, false)) instanceof Strict)
                    return false;

            }

            return $item->set($key, $value, $exec_filters);

        }

        /*
         * Check if the field is defined and decide if we should allow access to it
         *
         * By default, fields have to be defined to work.  However it is possible to
         * allow undefined fields to be automatically defined when they get set for
         * the first time.
         */
        if (!array_key_exists($key, $this->fields)) {

            if ($this->ignore_undefined === true)
                return null;

            elseif ($this->allow_undefined === false)
                throw new Exception\FieldUndefined($key);

            $this->fields[$key] = array('type' => gettype($value));

        }

        /*
         * Keep the field definition handy
         *
         * If it is an array, it is a complex type
         * If it is a string, it is a simple type, so convert to an array automatically with just a type def
         */
        $def = ake($this->fields, $key, ake($this->fields, '*'));
        
        if($this->loaded && array_key_exists('scope', $def) && !in_array($def['scope'], $this->scopes))
            return false;

        if (!is_array($def))
            $def = array('type' => $def);

        /*
         * Static/Ready-Only check.
         *
         * If a value is static then updates to it are not allowed and are silently ignored
         */
        if (array_key_exists('value', $def) || (array_key_exists('readonly', $def) && $def['readonly'] && $this->loaded))
            return false;

        if(array_key_exists('prepare', $def))
            $value = $this->execCallback($def['prepare'], $value, $key);

        /*
         * Run any pre-update callbacks
         */
        if ($exec_filters
            && array_key_exists('update', $def)
            && is_array($def['update'])
            && array_key_exists('pre', $def['update']))
            $value = $this->execCallback($def['update']['pre'], $value, $key);

        /*
         * Type check
         *
         * This checks the type of the field against the type of the value being set and converts it if needed.
         *
         * NOTE: Nulls are not converted as they may have special meaning.
         */
        if ($value !== null && array_key_exists('type', $def))
            DataTypeConverter::convertType($value, $def['type']);

        /*
         * null value check.
         */
        if ($value === null && array_key_exists('nulls', $def) && $def['nulls'] == false && !array_key_exists('value', $def)) {

            if (array_key_exists('default', $def))
                $value = $def['default'];
            else
                return false;

        }

        if (\Hazaar\Map::is_array($value)
            && array_key_exists('arrayOf', $def)
            && !$value instanceof ChildArray) {

            $value = new ChildArray(ake($def, 'arrayOf', 'string'), $value);

        }elseif(array_key_exists('type', $def) && $def['type'] == 'array' && is_array($value)){

            foreach($value as & $subValue){

                if(is_array($subValue) && array_key_exists('__hz_value', $subValue))
                    $subValue = DataBinderValue::create($subValue);

            }

        }

        /*
         * Field validation
         *
         * Possible validation handlers are:
         * * min - minimum integer value
         * * max - maximum integer value
         * * with - string regular expression matching
         */

        if ($exec_filters === true && array_key_exists('validate', $def)) {

            foreach($def['validate'] as $type => $data) {

                switch ($type) {
                    case 'min' :
                        if ($value < $data)
                            return false;

                        break;

                    case 'max' :
                        if ($value > $data)
                            return false;

                        break;

                    case 'with' :
                        if (!preg_match('/' . $data . '/', strval($value)))
                            return false;

                        break;

                    case 'equals' :
                        if ($value !== $data)
                            return false;

                        break;

                    case 'minlen' :
                        if($def['type'] == 'array'){

                            if(count($value) < $data)
                                return false;

                        }else{

                            if (strlen($value) < $data)
                                return false;
                        }

                        break;

                    case 'maxlen' :

                        if($def['type'] == 'array'){

                            if(count($value) > $data)
                                return false;

                        }else{

                            if (strlen($value) > $data)
                                return false;

                        }

                        break;

                }

            }

        }

        /*
         * Apply any sort methods
         */
        if(ake($def, 'type') == 'array' && $sort = ake($def, 'sort')){

            if(is_callable($sort)){

                usort($value, $sort);

            }else{

                switch($sort){

                    case 'asort':
                        asort($value);
                        break;

                    case 'ksort':
                        ksort($value);
                        break;

                    default:
                        sort($value);
                        break;

                }

            }

        }

        $old_value = null;

        if (array_key_exists($key, $this->values) && $this->values[$key] instanceof ChildModel){

            $this->values[$key]->populate($value, $exec_filters);

        } else {

            /*
             * Store the old value to pass to the callback;
             */
            $old_value = ake($this->values, $key);

            /*
             * Now that we have passed our checks, store the new value
             */
            $this->values[$key] = $value;

        }

        /*
         * Run any post-update callbacks
         */
        if ($exec_filters
            && array_key_exists('update', $def)
            && is_array($def['update'])
            && array_key_exists('post', $def['update']))
            $this->execCallback($def['update']['post'], $old_value, $key);

        return $value;

    }

    public function remove($key){

        if(!array_key_exists($key, $this->values))
            return false;

        if(!array_key_exists($key, $this->fields)){

            unset($this->values[$key]);

            return true;

        }

        if(array_key_exists('value', $this->fields[$key]))
            return false;

        if(array_key_exists('default', $this->fields[$key]))
            $this->values[$key] = $this->fields[$key]['default'];
        else
            unset($this->values[$key]);

        return true;

    }

    /**
     * Append an element to an array item
     *
     * @param mixed $key The name of the array field to append to.
     * @param mixed $item The item to append on to the end of the array.
     *
     * @return mixed The item that was just appended.
     */
    public function append($key, $item){

        if(!($def = ake($this->fields, $key)))
            return null;

        $type = ake($def, 'type');

        if(strtolower($type) != 'array' && strtolower($type) != 'list')
            return null;

        if($arrayOf = ake($def, 'arrayOf')){

            if(is_array($arrayOf))
                $item = new ChildModel($arrayOf, $item);
            else
                $this->convertType($item, $arrayOf);

        }

        if(! (array_key_exists($key, $this->values) && $this->values[$key] instanceof ChildArray))
            $this->values[$key] = new ChildArray($type);

        $this->values[$key][] = $item;

        return $item;

    }

    /**
     * Alias for Hazaar\Model\Strict::append()
     *
     * Added to help remove some confusion as to appends purpose.
     *
     * @param string $key The name of the array field to push to.
     * @param mixed $item The item to push on to the end of the array.
     *
     * @return mixed The item that was just pushed.
     */
    public function push($key, $item){

        return self::append($key, $item);

    }

    /**
     * @detail Execute the a callback function on a key/value pair.
     *
     * @param Mixed $cb_def     The callback definition
     *
     * @param mixed $value      The current value of the element.
     *
     * @param string $key       The key name of the element.
     *
     * @return mixed
     */
    private function execCallback($cb_def, $value, $key) {

        if (is_array($cb_def)) {

            $callback = array_slice($cb_def, 0, 2);

            $params = array(
                $value,
                $key
            );

            if (array_key_exists(2, $cb_def) && is_array($cb_def[2]))
                $params = array_merge($params, $cb_def[2]);

            $value = call_user_func_array($callback, $params);

        } elseif (is_callable($cb_def)) {

            $value = call_user_func($cb_def, $value, $key);

        } elseif (method_exists($this, $cb_def)){
            
            $value = call_user_func(array($this, $cb_def), $value, $key);

        }

        return $value;

    }

    public function extend($values, $exec_filters = true, $ignore_keys = null) {

        if (\Hazaar\Map::is_array($values)) {

            foreach($values as $key => $value) {

                if(is_array($ignore_keys) && in_array($key, $ignore_keys))
                    continue;

                if ($this->ignore_undefined && !array_key_exists($key, $this->values))
                    continue;

                $def = ake($this->fields, $key);

                if($exec_filters && ($extend = ake($def, 'extend'))){

                    if(is_callable($extend)){

                        //Do not execute callbacks because we are executing 'extend' instead.
                        $this->set($key, $extend($value, $this->values[$key]), false);

                    }

                }elseif (array_key_exists($key, $this->values) && $this->values[$key] instanceof Strict) {

                    $this->values[$key]->extend($value, $exec_filters, $ignore_keys);

                } else {

                    if(is_array($value)){

                        if($type = ake($def, 'arrayOf')){

                            foreach($value as $subKey => $subValue){

                                if(ake($this->values[$key], $subKey) instanceof Strict)
                                    $this->values[$key][$subKey]->extend($subValue, $exec_filters, $ignore_keys);

                                else{

                                    $this->convertType($subValue, $type);

                                    $this->values[$key][$subKey] = $subValue;

                                }

                            }

                        }else{

                            if(is_array($this->values[$key]))
                                $value = array_merge($this->values[$key], $value);

                            $this->set($key, $value, $exec_filters);

                        }

                    }else{

                        $this->set($key, $value, $exec_filters);

                    }

                }

            }

        }

        return true;

    }

    public function jsonSerialize(){

        return $this->resolveArray($this);

    }

    /**
     * Convert data into an array
     *
     * If field values are Strict models, then convert them to arrays as well.
     *
     * @since 1.0.0
     */
    public function toArray($disable_callbacks = false, $depth = null, $show_hidden = false, $export_data_binder = false) {

        return $this->resolveArray($this, $disable_callbacks, $depth, $show_hidden, $export_data_binder);

    }

    private function resolveArray($array, $disable_callbacks = false, $depth = null, $show_hidden = false, $export_data_binder = true) {

        $result = array();

        $callback_state = $this->disable_callbacks;

        $this->disable_callbacks = $disable_callbacks;

        foreach($array as $key => $value) {

            $def = ake($this->fields, $key, ake($this->fields, '*'));

            if (!is_array($def))
                $def = array('type' => $def);

            /**
             * If the field has a scope and the scope is not set, skip it immediately.
             */
            if(array_key_exists('scope', $def) && !in_array($def['scope'], $this->scopes))
                continue;

            if(array_key_exists('value', $def))
                $value = $def['value'];

            /*
             * Hiding fields
             *
             * If the definition for this field has the 'hide' attribute, we check if the value matches and if so we skip
             * this value.
             */
            if ($show_hidden === false
                && array_key_exists($key, $this->fields)
                && is_array($this->fields[$key])
                && array_key_exists('hide', $this->fields[$key])) {

                $hide = $this->fields[$key]['hide'];

                if ($hide instanceof \Closure)
                    $hide = $hide($value);

                if ($hide === true)
                    continue;
            }

            /*
             * Run any toArray callbacks
             */
            if (!$disable_callbacks && array_key_exists('toArray', $def))
                $value = $this->execCallback($def['toArray'], $value, $key);

            if ($depth === null || $depth > 0) {

                $next = $depth ? $depth - 1 : null;

                if ($value instanceof Strict) {

                    $value = $value->toArray($disable_callbacks, $next, $show_hidden, $export_data_binder);

                } elseif ($value instanceof DataBinderValue) {

                    $value = ($export_data_binder ? $value->toArray() : $value->value);

                } elseif (is_array($value) || $value instanceof ChildArray) {

                    $value = $this->resolveArray($value, $disable_callbacks, $next, $show_hidden, $export_data_binder);

                }

            }

            if(gettype($value) === 'string'){

                if($this->convert_nulls === true && $value === null)
                    $value = '';
                elseif($this->convert_empty === true && trim($value) === '')
                    $value = null;

            }

            $result[$key] = $value;

        }

        $this->disable_callbacks = $callback_state;

        return $result;

    }

    /**
     * Array Access Methods
     */
    public function offsetExists($offset) {

        return array_key_exists($offset, $this->values);

    }

    public function &offsetGet($offset) {

        return $this->get($offset);

    }

    public function offsetSet($offset, $value) {

        return $this->set($offset, $value);

    }

    public function offsetUnset($offset) {

        unset($this->values[$offset]);

    }

    public function each(){

        if(($key = key($this->values)) === null)
            return false;

        $item = array('key' => $key, 'value' => current($this->values));

        next($this->values);

        return $item;

    }

    /**
     * @detail Return the current element in the model
     *
     * @since 1.0.0
     */
    public function current() {

        $key = $this->current['key'];

        $value = $this->current['value'];

        $def = ake($this->fields, $key, ake($this->fields, '*', array()));

        /*
         * Run any pre-read callbacks
         */
        if (!$this->disable_callbacks && is_array($def) && array_key_exists('read', $def))
            return $def['read']($value, $key);

        return $value;

    }

    /**
     * @detail Return the current key from the model
     *
     * @since 1.0.0
     */
    public function key() {

        return $this->current['key'];

    }

    /**
     * @detail Move to the next element in the model
     *
     * @since 1.0.0
     */
    public function next() {

        if ($this->current = $this->each())
            return true;

        return false;

    }

    /**
     * @detail Set the internal pointer the first element
     *
     * @since 1.0.0
     */
    public function rewind() {

        reset($this->values);

        $this->current = $this->each();

    }

    /**
     * @detail Test that an element exists at the current internal pointer position
     *
     * @since 1.0.0
     */
    public function valid() {

        if ($this->current)
            return true;

        return false;

    }

    /**
     * Returns the number of fields stored in the model.
     *
     * @return int
     *
     * @since 1.3.0
     */
    public function count() {

        return count($this->values);

    }

    /**
     * Export the mdel in HazaarModelView format for easy display in views.
     *
     * @return array The array of values stored in the model in key => (label, value) tuples.  Returns null if model is empty.
     *
     * @since 2.0.0
     */
    public function exportHMV($ignore_empty = false, $export_all = false, $obj = null){

        if(!$obj)
            $obj = new \Hazaar\Map($this->toArray(false, 0, $export_all));

        return $this->exportHMVArray($this->toArray(false, 0, $export_all), $this->fields, $ignore_empty, $export_all, $obj);

    }

    /**
     * Exports and array in HazaarModelView format using the supplied definition
     *
     * @param mixed $array The array to convert and export.
     *
     * @param mixed $def   The strict model definition.
     *
     * @return array       The array of values in key => (label, value) tuples.  Returns null if first parameter is not an array.
     *
     * @since 2.0.0
     */
    private function exportHMVArray($array, $def, $hide_empty = false, $export_all = false, $object = null){

        if(!is_array($array))
            return null;

        $values = array();

        foreach($array as $key => $value){

            if(!($key_def = ake($def, $key)) && !$export_all)
                continue;

            //If there is no key definition (because we are export_all=true) then use an empty array so things don't break
            if(!is_array($key_def))
                $key_def = array();

            if(ake($key_def, 'force_hide') === true)
                continue;

            if($when = ake($key_def, 'when')){

                if(is_callable($when)){

                    if(!call_user_func($when))
                        continue;

                }else{

                    if(!$object->findOne($when))
                        continue;

                }

            }

            if(array_key_exists('export', $key_def) && is_callable($key_def['export']))
                $value = $key_def['export']($value, $key);

            $values[$key] = $key_def;

            if($value instanceof Strict){

                if($value->count() == 0 && ($hide_empty || ake($key_def, 'force_hide_empty') == true))
                    continue;

                if(method_exists($value, '__toString')){

                    $values[$key]['value'] = $value->__toString();

                }else{

                    $values[$key]['items'] = $value->exportHMV($hide_empty, $export_all, $object);

                }

            }elseif(is_array($value) || $value instanceof ChildArray){

                if(count($value) == 0 && ($hide_empty || ake($key_def, 'force_hide_empty') == true))
                    continue;

                foreach($value as $subKey => $subValue){

                    if(empty($subValue) && $hide_empty)
                        continue;

                    if ($subValue instanceof Strict){

                        if(method_exists($subValue, 'export')){

                            $values[$key]['items'][] = $subValue->export();

                        }elseif(method_exists($subValue, '__toString')){

                            $values[$key]['list'][] = (string)$subValue;

                        }else{

                            $values[$key]['collection'][] = $subValue->exportHMV($hide_empty, $export_all, $object);

                        }

                    }elseif(is_array($subValue) || $subValue instanceof ChildArray){

                        $subDef = $key_def;

                        $values[$key]['collection'][] = $this->exportHMVArray($subValue, (is_array($subDef)?$subDef:array()), $hide_empty, $export_all, $object);

                    }else{

                        if(!array_key_exists('items', $values[$key]))
                            $values[$key]['items'] = array();

                        $values[$key]['items'][] = array('label' => $subKey, 'value' => $subValue);

                    }

                }

            }else{

                if(empty($value) && ($hide_empty || ake($key_def, 'force_hide_empty') == true))
                    continue;

                $values[$key]['value'] = $value;

            }

        }

        return $values;

    }

    public function find($field, $criteria = array(), $multiple = false){

        if(!(array_key_exists($field, $this->values) && $this->values[$field] instanceof ChildArray))
            return false;

        return $this->values[$field]->find($criteria, $multiple);

    }

    /**
     * Apply a user supplied function to every member of an array
     *
     * Applies the user-defined callback function to each element of the array array.
     *
     * ChildArray::walk() is not affected by the internal array pointer of array. ChildArray::walk() will
     * walk through the entire array regardless of pointer position.
     *
     * For more information on this method see PHP's array_walk() function.
     *
     * @param mixed $callback   Typically, callback takes on two parameters. The array parameter's value being
     *                          the first, and the key/index second.
     * @param mixed $userdata   If the optional userdata parameter is supplied, it will be passed as the third
     *                          parameter to the callback.
     */
    public function array_walk($callback, $userdata = NULL){

        foreach($this->values as $key => $value){

            $callback($value, $key, $userdata);

            if($value != $this->values[$key])
                $this->set($key, $value);

        }

    }

    /**
     * Apply a user supplied function to every member of an array
     *
     * Applies the user-defined callback function to each element of the array array.
     *
     * ChildArray::walk() is not affected by the internal array pointer of array. ChildArray::walk() will
     * walk through the entire array regardless of pointer position.
     *
     * For more information on this method see PHP's array_walk() function.
     *
     * @param mixed $callback   Typically, callback takes on two parameters. The array parameter's value being
     *                          the first, and the key/index second.
     * @param mixed $userdata   If the optional userdata parameter is supplied, it will be passed as the third
     *                          parameter to the callback.
     */
    public function array_walk_recursive($callback, $userdata = NULL){

        foreach($this->values as $key => $value){

            if($value instanceof Strict || $value instanceof ChildArray)
                $value->array_walk_recursive($callback, $userdata);
            else
                $callback($value, $key, $userdata);

            if($value != $this->values[$key])
                $this->set($key, $value);

        }

    }

    /**
     * Magic method for calling array_* functions on the \Hazaar\Model\Strict class.
     *
     * @param mixed $func
     *
     * @param mixed $argv
     *
     * @throws Exception\BadMethodCall
     *
     * @return mixed
     */
    public function __call($func, $argv){

        if (!is_callable($func) || substr($func, 0, 6) !== 'array_')
            throw new \BadMethodCallException(get_class($this).'->'.$func);

        $values = $this->values;

        if($result = call_user_func_array($func, array_merge(array($values), $argv)))
            $this->extend($values);

        return $result;

    }

    public function allowUndefined($toggle = true){

        $this->allow_undefined = $toggle;

        $this->ignore_undefined = !$toggle;

    }

    /**
     * Add one or more scopes to the model
     */
    public function addScope(){

        $args = func_get_args();

        foreach($args as $arg){

            $scopes = is_array($arg) ? $arg : preg_split('/\s+/', $arg);

            foreach($scopes as $scope)
                $this->scopes[] = strtolower(trim($scope));

        }

    }

    /**
     * Remove one or more scopes from the model
     */
    public function removeScope(){

        $args = func_get_args();

        foreach($args as $arg){

            $scopes = preg_split('/s+/', $arg);

            foreach($scopes as $scope){

                if(($index = array_search($scope, $this->scopes)) !== false)
                    unset($this->scopes[$index]);

            }

        }
        
    }

    /**
     * Return the list of currently defined scopes
     */
    public function getScopes(){

        return $this->scopes;

    }

}

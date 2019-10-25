<?php
/**
 * @file        Hazaar/Map.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * @brief       Enhanced array access class
 *
 * @detail      The Map class acts similar to a normal PHP Array but extends it's functionality considerably.  You are
 *              able to reset a Map to default values, easily extend using another Map or Array, have more simplified
 *              access to array key functions, as well as output to various formats such as Array or JSON.
 *
 *              ### Example
 *
 *              ```php
 *              $map = new Hazaar\Map();
 *              $map->depth0->depth1->depth2 = array('foo', 'bar');
 *              echo $map->toJson();
 *              ```
 *
 *              The above example will print the JSON string:
 *
 *              ```php
 *              { "depth0" : { "depth1" : { "depth2" : [ "foo", "bar" ] } } }
 *              ```
 *
 *              ## Filters
 *
 *              Filters are callback functions or class methods that are executed upon a get/set call.  There are two
 *              methods
 *              used for applying filters.
 *
 *              * Map::addInputFilter() - Executes the filter when the element is added to the Map (set).
 *              * Map::addOutputFilter()  - Executes the filter when the element is read from the Map (get).
 *
 *              The method executed is passed two arguments, the value and the key, in that order.  The method must
 *              return the value that it wants to be used or stored.
 *
 *              ### Using Filters
 *
 *              Here is an example of using an input filter to convert a Date object into an array of MongoDate and a
 *              timezone field.
 *
 *              ```php
 *              $callback = function($value, $key){
 *                  if(is_a('\Hazaar\Date', $value)){
 *                      $value = new Map(array(
 *                          'datetime' => new MongoDate($value),
 *                          'timezone' => $value['timezone']
 *                      ));
 *                  }
 *                  return $value;
 *              }
 *              $map->addInputFilter($callback, '\Hazaar\Date', true);
 *              ```
 *
 *              Here is an example of using an output filter to convert an array with two elements back into a Date
 *              object.
 *
 *              ```php
 *              $callback = funcion($value, $key){
 *                  if(Map::is_array($value) && isset('datetime', $value) && isset('timezone', $value)){
 *                      $value = new \Hazaar\Date($value['datetime'], $value['timezone']);
 *                  }
 *                  return $value;
 *              }
 *              $map->addInputFilter($callback, '\Hazaar\Map', true);
 *              ```
 *
 *              The second parameter to the addInputFilter/addOutputFilter methods is a class condition, meaning that
 *              the callback will only be called on objects of that type (uses is_a() internally).
 *
 *              The third parameter says that you want the filter to be applied to all child Map elements as well.
 *              This
 *              is a very powerful feature that will allow type modification of any element at any depth of the Map.
 *
 * @since       1.0.0
 */
class Map implements \ArrayAccess, \Iterator, \Countable {

    /**
     * Holds the original child objects and values
     */
    protected $defaults = array();

    /**
     * Holds the active elements
     */
    protected $elements = array();

    /**
     * The current value for array access returned by Map::each()
     */
    protected $current;

    /**
     * Optional filter definition to modify objects as they are set or get.
     *
     * Filters are an array with the following keys:
     *
     * * callback - The method to execute.  Can be a PHP callback definition or function name.
     * * class - A class name or array of class names that that the callback will be executed on.  Null means all
     * elements.
     * * recurse - Whether this filter should be recursively added to new and existing child elements
     */
    private $filter = array();

    /**
     * Allows the map to be locked so that it's values are not accidentally changed.
     */
    private $locked = FALSE;

    /**
     * @detail      The Map constructor sets up the default state of the Map.  You can pass an array or another Map
     * object
     *              to use as default values.
     *
     *              In the constructor you can also optionally extend the defaults.  This is useful for when you have a
     * default
     *              set of values that may or may not exist in the extended array.
     *
     *              ### Example
     *
     *              ```php
     *                $config = array('enabled' => true);
     *                $map = new Hazaar\Map(array(
     *                  'enabled' => false,
     *                  'label' => 'Test Map'
     *                ), $config);
     *
     *                var_dump($map->toArray());
     *              ```
     *
     *              This will output the following text:
     *
     *              ```
     *                array (size=2)
     *                  'enabled' => boolean true
     *                  'label' => string 'Test Map' (length=8)
     *              ```
     *
     *              !!! notice
     *              If the input arguments are strings then the Map class will try and figure out what kind of string it
     *              is and either convert from JSON or unserialize the string.
     *
     * @since       1.0.0
     *
     * @param       mixed $defaults Default values will set the default state of the Map
     *
     * @param       mixed $extend Extend the default values overwriting existing key values and creating new ones
     *
     * @param       Array $filter_def Optional filter definition
     */
    function __construct($defaults = array(), $extend = array(), $filter_def = array()) {

        /**
         * If we get a string, try and convert it from JSON
         */
        if(is_string($defaults)) {

            if($json = json_decode($defaults, TRUE)) {

                $defaults = $json;

            } else if($unser = unserialize($defaults)) {

                $defaults = $unser;

            } else {

                throw new Exception\UnknownStringArray($defaults);

            }

        }

        if($defaults instanceof Map)
            $filter_def = array_merge($filter_def, $defaults->filter);

        if($extend instanceof Map)
            $filter_def = array_merge($filter_def, $extend->filter);

        $this->filter = $filter_def;

        $this->populate($defaults);

        if($extend)
            $this->extend($extend);

    }

    /**
     * @detail      Test if an object is a usable Array.
     *
     * @since       1.0.0
     *
     * @return      boolean True if the value is an array or extends ArrayAccess
     */
    static public function is_array($array) {

        return (is_array($array)
            || $array instanceof \stdClass
            || $array instanceof \ArrayAccess
            || $array instanceof \ArrayIterator
            || $array instanceof \Iterator);

    }

    /**
     * @detail      Populate sets up the array with initial values.
     *              * This can be used to construct the initial array after it has been instatiated.
     *              * It can also be used to reset an array with different values
     *
     *              Input filters are also applied at this point so that default elements can also be modified.
     *
     *              !!! warning
     *              This method will overwrite ALL values currently in the Map.
     *
     * @since       1.0.0
     *
     * @param       mixed $defaults Map or Array of values to initialise the Map with.
     * @param       boolean $erase If TRUE resets the default values.  If FALSE, then the existing defaults are kept
     *                                but will be overwritten by any new values or created if they do not already exist.
     *                                Use this to add new default values after the object has been created.
     */
    public function populate($defaults, $erase = TRUE) {

        if($this->locked)
            return FALSE;

        if($erase)
            $this->defaults = array();

        if(Map::is_array($defaults)) {

            foreach($defaults as $key => $value) {

                /*
                 * Here we want to specifically look for a REAL array so we can convert it to a Map
                 */
                if(is_array($value) || $value instanceof \stdClass) {

                    $value = new Map($value, NULL, $this->filter);

                } elseif($value instanceof Map) {

                    $value->applyFilters($this->filter);

                }

                $value = $this->execFilter($key, $value, 'in');

                $this->defaults[$key] = $value;

            }

        }

        if($erase)
            $this->elements = $this->defaults;

        return TRUE;

    }

    /**
     * @detail      Commit any changes to be the new default values
     *
     * @since       1.0.0
     *
     * @param       boolean $recursive Recurse through any child Map objects and also commit them.
     *
     * @return      boolean True on success.  False otherwise.
     */
    public function commit($recursive = TRUE) {

        if($this->locked)
            return FALSE;

        if($recursive) {

            foreach($this->elements as $key => $value) {

                if($value instanceof Map) {

                    $value->commit($recursive);

                }

            }

        }

        $this->defaults = $this->elements;

        return TRUE;

    }

    /**
     * @detail      Clear all values from the array.
     *
     *              It is still possible to reset the array back to it's default state after doing this.
     *
     * @since       2.0.0
     */
    public function clear() {

        $this->elements = array();

    }

    /**
     * Check whether the map object is empty or not.
     *
     * @return boolean
     */
    public function isEmpty(){

        return (count($this->elements) == 0);

    }

    /**
     * @detail      Reset the Map back to its default values
     *
     * @since       1.0.0
     */
    public function reset($recursive = FALSE) {

        if($this->locked)
            return FALSE;

        $this->elements = $this->defaults;

        if($recursive == TRUE) {

            foreach($this->elements as $key => $value) {

                if($value instanceof Map) {

                    if(! $value->reset())
                        return FALSE;

                }

            }

        }

        return TRUE;

    }

    /**
     * @detail      The cancel method will flush the default elements so that all elements are considered new or
     *              changed.
     *
     * @since       1.0.0
     */
    public function cancel($recursive = TRUE) {

        if($this->locked)
            return FALSE;

        $this->defaults = array();

        foreach($this->elements as $elem) {

            if($elem instanceof Map) {

                $elem->cancel($recursive);

            }

        }

        return NULL;

    }

    /**
     * @detail      Countable interface method.  This method is called when a call to count() is made on this object.
     *
     * @since       1.0.0
     *
     * @return      int The number of elements in this Map.
     */

    public function count($ignorenulls = FALSE) {

        if($ignorenulls == false)
            return count($this->elements);

        $count = 0;

        foreach($this->elements as $elem) {

            if($elem !== NULL)
                $count++;

        }

        return $count;

    }

    /**
     * @detail      Test if an element exists in the Map object.
     *
     * @since       1.0.0
     *
     * @return      boolean True if the element exists, false otherwise.
     */
    public function has($key) {

        if(array_key_exists($key, $this->elements)) {

            if(! $this->elements[$key] instanceof Map || $this->elements[$key]->count() > 0) {

                return TRUE;

            }

        }

        return FALSE;

    }

    /**
     * @detail     Read will return either the value stored with the specified key, or the default value.  This is
     *              essentially same as doing:
     *
     *              ```php
     *              $value = ($map->has('key')?$map->key:$default);
     *              ```
     *
     *              It has the added benefits however, of being more streamlined and also allowing the value to be
     *              added automatically if it doesn't exist.
     */
    public function & read($key, $default, $insert = FALSE) {

        if(self::has($key))
            return self::get($key);

        if($insert)
            self::set($key, $default);

        return $default;

    }

    /**
     * Get the default value for a value stored in the Map object.
     *
     * This is useful for getting the original value of a value that has changed.  Such as an original index number or
     * other identifier.
     *
     * @param $key
     *
     * @return bool
     */
    public function getDefault($key) {

        if(array_key_exists($key, $this->defaults))
            return $this->defaults[$key];

        return FALSE;

    }

    /**
     * @detail      Test if there are any changes to this Map object.  Changes include not just changes to element
     *              values but any new elements added or any elements being removed.
     *
     * @since       1.0.0
     *
     * @return      boolean True if there are any changes/additions/removal of elements, false otherwise.
     */
    public function hasChanges() {

        $diff1 = array_diff_assoc($this->elements, $this->defaults);

        $diff2 = array_diff(array_keys($this->defaults), array_keys($this->elements));

        if(count($diff1 + $diff2) > 0) {

            return TRUE;

        }

        return FALSE;

    }

    /**
     * @detail      Return an array of element value changes that have been made to this Map
     *
     * @since       1.0.0
     *
     * @return      Map An Map of changed elements
     */
    public function getChanges() {

        $changes = array_diff_assoc($this->elements, $this->defaults);

        foreach($changes as $key => $value) {

            if($value instanceof Map) {

                $changes[$key] = $value->toArray();

            }

        }

        return new Map($changes);

    }

    /**
     * @detail      Test if any values have been removed
     *
     * @since       1.0.0
     *
     * @return      boolean True if one or more values have been removed.  False otherwise.
     */
    public function hasRemoves() {

        if(count(array_diff(array_keys($this->defaults), array_keys($this->elements))) > 0) {

            return TRUE;

        }

        return FALSE;

    }

    /**
     * @detail      Return a list of keys that have been removed
     *
     * @since       1.0.0
     *
     * @return      Map A Map of key names that have been removed from this Map.
     */
    public function getRemoves() {

        $removes = array();

        if($removes = array_flip(array_diff(array_keys($this->defaults), array_keys($this->elements)))) {

            $walk_func = function (&$value) {

                $value = TRUE;

            };

            array_walk($removes, $walk_func);

            return new Map($removes);

        }

        return NULL;

    }

    /**
     * @detail      Test if there are any new elements in the Map
     *
     * @since       1.0.0
     *
     * @return      boolean True if there are new elements, false otherwise.
     */
    public function hasNew() {

        return (count(array_diff(array_keys($this->elements), array_keys($this->defaults))) > 0);

    }

    /**
     * @detail      Return any new elements in the Map
     *
     * @since       1.0.0
     *
     * @return      Map An map of only new elements in the Map
     */
    public function getNew() {

        $new = array_diff(array_keys($this->elements), array_keys($this->defaults));

        $array = array();

        foreach($new as $key) {

            $array[$key] = $this->elements[$key];

        }

        return new Map($array);

    }

    /**
     * @detail      Magic method to test if an element exists
     *
     * @since       1.0.0
     *
     * @return      boolean True if the element exists, false otherwise.
     */
    public function __isset($key) {

        return $this->has($key);

    }

    /**
     * @detail      Extend the Map using elements from another Map or Array.
     *
     * @since       1.0.0
     *
     */
    public function extend() {

        if($this->locked)
            return FALSE;

        foreach(func_get_args() as $elements) {

            if(is_string($elements)) {

                if($json = json_decode($elements, TRUE))
                    $elements = $json;


            }

            if(Map::is_array($elements)) {

                foreach($elements as $key => $elem) {

                    /*
                     * Here we want to specifically look for a REAL array so we can convert it to a Map
                     */
                    if(is_array($elem) || $elem instanceof \stdClass) {

                        $elem = new Map($elem, NULL, $this->filter);

                    } elseif($elem instanceof Map) {

                        $elem->applyFilters($this->filter);

                    }

                    $elem = $this->execFilter($key, $elem, 'in');

                    if($elem instanceof Map
                        && array_key_exists($key, $this->elements)
                        && $this->elements[$key] instanceof Map) {

                        $this->elements[$key]->extend($elem);

                    } else {

                        $this->elements[$key] = $elem;

                    }

                }

            }

        }

        return $this;

    }

    /**
     * Pop an element off of the Map
     *
     * This will by default pop an element off the end of an array.  However this method allows for
     * an element key to be specified which will pop that specific element off the Map.
     *
     * @param       mixed $key Optionally specify the array element to pop off
     *
     * @since       1.0.0
     *
     * @return      mixed The element in the last position of the Map
     */
    public function pop($key = null) {

        if($key === null){

            if($this->locked)
                return FALSE;

            return array_pop($this->elements);

        }

        if(!array_key_exists($key, $this->elements))
            return false;

        $value = $this->elements[$key];

        unset($this->elements[$key]);

        return $value;

    }

    /**
     * @detail      Push an element on to the end of the Map
     *
     * @since       1.0.0
     */
    public function push($value) {

        if($this->locked)
            return FALSE;

        /*
         * Here we want to specifically look for a REAL array so we can convert it to a Map
         */
        if(is_array($value) || $value instanceof \stdClass) {

            $value = new Map($value, NULL, $this->filter);

        } elseif($value instanceof Map) {

            $value->applyFilters($this->filter);

        }

        return array_push($this->elements, $value);

    }

    /**
     * @detail      Shift an element off of the front of the Map
     *
     * @since       1.0.0
     *
     * @return      mixed The element in the first position of the Map
     */
    public function shift() {

        if($this->locked)
            return FALSE;

        return array_shift($this->elements);

    }

    /**
     * @detail      Push an element on to the front of the Map
     *
     * @since       1.0.0
     */
    public function unshift($value) {

        if($this->locked)
            return FALSE;

        /*
         * Here we want to specifically look for a REAL array so we can convert it to a Map
         */
        if(is_array($value) || $value instanceof \stdClass) {

            $value = new Map($value, NULL, $this->filter);

        } elseif($value instanceof Map) {

            $value->applyFilters($this->filter);

        }

        return array_unshift($this->elements, $value);

    }

    /**
     * @detail      Set an output filter callback to modify objects as they are being returned
     *
     * @since       1.0.0
     *
     * @param text $field The field to apply the filter to.  If null, then the filter will be applied
     *                                      to all fields.
     *
     * @param mixed $callback The function to execute on get.
     *
     * @param mixed $filter_type A class name or array of class names to run the callback on.
     *
     * @param boolean $filter_recurse All children will have the same filter applied
     */
    public function addOutputFilter($callback, $filter_field = NULL, $filter_type = NULL, $filter_recurse = FALSE) {

        if(! $callback)
            throw new Exception\BadFilterDeclaration();

        $filter = array('callback' => $callback, 'field' => $filter_field);

        if($filter_type)
            $filter['type'] = $filter_type;

        if($filter_recurse)
            $filter['recurse'] = $filter_recurse;

        if($filter_recurse) {

            foreach($this->elements as $key => $elem) {

                if($elem instanceof Map) {

                    $elem->addOutputFilter($callback, $filter_field, $filter_type, $filter_recurse);

                }

            }

        }

        $this->filter['out'][] = $filter;

    }

    /**
     * @detail      Set an input filter callback to modify objects as they are being set
     *
     * @since       1.0.0
     *
     * @param       mixed $callback The function to execute on set.
     *
     * @param       mixed $filter_type A class name or array of class names to run the callback on.
     *
     * @param       boolean $filter_recurse All children will have the same filter applied
     */
    public function addInputFilter($callback, $filter_field = NULL, $filter_type = NULL, $filter_recurse = FALSE) {

        if(! $callback)
            throw new Exception\BadFilterDeclaration();

        $filter = array('callback' => $callback, 'field' => $filter_field);

        if($filter_type)
            $filter['type'] = $filter_type;

        if($filter_recurse)
            $filter['recurse'] = $filter_recurse;

        if($filter_recurse) {

            foreach($this->elements as $key => $elem) {

                if($elem instanceof Map) {

                    $elem->addInputFilter($callback, $filter_field, $filter_type, $filter_recurse);

                }

            }

        }

        $this->filter['in'][] = $filter;

    }

    /**
     * @detail      Apply a filter array to be used for input/output filters
     *
     * @since       1.0.0
     *
     * @return      boolean True if the filter was valid, false otherwise.
     */
    public function applyFilters($filters_def, $recurse = TRUE) {

        if(Map::is_array($filters_def)) {

            $this->filter = $filters_def;

            if($recurse) {

                foreach($this->elements as $key => $value) {

                    if($value instanceof Map) {

                        $value->applyFilters($filters_def);

                    }

                }

            }

            return TRUE;

        }

        return FALSE;

    }

    /**
     * @detail      Execute a filter on the given element.
     *
     * @since       1.0.0
     *
     * @internal
     *
     * @param       string $key The name of the key to pass to the callback
     *
     * @param       mixed $elem The element that we are executing the callback on
     *
     * @param       string $direction The filter direction identified ('in' or 'out')
     *
     * @return      mixed Returns the element once it has been passed through the callback
     */
    private function execFilter($key, $elem, $direction) {

        if(is_array($this->filter) && array_key_exists($direction, $this->filter) && count($this->filter[$direction]) > 0) {

            foreach($this->filter[$direction] as $filter) {

                if($filter['field'] !== NULL && $key != $filter['field'])
                    continue;

                if(array_key_exists('type', $filter)) {

                    if(! is_array($filter['type'])) {

                        $filter['type'] = array($filter['type']);

                    }

                    foreach($filter['type'] as $type) {

                        if(is_a($elem, $type)) {

                            $elem = call_user_func($filter['callback'], $elem, $key);

                        }

                    }

                } else {

                    $elem = call_user_func($filter['callback'], $elem, $key);

                }

            }

        }

        return $elem;

    }

    /**
     * @detail      Get a reference to a Map value by key.  If an output filters are set they will be executed
     *              before the element is returned here.
     *              Filters are applied/executed only for element types specified in the 'out' filter definition.
     *
     *              !!! warning
     *              Note that when using an output filter the value will NOT be returned by reference meaning
     *              in-place modifications will not work.
     *
     * @since       1.0.0
     *
     * @return      mixed Value at key $key
     */
    public function & get($key, $create = false) {

        if($key === NULL && ! $this->locked) {

            $elem = new Map(NULL, NULL, $this->filter);

            array_push($this->elements, $elem);

        } elseif(! array_key_exists($key, $this->elements) && ! $this->locked) {

            if($create === false){

                $null = null;

                return $null;
            }

            $this->elements[$key] = $elem = new Map(NULL, NULL, $this->filter);

        } else {

            $elem = &$this->elements[$key];

        }

        $elem = $this->execFilter($key, $elem, 'out');

        return $elem;

    }

    /**
     * @detail      Magic method to allow -> access to key values.  Calls Map::get().
     *
     * @since       1.0.0
     *
     * @return      mixed Value at key $key
     */
    public function & __get($key) {

        return $this->get($key, true);

    }

    /**
     * @detail      Set key value.  Filters are applied/executed at this point for element types specified in the 'in'
     * filter definition.
     *
     * @since       1.0.0
     */
    public function set($key, $value, $merge_arrays = false) {

        if($this->locked)
            return FALSE;

        if(is_boolean($value))
            $value = boolify($value);

        /*
         * Here we want to specifically look for a REAL array so we can convert it to a Map
         */
        if(is_array($value) || $value instanceof \stdClass)
            $value = new Map($value, NULL, $this->filter);

        $value = $this->execFilter($key, $value, 'in');

        if($key === NULL) {

            array_push($this->elements, $value);

        } else {

            if(strpos($key, '.')) {

                $cur = &$this->elements;

                foreach(explode('.', $key) as $child)
                    $cur = &$cur[$child];

                $cur = $value;

            } else {

                if($merge_arrays === true
                    && array_key_exists($key, $this->elements)
                    && $this->elements[$key] instanceof Map
                    && $value instanceof Map)
                    $this->elements[$key]->extend($value);
                else
                    $this->elements[$key] = $value;

            }

        }

        return TRUE;

    }

    /**
     * @detail      Magic method to allow -> access to when setting a new kay value
     *
     * @since       1.0.0
     */
    public function __set($key, $value) {

        $this->set($key, $value);

    }

    /**
     * @detail      Remove an element from the Map object
     *
     * @since       1.0.0
     */
    public function del($key) {

        if($this->locked)
            return FALSE;

        unset($this->elements[$key]);

        return TRUE;

    }

    /**
     * @detail      Magic method to remove an element
     *
     * @since       1.0.0
     */
    public function __unset($key) {

        $this->del($key);

    }

    /**
     * @internal
     */
    public function offsetExists($key) {

        return array_key_exists($key, $this->elements);

    }

    /**
     * @private
     */
    public function & offsetGet($key) {

        return $this->get($key);

    }

    /**
     * @private
     */
    public function offsetSet($key, $value) {

        $this->set($key, $value);

    }

    /**
     * @private
     */
    public function offsetUnset($key) {

        unset($this->elements[$key]);

    }

    public function each(){

        if(($key = key($this->elements)) === null)
            return false;

        $item = array('key' => $key, 'value' => current($this->elements));

        next($this->elements);

        return $item;

    }

    /**
     * @detail      Return the current element in the Map
     *
     * @since       1.0.0
     */
    public function current() {

        $key = $this->current['key'];

        $elem = $this->current['value'];

        $elem = $this->execFilter($key, $elem, 'out');

        return $elem;

    }

    /**
     * @detail      Return the current key from the Map
     *
     * @since       1.0.0
     */
    public function key() {

        return $this->current['key'];

    }

    /**
     * @detail      Move to the next element in the Map
     *
     * @since       1.0.0
     */
    public function next() {

        if($this->current = $this->each())
            return TRUE;

        return FALSE;

    }

    /**
     * @detail      Set the internal pointer the first element
     *
     * @since       1.0.0
     */
    public function rewind() {

        reset($this->elements);

        $this->current = $this->each();

    }

    /**
     * @detail      Test that an element exists at the current internal pointer position
     *
     * @since       1.0.0
     */
    public function valid() {

        if($this->current) {

            return TRUE;

        }

        return FALSE;

    }

    /**
     * @detail      Test if a child value is true NULL.  This is the correct way to test for null on a Map object as
     *              it will correctly return true for elements that don't exist.
     *
     * @since       1.0.0
     */
    public function isNull($key) {

        if(! $this->has($key))
            return TRUE;

        return ($this->get($key) == NULL);

    }

    /**
     * @detail      Convert the map to a string.  This is for compatibility with certain other functions that
     *              may attempt to use these objects as a string.  If the map contains any elements it will
     *              return '%Map', otherwise it will return an empty string.
     *
     * @since       1.0.0
     *
     * @return      string A string
     */
    public function toString() {

        return (($this->count() > 0) ? 'Map' : '');

    }

    /**
     * @detail     Magic method to convert the map to a string.  See Map::toString();
     */
    public function __tostring() {

        return $this->toString();

    }

    /**
     * @detail      Return the Map as a standard Array
     *
     * @since       1.0.0
     *
     * @return      Array The Map object as an array
     */
    public function toArray($ignorenulls = FALSE) {

        $array = $this->elements;

        foreach($array as $key => $elem) {

            $elem = $this->execFilter($key, $elem, 'out');

            if($elem instanceof Map || $elem instanceof Model\Strict) {

                if($elem->count() > 0) {

                    $elem = $elem->toArray();

                } else {

                    $elem = array();

                }

            }

            $array[$key] = $elem;

        }

        return $array;

    }

    /**
     * This is get() and toArray() all in one with the added benefit of checking if $key is a \Hazaar\Map and only calling toArray() if it is.
     *
     * @param mixed $key The key values to get as an array.
     *
     * @param mixed $ignorenulls
     */
    public function getArray($key, $ignorenulls = FALSE){

        $value = $this->get($key);

        if($value instanceof Map)
            $value = $value->toArray($ignorenulls);

        return $value;

    }

    /**
     * @detail      Return a valid JSON string representation of the Map
     *
     * @since       1.0.0
     *
     * @return      string The Map as a JSON string
     */
    public function toJSON($ignorenulls = FALSE, $args = NULL) {

        if($array = $this->toArray($ignorenulls)) {

            return json_encode($array, $args);

        }

        return NULL;

    }

    /**
     * @detail      Find elements based on search criteria
     *
     * @since       1.0.0
     *
     * @return      Map A Map of elements that satisfied the search criteria.
     */
    public function find($criteria) {

        if(! Map::is_array($criteria))
            throw new Exception\InvalidSearchCriteria();

        $elements = array();

        foreach($this->elements as $id => $elem) {

            if(! is_array($elem) && ! $elem instanceof \ArrayAccess)
                continue;

            foreach($criteria as $key => $value) {

                //Look for dot notation.
                if(strpos($key, '.') > 0){

                    $dn = $elem->toDotNotation();

                    if(!($dn->has($key) && $dn->get($key) == $value))
                        continue 2;

                }else{

                    if(! isset($elem[$key]))
                        continue 2;

                    if($elem[$key] != $value)
                        continue 2;

                }

            }

            $elements[$id] = $elem;

        }

        return new Map($elements);

    }

    /**
     * @detail      Find a sub element based on search criteria
     *
     * @since       1.0.0
     *
     * @param       Map $criteria Search criteria in the format of key => value.
     *
     * @param       String Return a single field.  If the field does not exist returns null.  This allows
     *              us to safely return a single field in a single line in cases where nothing is found.
     *
     * @return      mixed The first element that matches the criteria
     */
    public function & findOne($criteria, $field = null) {

        if($criteria instanceof \MongoDB\BSON\ObjectID)
            $criteria = array('_id' => $criteria);

        if(! Map::is_array($criteria))
            throw new Exception\InvalidSearchCriteria();

        foreach($this->elements as $id => $elem) {

            if(! is_array($elem) && ! $elem instanceof \ArrayAccess)
                continue;

            foreach($criteria as $key => $value) {

                //Look for dot notation.
                if(strpos($key, '.') > 0){

                    $dn = $elem->toDotNotation();

                    if(!($dn->has($key) && $dn->get($key) == $value))
                        continue 2;

                }else{

                    if(! isset($elem[$key]))
                        continue 2;

                    if($elem[$key] != $value)
                        continue 2;

                }

            }

            if($field)
                return $elem->get($field);

            return $elem;

        }

        $null = null;

        return $null;

    }

    /**
     * @detail      Searches a numeric keyed array for a value that is contained within it and returns true if it
     *              exists.
     *
     * @since       2.0.0
     *
     * @param       mixed $value The value to search for
     *
     * @return      boolean
     */
    public function in($value) {

        return in_array($value, $this->elements);

    }

    public function search($value) {

        return array_search($value, $this->elements);

    }

    public function fill($start_index, $num, $value) {

        $this->elements = array_fill($start_index, $num, $value);

    }

    /**
     * @detail      Modify multiple elements in one go.  Unlike extends this will only modify a value that already
     *              exists in the Map.
     *
     * @since       1.0.0
     *
     * @param       Map $values Map of values to update.
     */
    public function modify($values) {

        if($this->locked)
            return FALSE;

        if($values instanceof Map)
            $values = $values->toArray();

        foreach($values as $key => $value) {

            if(self::has($key)) {

                self::set($key, $value);

            }

        }

        return TRUE;

    }

    /**
     * @detail      Enhance is the compliment of modify. It will only update values if they DON'T already exist.
     *
     * @since       1.2
     *
     * @param       mixed $values Array or Map of values to add.
     *
     * @return      boolean
     */
    public function enhance($values) {

        if($this->locked)
            return FALSE;

        if($values instanceof Map)
            $values = $values->toArray();

        foreach($values as $key => $value) {

            if(! self::has($key)) {

                self::set($key, $value);

            }

        }

        return TRUE;

    }

    /**
     * @detail      Remove an element from the Map based on search criteria
     *
     * @since       1.0.0
     *
     * @param       Array $criteria An array of ssearch criteria that must be met for the element to be removed.
     *
     * @return      boolean True if something is removed, false otherwise.
     */
    public function remove($criteria) {

        if($this->locked)
            return FALSE;

        if(! Map::is_array($criteria))
            return FALSE;

        foreach($this->elements as $key => $elem) {

            if(! Map::is_array($elem))
                continue;

            foreach($criteria as $search_key => $search_value) {

                if(! isset($elem[$search_key]))
                    continue 2;

                if((string)$elem[$search_key] != (string)$search_value)
                    continue 2;

            }

            $this->del($key);

            return TRUE;

        }

        return FALSE;

    }

    /**
     * @detail      Return a total of all numeric values in the Map.
     *
     * @since       1.0.0
     *
     * @param       Array $criteria Search criteria that must be met for the value to be included.
     *
     * @param       Mixed $fields The fields to use for the sum.  If omitted all numeric fields will be summed.
     *                                 If a string is specified then a single field will be used.  Also, an Array can
     *                                 be used to allow multiple fields.
     *
     * @param       boolean $recursive Set true if you need to recurse into child elements and add them to the sum.
     *
     * @return      float Sum of all numeric values
     */
    public function sum($criteria = NULL, $fields = array(), $recursive = FALSE) {

        $children = array();

        $sum = 0;

        foreach($this->elements as $key => $elem) {

            if($elem instanceof Map) {

                if($recursive) {

                    $sum += $elem->sum($criteria, $fields, $recursive);

                } else {

                    continue;

                }

            } else {

                if($criteria !== NULL) {

                    foreach($criteria as $search_key => $search_value) {

                        if(! isset($elem[$search_key]))
                            continue 2;

                        if($elem[$search_key] != $search_value)
                            continue 2;

                    }
                }

                if(! is_array($fields) || count($fields) == 0 || in_array($key, $fields))
                    $sum += (float)$elem;

            }

        }

        return $sum;

    }

    /**
     * @detail     Returns an array of key names currently in this Map object
     */
    public function keys() {

        return new Map(array_keys($this->elements));

    }

    /**
     * @detail     Lock the map so that it's values can not be accidentally changed.
     */
    public function lock() {

        $this->locked = TRUE;

    }

    /**
     * @detail     Unlock the map so that it's values can be changed.
     */
    public function unlock() {

        $this->locked = FALSE;

    }

    public function implode($glue = ' ') {

        return implode($this->elements, $glue);

    }

    public function flatten($inner_glue = '=', $outer_glue = ' ', $ignore = array()) {

        $elements = array();

        foreach($this->elements as $key => $value) {

            if(in_array($key, $ignore))
                continue;

            if(gettype($value) == 'boolean')
                $value = strbool($value);

            $elements[] = $key . $inner_glue . $value;

        }

        return implode($outer_glue, $elements);

    }

    /**
     * @detail      Export all objects/arrays/Maps as an array
     *
     *              If an element is an object it will be checked for an __export() method which if it exists the
     *              resulting array from that method will be used as the array representation of the element.  If
     *              the method does not exist then the resulting array will be an array of the *public* object member
     *              variables only.
     *
     * @since       2.0.0
     *
     * @param               mixed @element The root element to convert into an array.
     *
     * @param       boolean $export_as_json Instead of returning an array, return a JSON string of the array.
     */
    static public function exportAll($element, $export_as_json = FALSE) {

        $result = NULL;

        if($element instanceof Map) {

            $result = $element->toArray();

        } elseif(is_object($element)) {

            if(method_exists($element, '__export')) {

                $result = Map::exportAll($element->__export());

            } else {

                $members = get_object_vars($element);

                if(count($members) > 0) {

                    $result = array();

                    foreach($members as $key => $value) {

                        $result[$key] = Map::exportAll($value);

                    }

                }

            }

        } elseif(is_array($element)) {

            $result = $element;

            foreach($element as $key => $child) {

                $result[$key] = Map::exportAll($child);

            }

        } else {

            $result = $element;

        }

        if($export_as_json)
            return json_encode($result);

        return $result;

    }

    /**
     * @brief       Convert to dot notation
     *
     * @detail      Converts/reduces a multidimensional array into a single dimensional array with keys in dot-notation.
     *
     * @since       2.0.0
     *
     * @return      array
     */
    public function toDotNotation() {

        $rows = array();

        foreach($this->elements as $key => $value) {

            if($value instanceof Map) {

                $children = $value->todotnotation();

                foreach($children as $childkey => $child) {

                    $new_key = $key . '.' . $childkey;

                    $rows[$new_key] = $child;

                }

            } else {

                $rows[$key] = $value;

            }

        }

        return new Map($rows);

    }

    /**
     * Populate or extend the object values from a JSON string
     *
     * @param mixed $json
     * @param mixed $merge
     */
    public function fromJSON($json, $merge = FALSE){

        $bom = pack('H*','EFBBBF');

        $json = preg_replace("/^$bom/", '', $json);

        if(($new = json_decode($json, true)) === null)
            return false;

        if($merge)
            $this->extend($new);
        else
            $this->populate($new);

        return true;

    }

    /**
     * @brief       Convert to Map from dot notation
     *
     * @detail      Converts/reduces a single dimensional array with keys in dot-notation and expands it into a
     *              multi-dimensional array.
     *
     * @since       2.0.0
     *
     * @return      array
     */
    public function fromDotNotation($array, $merge = FALSE) {

        if(!is_array($array))
            return false;

        $new = array();

        foreach($array as $idx => $value) {

            if(is_array($value)) {

                $new[$idx] = new \Hazaar\Map();

                $new[$idx]->fromDotNotation($value, $merge);

            } else {

                $parts = explode('.', $idx);

                if(count($parts) > 1) {

                    $cur =& $new;

                    foreach($parts as $part) {

                        if(! array_key_exists($part, $cur))
                            $cur[$part] = array();

                        if(is_array($cur))
                            $cur =& $cur[$part];

                    }

                    $cur = $value;

                } else {

                    $new[$idx] = $value;

                }

            }

        }

        if($merge)
            $this->extend($new);
        else
            $this->populate($new);

        return true;

    }

    /**
     * Updates the Map with values in the supplied array if they exist
     *
     * This method will update existing values in the current Map object with the values in the supplied $value array
     * or Map.  If the values do not already exist in the current Map object, no new values will be created.
     *
     * @param $values The values to update
     *
     * @return boolean WIll return False if the supplied parameter is not an array.
     */
    public function update($values) {

        if(! Map::is_array($values))
            return FALSE;

        foreach($values as $key => $value) {

            if($this->has($key)) {

                if(Map::is_array($this->get($key))) {

                    $this->get($key)
                         ->update($value);

                } else {

                    $this->set($key, $value);

                }

            }

        }

        return TRUE;

    }

    public function __sleep(){

        return array('defaults', 'elements', 'current', 'locked');

    }

}
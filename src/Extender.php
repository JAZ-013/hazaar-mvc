<?php

namespace Hazaar;

/**
 * @brief       The Class Extender Class
 *
 * @detail      The Extender Class allows (or simulates) multiple class inheritance in PHP.  It does this
 *              by loading instances of the extended class under the covers and transparently passing non-existent
 *              method calls on to 'child' classes (if the method exist).  It will also work with member variables
 *              and honors the private, protected and public variable definitions.
 *
 * @since       1.2
 */
abstract class Extender {

    private $children = array();

    private $methods = array();

    private $properties = array();

    /**
     * @detail      Extend a class with a child class.
     *
     *              The arguments are as follows:
     *              # The name of the class to inherit from
     *              # ...  A list of arguments to pass on to the class constructor
     *
     * @since       1.2
     *
     * @return      bool Returns true if the class was successfully extended.
     *
     * @throws      \Exception
     */
    protected function extend() {

        $args = func_get_args();

        if(! isset($args[0]))
            return false;

        $class = array_shift($args);

        if($class[0] !== '\\'){

            $t = new \ReflectionClass($this);

            $class = $t->getNamespaceName() . '\\' . $class;

        }

        $r = new \ReflectionClass($class);

        if($r->isFinal())
            throw new Exception\ExtenderMayNotInherit('final', get_class($this), $class);

        if($r->isAbstract()){

            $wrapper_class = 'wrapper_' . str_replace('\\', '_', $class);

            eval("class $wrapper_class extends $class {}");

            $r = new \ReflectionClass($wrapper_class);

        }

        $obj = $r->newInstanceArgs($args);

        $this->children[$class] = $obj;

        foreach($r->getMethods() as $method) {

            if(! array_key_exists($method->name, $this->methods)) {

                if(! $method->isPrivate())
                    $method->setAccessible(true);

                $this->methods[$method->name] = array(
                    $class,
                    $method
                );

            }

        }

        foreach($r->getProperties() as $property) {

            if(! array_key_exists($property->name, $this->properties)) {

                if(! $property->isPrivate())
                    $property->setAccessible(true);

                $this->properties[$property->name] = array(
                    $class,
                    $property
                );

            }

        }

        return true;

    }

    /**
     * @detail      This method call router will route the method call to the first child class that was
     *              found to have the requested method.  If the method does not exist an \Exception is thrown.
     *
     * @since       1.2
     *
     * @param       string $method Then name of the method being called
     *
     * @param       Array  $args   An array of arguments from the method call
     *
     * @return      mixed The returned data from the child method
     *
     * @throws      \Exception
     */
    public function __call($method, $args = array()) {

        if(array_key_exists($method, $this->methods)) {

            list($class, $rm) = $this->methods[$method];

            if(! ($call = $rm->isPublic())) {

                $trace = debug_backtrace();

                if($rm->isPrivate()) {

                    throw new Exception\ExtenderInvokeFailed('private', $class, $method, get_class($this));

                } elseif(array_key_exists('class', $trace[2]) && $trace[2]['class'] == get_class($this)) {

                    $call = true;

                } elseif($rm->isProtected()) {

                    throw new Exception\ExtenderInvokeFailed('protected', $class, $method, get_class($this));

                }

            }

            if($call) {

                return $rm->invokeArgs($this->children[$class], $args);

            }

        }

        throw new Exception\MethodUndefined(get_class($this), $method);

    }

    /**
     * @detail      Pass through method for converting the element to a string.  If one of the child classes
     *              had a __tostring() method then this method is called on the first child that has it.
     *
     * @since       1.2
     *
     * @return      string
     */
    public function __tostring() {

        if(array_key_exists('__tostring', $this->methods)) {

            return self::__call('__tostring');

        }

        return '';

    }

    /**
     * @detail      Get a property from the instantiated class object for properties that do not exist.  This will
     *              return the value of a property if it exists in a child class.  This method honors the private,
     *              protected and public variable declarations.
     *
     * @since       1.2
     *
     * @param       string $property The property being requested
     *
     * @return      mixed The value of the property
     */
    public function __get($property) {

        if(array_key_exists($property, $this->properties)) {

            list($class, $rp) = $this->properties[$property];

            if(! ($return = $rp->isPublic())) {

                $trace = debug_backtrace();

                if($rp->isPrivate()) {

                    throw new Exception\ExtenderAccessFailed('private', get_class($this), $property);

                } elseif(array_key_exists('class', $trace[1]) && $trace[1]['class'] == get_class($this)) {

                    $return = true;

                } elseif($rp->isProtected()) {

                    throw new Exception\ExtenderAccessFailed('protected', get_class($this), $property);

                }

            }

            if($return) {

                return $rp->getValue($this->children[$class]);

            }

        }

        throw new Exception\PropertyUndefined(get_class($this), $property);

    }

    /**
     * @detail      Set a property on the instantiated class object for properties that do not exist.  This will
     *              set the value of a property if it exists in a child class.  This method honors the private,
     *              protected and public variable declarations.  If the child class variable is not meant to be
     *              accessible, either by not existing, or being set public, or being protected and the call
     *              originated outside the object, then the variable is created on the fly in the main object.
     *              This is the same as a normal object.
     *
     * @since       1.2
     *
     * @param       string $property The property being set
     *
     * @param       mixed  $value    The value to set
     */
    public function __set($property, $value) {

        if(array_key_exists($property, $this->properties)) {

            list($class, $rp) = $this->properties[$property];

            if(! ($set = $rp->isPublic())) {

                $trace = debug_backtrace();

                if($rp->isPrivate()) {

                    throw new Exception\ExtenderAccessFailed('private', get_class($this), $property);

                } elseif(array_key_exists('class', $trace[1]) && $trace[1]['class'] == get_class($this)) {

                    $return = true;

                } elseif($rp->isProtected()) {

                    throw new Exception\ExtenderAccessFailed('protected', get_class($this), $property);

                }

            }

            if($set) {

                return $rp->setValue($this->children[$class], $value);

            }

        }

        return $this->$property = $value;

    }

    /**
     * Test if the class extends another class.
     *
     * This implements the "instanceof" functionality of PHP and searches all the child classes to see if any of them extend
     * the specified class.
     *
     * @param string $class The name of the class to test
     *
     * @return boolean True if any of the child classes extend the specified class.
     *
     * @since 2.5.1
     */
    public function instanceof($class){

        foreach($this->children as $child){

            if($child instanceof $class
                || ($child instanceof Extender && $child->instanceof($class)))
                return true;

        }

        return false;

    }

}


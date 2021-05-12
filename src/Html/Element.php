<?php

namespace Hazaar\Html;

/**
 * @brief       Abstract base element class
 *
 * @detail      This class is the base element class from which all HTML elements are built upon.  It contains common
 *              methods and properties for all elements.  It also enforces the use of __tostring() to output string
 *              renders of the object to form HTML content.
 *
 * @since       1.0.0
 */
abstract class Element implements _Interface {

    protected $type;

    protected $parameters;

    protected $style = array();

    /**
     * @detail      The HTML element constructor takes two arguments.  The type of the element which is defined by
     *              the available types in the HTML standards.  eg: A, DIV, SPAN, etc.  The second argument is an
     *              array of parameters to apply to the element such as width, height, style, etc.  The parameters
     *              are also defined by the current HTML standards.
     *
     *              The available types and parameters are not restricted by this class and so ANY type, valid or
     *              not, can be specified.  It is up to the developer to ensure that the HTML element types they
     *              use adhere to the standards for which they are trying to comply.
     *
     * @since       1.0.0
     *
     * @param       string $type The HTML element type.
     *
     * @param       array $parameters An array of HTML parameters to apply to the element.
     */
    function __construct($type = null, $parameters = array()) {

        $this->type = $type;

        if($parameters instanceof Parameters) 
            $this->parameters = $parameters;
        else
            $this->parameters = new Parameters($parameters);

        $this->parameters->setMultiValue('class', ' '); //Set class as a multi-value parameter with space delimeter

    }

    public function getTypeName(){

        return strtoupper($this->type);

    }

    /**
     * @detail      Magic method to allow getting of parameters by property access.
     *
     * @since       1.0.0
     *
     * @param       string $key The name of the parameter to return.
     *
     * @return      mixed The value of the requested parameter.  The data type of the value will be the same as that
     *              when it was originally set.
     */
    public function __get($key) {

        return $this->attr($key);

    }

    /**
     * @detail      Get/Set an attribute on the current HTML element
     *
     * @since       1.0.0
     *
     * @param       string $key The name of the parameter to set.
     *
     * @param              mixed @value The value of the parameter.
     *
     * @return      \\Hazaar\\Html\Element Returns a ref to self.
     *
     */
    public function attr() {

        if(func_num_args() == 0)
            return $this->parameters;

        if(func_num_args() == 1)
            return $this->parameters->get(func_get_arg(0));

        list($key, $value) = func_get_args();

        if(! $this->parameters instanceof Parameters)
            $this->parameters = new Parameters();

        if($value === null)
            $this->parameters->remove($key);
        else
            $this->parameters->set($key, $value);

        return $this;

    }

    /**
     * Enable or disable a property.
     *
     * Some element types have properties that have no value.  A good example is "checked" on a checkbox or radio, or "enabled" on an input.  This method
     * allows these properties to be added or removed without requiring a value.
     *
     * @param string $key The property to set on the element.
     *
     * @param boolean $enabled If TRUE the property will be added.  If FALSE the property will not be added or it will be removed if it already exists.
     *
     * @return Element
     */
    public function prop($key, $enabled = true){

        if($enabled)
            $this->parameters->set($key);
        else
            $this->parameters->remove($key);

        return $this;

    }

    /**
     * Get the parameters object
     * 
     * @return Hazaar\Html\Parameters 
     */
    public function parameters() {

        return $this->parameters;

    }

    /**
     * Adds a class to the HTML element
     *
     * @param string $class The name of the class to add
     *
     * @return $this
     *
     */
    public function addClass($class) {

        if($this->parameters->has('class'))
            $class = ' ' . $class;

        $this->parameters->append('class', $class);

        return $this;

    }

    /**
     * Removes a class from the HTML element
     *
     * @param string $class The name of the class to remove
     * 
     * @return $this
     */
    public function removeClass($class){

        $this->parameters->remove('class', $class);

        return $this;

    }

    /**
     * Test if a class has been added to an HTML element
     *
     * @param string $class The class to check for
     *
     * @return bool TRUE if the class has been set
     */
    public function hasClass($class) {

        if(! $this->parameters->has('class'))
            return FALSE;

        $classes = explode(' ', $this->parameters->get('class'));

        return in_array($class, $classes);

    }

    /**
     * Set's a class based on a boolean value.
     *
     * This is handy for adding a class only if a boolean value is true and can be used as shorthand in place of
     * in 'if' statement that selectively adds the class as this may not be desired in a view.
     *
     * @param $class
     *
     * @param $boolean
     *
     * @return $this;
     */
    public function toggleClass($class, $boolean = FALSE) {

        if($boolean)
            $this->addClass($class);

        return $this;

    }

    /**
     * @detail      Set a parameter on the current HTML element
     *
     * @since       1.0.0
     *
     * @param       string $key The name of the parameter to set.
     *
     * @param              mixed @value The value of the parameter.
     *
     * @return      \\Hazaar\\Html\Element Returns a ref to self.
     *
     */
    public function __set($key, $value) {

        return $this->attr($key, $value);

    }

    /**
     * @detail      Magic method to convert the element to a string.
     *
     *              Calls the methods renderObject() method to render the object as a string.
     *
     * @since       1.0.0
     *
     * @return      string
     */
    public function __tostring() {

        return $this->renderObject();

    }

    /**
     * @detail      Render the element as HTML using ascii special characters.  This allows elements to easily
     *              be displayed without being rendered in the browser.  Great for use inside <pre> elements.
     *
     * @since       1.0.0
     *
     * @return      string
     */
    public function asHtml() {

        return htmlspecialchars($this->renderObject());

    }

    /**
     * @detail      Chaining method to set a parameter on the element.  As event parameters all start with 'on' this
     *              will check for event parameters and ensure that they are quoted correctly so that execution will
     *              not fail.
     *
     * @since       1.2
     *
     * @return      string
     */
    public function __call($method, $args) {

        $value = NULL;

        if(substr(strtolower(trim($method)), 0, 2) == 'on') {

            $value = preg_replace('/"/', "'", $args[0]);

        } elseif(count($args) > 0) {

            $value = $args[0];

        } else {

            $this->parameters->set($method);

            return $this;

        }

        return $this->attr($method, $value);

    }

    public function style() {

        if(func_num_args() == 0)
            return $this->style;

        if(! $this->style instanceof Style)
            $this->style = new Style();

        $this->parameters->set('style', $this->style);

        call_user_func_array(array($this->style, 'set'), func_get_args());

        return $this;

    }

    /*
     * Set an HTML5 data atrribute.
     *
     * @param $key string The name of the data attribute to set.
     *
     * @param $value string The value of the data attributes.
     *
     * @since 2.0
     */
    public function data($key, $value){

        return $this->attr('data-' . trim($key), $value);

    }

    /**
     * Shorthand method to set an element visible
     */
    public function show(){

        $this->style('display', 'block');

    }

    /**
     * Shorthand method to set an element not visible
     */
    public function hide(){

        $this->style('display', 'none');

    }

    /**
     * Append the element to another.
     *
     * This is the same as calling add() on the other element but is more convenient in some cases.
     *
     * @param mixed $element The element to append to.
     *
     * @return Element This element
     */
    public function appendTo(&$element){

        if($element instanceof Element)
            $element->add($this);
        elseif(is_array($element))
            $element[] = $this;

        return $this;

    }

}

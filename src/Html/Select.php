<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML select class.
 *
 * @detail      Displays an HTML &lt;select&gt; element.
 *
 * @since       1.1
 */
class Select extends Block {

    private $value;

    private $use_options_index_as_value = true;

    private $nullable = false;

    private $placeholder = null;

    /**
     * @detail      The HTML select constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements
     *                                or arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     *
     * @param       boolean $use_options_index_as_value Normally not used, but this will disable the use of the options index and cause the
     *                                                  resulting SELECT OPTIONS to have no VALUE attribute.
     */
    function __construct($name = NULL, $options = NULL, $value = NULL, $params = array(), $use_options_index_as_value = true) {

        $params['name'] = $name;

        $this->use_options_index_as_value = $use_options_index_as_value;

        parent::__construct('select', NULL, $params);

        $this->value = $value;

        if($options)
            $this->add($options);

    }

    public function add() {

        foreach(func_get_args() as $arg) {

            if(is_array($arg)) {

                foreach($arg as $value => $label){

                    if(is_array($label)){

                        $this->addOptgroup($value, $label);

                    }else{

                        if($this->use_options_index_as_value)
                            $this->addOption($label, $value);
                        else
                            $this->addOption($label);

                    }

                }

                return $this;

            } else if(! $arg instanceof Option && ! $arg instanceof Optgroup) {

                throw new \Exception('Only elements of type Option or Optgroup are allowed to be added to a Select object.');

            } else {

                parent::add($arg);

            }

        }

        return $this;

    }

    public function addOptgroup($label, $options = array()) {

        return self::addOption(array('label' => $label, 'items' => $options));

    }

    public function addOption($label, $value = NULL, $params = array()) {

        if(is_array($label) && array_key_exists('items', $label)) {

            $item = new Optgroup($label['label'], $label['items']);

        } elseif($label instanceof Optgroup || $label instanceof Option) {

            $item = $label;

        } else {

            $item = new Option($label, $value, $params);

        }

        return $this->add($item);

    }

    public function renderElement($child) {

        if($child instanceof Option) {

            if(is_array($this->value)){

                if($this->use_options_index_as_value){

                    if(in_array($child->value, $this->value))
                        $child->selected = true;

                }else{

                    if(in_array($child->get()[0], $this->value))
                        $child->selected = true;

                }

            }elseif($this->use_options_index_as_value){

                if($child->value == $this->value)
                    $child->selected = true;

            }else{

                if($child->get()[0] == $this->value)
                    $child->selected = true;

            }

        } elseif($child instanceof Optgroup) {

            foreach($child->get() as $option) {

                if(is_array($this->value)){

                    if(in_array($child->value, $this->value))
                        $child->selected = true;

                }else{

                    if($option->value == $this->value)
                        $option->selected = TRUE;

                }

            }

        }

        return parent::renderElement($child);

    }

    public function nullable($boolean = true, $placeholder = null){

        $this->nullable = $boolean;

        if($placeholder !== null)
            $this->placeholder = $placeholder;

        return $this;

    }

    public function renderObject() {

        if($this->nullable == true)
            $this->prepend(new Option($this->placeholder, 'null'));

        return parent::renderObject();

    }

}

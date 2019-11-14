<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML optgroup class.
 *
 * @detail      Displays an HTML &lt;optgroup&gt; element.
 *
 * @since       1.1
 */
class Optgroup extends Block {

    private $value;

    private $use_options_index_as_value = true;

    /**
     * @detail      The HTML optgroup constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($label, $options = NULL, $value = NULL, $params = array(), $use_options_index_as_value = true) {

        $params['label'] = $label;

        $this->use_options_index_as_value = $use_options_index_as_value;

        parent::__construct('optgroup', NULL, $params);

        $this->value = $value;

        if($options)
            $this->add($options);

    }

    public function add() {

        foreach(func_get_args() as $arg) {

            if(is_array($arg)) {

                foreach($arg as $value => $label)
                    $this->addOption($label, $value);

            } else if(! $arg instanceof Option && ! $arg instanceof Optgroup) {

                throw new \Hazaar\Exception('Only elements of type Option or Optgroup are allowed to be added to a Select object.');

            } else {

                parent::add($arg);

            }

        }

        return $this;

    }

    public function addOption($label, $value = NULL, $params = array()) {

        if(is_array($label) && array_key_exists('items', $label)) {

            $item = new Optgroup($label['label'], $label['items']);

        } else {

            if($value == $this->value)
                $params['selected'] = TRUE;

            $item = new Option($label, $value, $params);
        }

        return $this->add($item);

    }

}


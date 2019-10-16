<?php

namespace Hazaar\Html;

/**
 * @brief       Inline HTML display element
 *
 * @detail      Generic base class for an HTML inline element.  This class will render any inline element of the defined
 *              type.
 *
 * @since       1.0.0
 */
class Inline extends Element {

    /**
     * @detail      Renders the current object as an inline HTML element.
     *
     * @since       1.0.0
     *
     * @return      string
     */
    public function renderObject() {

        if(!$this->type)
            throw new \Hazaar\Exception('Unable to render inline HTML element that has not element type!');

        return '<' . $this->type . (($this->parameters->count() > 0) ? ' ' . $this->parameters : null) . ' />';

    }

    /**
     * Shorthand method to set an element visible
     */
    public function show(){

        $this->style('display', 'inline');

    }

}

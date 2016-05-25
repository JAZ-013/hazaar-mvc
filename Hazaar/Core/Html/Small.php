<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML small class.
 *
 * @detail      Displays an HTML &lt;small&gt; element.
 *
 * @since       1.1
 */
class Small extends Block {

    /**
     * @detail      The HTML small constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the span.
     */
    function __construct($content = null, $params = array()) {

        parent::__construct('small', $content, $params);

    }

}


<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML tbody class.
 *
 * @detail      Displays an HTML &lt;tbody&gt; element.
 *
 * @since       1.1
 */
class Tbody extends Block {

    /**
     * @detail      The HTML tbody constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('tbody', $content, $parameters);

    }

    public function addRow($fields) {

        if($fields instanceof Tr)
            return parent::add($fields);

        $tr = new Tr();

        foreach($fields as $field) {

            if(!$field instanceof Td)
                $field = new Td($field);

            $tr->add($field);

        }

        return parent::add($tr);

    }

    public function add() {

        foreach(func_get_args() as $row) {

            $this->addRow($row);

        }

        return $this;

    }

}

<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML object class.
 *
 * @detail      Displays an HTML &lt;object&gt; element.
 *
 * @since       1.1
 */
class Object extends Block {

    /**
     * @detail      The HTML object constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($data, $type = null, $parameters = array()) {

        $parameters['data'] = $data;

        if($type)
            $parameters['type'] = $type;

        parent::__construct('object', null, $parameters);

    }

    public function add() {

        foreach(func_get_args() as $arg) {

            if(is_array($arg)) {

                foreach($arg as $item)
                    $this->add($item);

            } elseif(!$arg instanceof Param) {

                throw new \Hazaar\Exception('You can only add elements of type \Hazaar\Html\Param to ' . get_class());

            } else {

                parent::add($arg);

            }

        }

        return $this;

    }

    public function param($name, $value) {

        return $this->add(new Param($name, $value));

    }

}

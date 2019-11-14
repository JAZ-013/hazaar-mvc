<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML hgroup class.
 *
 * @detail      Displays an HTML &lt;hgroup&gt; element.
 *
 * @since       1.1
 */
class Hgroup extends Block {

    /**
     * @detail      The HTML hgroup constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $parameters = array()) {

        parent::__construct('hgroup', null, $parameters);

        $this->add($content);

    }

    public function add() {

        foreach(func_get_args() as $arg) {

            if(is_array($arg)) {

                foreach($arg as $item) {

                    $this->add($item);

                }

            } elseif(!preg_match('/^Hazaar\\\Html\\\H[1-6]$/', get_class($arg))) {

                throw new \Hazaar\Exception('You can NOT add an object of type ' . get_class($arg) . ' to an Hgroup object.');

            } else {

                parent::add($arg);

            }

        }

        return $this;

    }

}

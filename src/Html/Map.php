<?php

namespace Hazaar\Html;

/**
 * @brief       The HTML mao class.
 *
 * @detail      Displays an HTML &lt;area&gt; element.
 *
 * @since       1.1
 */
class Map extends Block {

    /**
     * @detail      The HTML anchor constructor.
     *
     * @since       1.1
     *
     * @param       mixed $content The element(s) to set as the content.  Accepts strings, integer or other elements or
     *              arrays.
     *
     * @param       array $parameters Optional parameters to apply to the anchor.
     */
    function __construct($content = null, $name = null, $parameters = array()) {

        if(!$name)
            $name = uniqid();

        $parameters['name'] = $name;

        parent::__construct('map', $content, $parameters);

    }

    public function add() {

        foreach(func_get_args() as $item) {

            if(is_array($item)) {

                foreach($item as $i)
                    $this->add($i);

            } elseif(!$item instanceof Area) {

                throw new \Hazaar\Exception('You can only add objects of type \Hazaar\Area to \Hazaar\Map');

            } else {

                parent::add($item);

            }

        }

        return $this;

    }

    public function area($href, $coords, $shape = null) {

        $area = new Area($href, $coords);

        if($shape)
            $area->shape($shape);

        return $this->add($area);

    }

}

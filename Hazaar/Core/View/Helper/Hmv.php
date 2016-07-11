<?php

namespace Hazaar\View\Helper;

/**
 * The HazaarModelView renderer
 *
 * This helper renders a Hazaar\Model\Strict object that has field labels defined.
 */
class Hmv extends \Hazaar\View\Helper {

    public function import(){

        $this->requires('html');

    }

    public function render(\Hazaar\Model\Strict $model, $ignore_empty = false){

        $container = $this->html->div();

        return $container->add($this->renderItems($model->export($ignore_empty)))->class('hmvContainer');

    }

    private function renderItems($items){

        if(!is_array($items))
            return null;

        $out = array();

        foreach($items as $key => $item){

            $label = ake($item, 'label');

            if($items = ake($item, 'items')){

                $subItems = array();

                foreach($items as $subItem)
                    $subItems[] = $this->renderItems($subItem);

                $field = array(
                    $this->html->label($label)->class('hmvSectionLabel'),
                    $this->html->div($subItems)->class('hmvSubItems')
                );

            }else{

                $value = ake($item, 'value');

                if(is_array($value)){

                    $field = array(
                       $this->html->label($label)->class('hmvSectionLabel'),
                       $this->html->div($this->renderItems($value))->class('hmvSubItems')
                   );

                }else{

                    $field = array(
                        $this->html->label($label)->class('hmvItemLabel'),
                        $this->html->span($value)->class('hmvItemValue')
                    );

                }

            }

            $out[] = $this->html->div($field)->data('name', $key)->class('hmvItem');

        }

        return $out;

    }

}
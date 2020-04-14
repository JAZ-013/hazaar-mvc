<?php

namespace Hazaar\Console;

class MenuItem implements \jsonSerializable {

    public $label;

    public $name;

    public $icon;

    public $suffix;

    public $items = array();

    public $url;

    function __construct($name, $label, $url = null, $icon = null, $suffix = null){

        $this->label = $label;

        $this->name = (($name instanceof Module) ? $name->getName() : $name) . ($url? '/' . $url:null);

        $this->icon = $icon;

        if($suffix)
            $this->suffix = (is_array($suffix) ? $suffix : array($suffix));

    }

    public function addMenuItem($label, $url = null, $icon = null, $suffix = null){

        return $this->items[] = new MenuItem($this->name, $label, $url, $icon, $suffix);

    }

    public function jsonSerialize(){

        $json = [
            'name' => $this->name,
            'label' => $this->label,
            'icon' => $this->icon,
            'suffix' => $this->suffix,
            'url' => $this->url
        ];

        if(is_array($this->items) && count($this->items) > 0)
            $json['items'] = $this->items;

        return $json;

    }

}
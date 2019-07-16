<?php

namespace Hazaar\View\Helper;

/**
 * The HazaarModelView renderer
 *
 * This helper renders a Hazaar\Model\Strict object that has field labels defined.
 */
class Hmv extends \Hazaar\View\Helper {

    public $use_tables = true;

    public $container_class = 'hmvContainer';

    public $section_tag = 'h1';

    public $section_class = 'hmvSection';

    public $item_class = 'hmvItem';

    public $input_class = 'hmvInput';

    public $newitem_class = 'hmvNewItem';

    public function import(){

        $this->requires('html');

    }

    public function render(\Hazaar\Model\Strict $model, $ignore_empty = false, $export_all = false, $empty_val = null){

        if($this->use_tables){

            $container = $this->html->table();

            $container->add($this->renderTABLEItems($model->exportHMV($ignore_empty, $export_all), $empty_val));

        }else{

            $container = $this->html->div($this->renderDIVItems($model->exportHMV($ignore_empty, $export_all), $empty_val));

        }

        return $container->class($this->container_class);

    }

    private function renderTABLEItems($items, $empty_val = null){

        if(!is_array($items))
            return null;

        $itemCollection = array();

        foreach($items as $key => $item){

            $label = ake($item, 'label', $key);

            if(array_key_exists('render', $item) && is_callable($item['render']))
                $item['value'] = call_user_func($item['render'], $key, $item, $this->view);

            $no_value = empty(ake($item, 'value', null, true));

            if($no_value && ($children = ake($item, 'list'))){

                $labelTD = $this->html->td($this->html->label($label));

                $itemsTD = $this->html->td();

                foreach($children as $child)
                    $itemsTD->add($this->html->div($child));

                $itemCollection[] = $this->html->tr(array($labelTD, $itemsTD));

            }elseif($no_value && ($children = ake($item, 'collection'))){

                $section = $this->html->td($this->html->block($this->section_tag, $label));

                $childTable = $this->html->table()->class($this->container_class);

                foreach($children as $child)
                    $childTable->add($this->renderTABLEItems($child, $empty_val));

                $itemCollection[] = $this->html->tr(array($section, $this->html->td($childTable)));

            }elseif($no_value && ($children = ake($item, 'items'))){

                $section = $this->html->td($this->html->block($this->section_tag, $label));

                $childTable = $this->html->table()->class($this->container_class);

                $childTable->add($this->renderTABLEItems($children, $empty_val));

                $itemCollection[] = $this->html->tr(array($section, $this->html->td($childTable)));

            }else{

                $labelTD = $this->html->td($this->html->label($label));

                $value = ake($item, 'value', $empty_val, true);

                if(ake($item, 'empty', true) === false && $value === $empty_val)
                    continue;

                if(is_bool($value))
                    $value = ucfirst(strbool($value));

                $itemCollection[] = $this->html->tr(array($labelTD, $this->html->td($value)))->data('name', $key);

            }

        }

        return $itemCollection;

    }

    private function renderDIVItems($items, $empty_val = null){

        if(!is_array($items))
            return null;

        $itemCollection = array();

        foreach($items as $key => $item){

            if($children = ake($item, 'list')){

                $label = $this->html->label(ake($item, 'label'));

                $itemsDiv = $this->html->td();

                foreach($children as $child)
                    $itemsDiv->add($this->html->div($child));

                $itemCollection[] = $this->html->div(array($label, $itemsDiv));

            }elseif($children = ake($item, 'collection')){

                $section = $this->html->block($this->section_tag, ake($item, 'label'))->class($this->section_class);

                $childDiv = $this->html->div()->class($this->container_class);

                foreach($children as $child)
                    $childDiv->add($this->renderDIVItems($child, $empty_val));

                $itemCollection[] = $this->html->div(array($section, $childDiv));

            }elseif($children = ake($item, 'items')){

                $section = $this->html->block($this->section_tag, ake($item, 'label'))->class($this->section_class);

                $childDiv = $this->html->div()->class($this->container_class);

                $childDiv->add($this->renderDIVItems($children, $empty_val));

                $itemCollection[] = $this->html->div(array($section, $childDiv));

            }else{

                $label = $this->html->label(ake($item, 'label'));

                if(array_key_exists('render', $item) && is_callable($item['render']))
                    $item['value'] = call_user_func($item['render'], $key, $item, $this->view);

                $value = ake($item, 'value', $empty_val, true);

                if(ake($item, 'empty', true) === false && $value === $empty_val)
                    continue;

                if(is_bool($value))
                    $value = ucfirst(strbool($value));

                $itemCollection[] = $this->html->div(array($label, $this->html->span($value)))->class($this->item_class)->data('name', $key);

            }

        }

        return $itemCollection;

    }

    public function renderEditor(\Hazaar\Model\Strict $model, $export_all = false){

        $container = $this->html->table()->class($this->container_class);

        return $container->add($this->renderInputs($model, null, $export_all));

    }


    private function renderInputs(\Hazaar\Model\Strict $object, $prefix = null, $export_all = false){

        $tableRows = array();

        $typeMap = array(
            'string' => 'text',
            'bool' => 'checkbox',
            'boolean' => 'checkbox',
            'float' => 'number',
            'int' => 'number',
            'integer' => 'number'
        );

        $hiddenInputs = $this->html->td();

        foreach($object->toArray(true, 0, $export_all) as $key => $item){

            if($prefix)
                $name = $prefix . '[' . $key . ']';
            else
                $name = $key;

            if(!($def = $object->getDefinition($key)))
                $def = array();

            if(ake($def, 'hideInEdit') === true)
                continue;

            if(ake($def, 'hide') === true){

                $hiddenInputs->add($this->html->input('hidden', $name, $item));

                continue;

            }

            if(!($label = ake($def, 'label'))){

                if($export_all)
                    $label = $key;
                else
                    continue;

            }

            if(!$item && $object->isObject($key)){

                $newItem = $object->set($key, array());

                if($newItem instanceof \Hazaar\Model\Strict)
                    $item = $newItem;

            }

            $input = null;

            $edit = ake($def, 'edit', true);

            if(is_callable($edit))
                $edit = $edit($item, $def, $object);

            if($edit == false){

                $labelTD = $this->html->td($this->html->label($label));

                if(is_array($item)){

                    $input = $this->html->div();

                    foreach($item as $subItem)
                        $input->add((string)$subItem);

                }else{

                    $input = (string)$item;

                }

            }elseif(array_key_exists('input', $def) && $def['input'] instanceof \Closure){

                $labelTD = $this->html->td($this->html->label($label));

                $input = call_user_func($def['input'], $name, $item, $this->view);

            }elseif($item instanceof \Hazaar\Model\Strict){

                //If the object definition has a data source, use that to create the select
                if($data = $this->getItemData($def, $item)){

                    $labelTD = $this->html->td($this->html->label($label)->for($name));

                    if(ake($def, 'nulls', true))
                        $data = array('null' => 'Select...') + $data;

                    if($valueKey = ake($def, 'valueKey'))
                        $value = $item->get($valueKey);
                    else
                        $value = (string)$item;

                    $input = $this->html->select($name, $data, $value)->class($this->input_class)->id($name);

                }else{ //Otherwise, try and render the object as a sub-object.

                    $labelTD = $this->html->td($this->html->block($this->section_tag, $label));

                    $input = $this->html->table()->class($this->container_class);

                    $input->add($this->renderInputs($item, $name, $export_all));

                }

            }elseif(is_array($item) || $item instanceof \Hazaar\Model\ChildArray){

                $labelTD = $this->html->td($this->html->block($this->section_tag, $label));

                if(array_key_exists('source', $def)){

                    if($class = ake($def, 'arrayOf', ake($def, 'type')))
                        $obj = new $class();
                    else
                        $obj = $item;

                    if($data = $this->getItemData($def, $obj)){

                        $input = $this->html->div();

                        $values = array();

                        if($valueKey = ake($def, 'valueKey')){

                            foreach($item as $i)
                                $values[] = $i->get($valueKey);

                        }

                        $count = 0;

                        foreach($data as $itemName => $itemValue){

                            $checkbox = $this->html->input('checkbox', $name . '[' . $itemName . ']');

                            if(in_array($itemName, $values))
                                $checkbox->checked(true);

                            $input->add($this->html->div($this->html->label(array($checkbox, $this->html->span($itemValue)))));

                            $count++;

                        }

                    }

                }elseif(ake($def, 'arrayOf')){

                    $input = array();

                    $delTR = $this->html->tr(array($this->html->td(), $this->html->td($this->html->span()->class('btnDelItem'))));

                    foreach($item as $index => $i){

                        $table = $this->html->table()->class($this->container_class);

                        $input[] = $table->add($this->renderInputs($i, $name . '[' . $index . ']', $export_all), $delTR);

                    }

                    $table = $this->html->table()->class($this->container_class);

                    $input[] = $table->add($this->renderInputs($object->append($key, array()), $name . '[]', $export_all), $delTR)
                        ->addClass($this->newitem_class);

                    $input[] = $this->html->span()->class('btnNewItem');

                }else{

                    throw new \Exception('Unsupported array: ' . $name);

                }

            }else{

                $values = null;

                $labelTD = $this->html->td($this->html->label($label)->for($name));

                if($values = $this->getItemData($def, $object))
                    $def['input'] = 'array';

                elseif(!array_key_exists('input', $def))
                    $def['input'] = ake($typeMap, ake($def, 'type', 'string'), 'text');

                switch($type = ake($def, 'input')){
                    case 'array':
                        $input = $this->html->select($name, $values, $item);
                        break;
                    case 'date':
                        if($item instanceof \Hazaar\Date) $item = $item->format('Y-m-d');
                    case 'checkbox':
                    case 'text':
                    default:
                        if(ake($def, 'multiline') === true)
                            $input = $this->html->textarea($name, $item)->class($this->input_class)->id($name);
                        else
                            $input = $this->html->input($type, $name, $item)->class($this->input_class)->id($name);
                        break;

                }

                if($input instanceof \Hazaar\Html\Element && $placeholder = ake($def, 'placeholder'))
                    $input->placeholder($placeholder);

            }

            $tableRows[] = $this->html->tr(array($labelTD, $this->html->td($input)))->data('name', $key);

        }

        if($hiddenInputs->count() > 0)
            $tableRows[] = $this->html->tr($hiddenInputs);

        return $tableRows;

    }

    private function getItemData($def, $obj){

        if(!($source = ake($def, 'source')))
            return null;

        $data = array();

        //If it's an array but not callable, it's an array of data
        if(is_array($source) && !is_callable($source)){

            $data = $source;

            //Otherwise it is supposed to be a callable
        }else{

            //But if it's not
            if(!is_callable($source)){

                //Look for the method if it's a string
                if(is_string($source) && method_exists($obj, $source))
                    $source = array($obj, $source);
                else
                    return null;

            }

            $data = call_user_func_array($source, ake($def, 'sourceArgs', array()));

        }

        return $data;

    }

}
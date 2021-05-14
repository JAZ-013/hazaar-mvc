<?php

namespace Hazaar\Html;

/**
 * ElementContainer short summary.
 *
 * ElementContainer description.
 *
 * @version 1.0
 * @author jamiec
 */
class ElementCollection implements \ArrayAccess, \Iterator {

    private $elements = array();

    private $original_selector;

    function __construct(&$objects = null, $selector = null, $recursive = false){

        $this->original_selector = $selector;

        if(is_array($objects) && count($objects) > 0)
            $this->elements = $this->match($objects, $this->original_selector,$recursive);

    }

    static private function match(&$objects, $selector = null, $recursive = false){

        //Split on a comma and any amount of adjacent white space
        $parts = preg_split('/\s*,\s*/', $selector);

        $ruleset = array();

        //Compile all the selector rules.
        foreach($parts as $part)
            $ruleset[] = ElementCollection::compileRules($part, count($objects));

        return ElementCollection::applyRuleset($objects, $ruleset, $recursive);

    }

    static public function compileRules($selector, $count){

        $rules = array(
                'type' => null,
                'id' => null,
                'classes' => array(),
                'attributes' => array(
                    'match' => array(),
                    'exists' => array()
                ),
                'ranges' => array(),
                'pseudo-class' => array(),
                'func' => array(),
                'not' => array()
            );

        $selectors = preg_split('/\:/', $selector);

        $primary = array_shift($selectors);

        //Match element type references (single)
        if(preg_match('/^(\w+)/', $primary, $matches))
            $rules['type'] = strtoupper($matches[1]);

        //Match ID reference (single)
        if(preg_match('/\#([\w-]*)/', $primary, $matches))
            $rules['id'] = $matches[1];

        //Match class references (multi)
        if(preg_match_all('/\.([\w-]*)/', $primary, $matches)){

            foreach($matches[1] as $match)
                $rules['classes'][] = $match;

        }

        //Match attribute references (multi)
        if(preg_match_all('/\[(.+)\]/U', $primary, $matches)){

            foreach($matches[1] as $match){

                if(preg_match('/(\w+)=[\'"]?([\s\w]+)[\'"]?/', $match, $pair))
                    $rules['attributes']['match'][$pair[1]] = $pair[2];
                else
                    $rules['attributes']['exists'][] = $match;

            }

        }

        if(count($selectors) > 0){

            foreach($selectors as $pseudo_class){

                if(preg_match('/([\w\-]+)\((.*)\)/', $pseudo_class, $func)){

                    if($func[1] == 'not'){

                        $rules['not'][] = ElementCollection::compileRules($func[2], $count);

                    }elseif(substr($func[1], 0, 4) == 'nth-'){

                        if($func[2] == 'even')
                            $func[2] = '2n+0';
                        elseif($func[2] == 'odd')
                            $func[2] = '2n+1';

                        if(preg_match('/^(([\+\-]?\d*)n)?([\+\-]?\d*)$/', $func[2], $bits)){

                            $a = intval($bits[2]);

                            $b = intval($bits[3]);

                            $range = array();

                            switch($func[1]){
                                case 'nth-child':

                                    if($a > 0){

                                        $i = 0;

                                        while(($pos = (($a * $i++) + $b)) <= $count){

                                            if($pos > 0)
                                                $range[] = $pos - 1;

                                        }

                                    }elseif($b < 0){

                                        $range[] = $count + $b;

                                    }else{

                                        $range[] = $b - 1;

                                    }

                                    break;

                                default:

                                    throw new \Hazaar\Exception('Unsupported nth pseudo class class selector: ' . $pseudo_class);

                            }

                            $rules['ranges'][] = $range;

                        }

                    }else{

                        throw new \Hazaar\Exception('Unsupported complex pseudo-class: ' . $func[1]);

                    }

                }else{

                    $rules['pseudo-class'][] = $pseudo_class;

                }

            }

        }

        return $rules;

    }

    static private function applyRuleset(&$objects, &$ruleset, $recursive = false){

        $collection = array();

        foreach($objects as $index => $object){

            if(!$object instanceof Element)
                continue;

            if($recursive && $object instanceof Block)
                $collection += ElementCollection::applyRuleset($object->get(), $ruleset, $recursive);

            foreach($ruleset as $rules){

                if(ElementCollection::matchElement($object, $rules, $index, count($objects)))
                    $collection[] = $object;

            }

        }

        return $collection;

    }

    static public function matchElement($element, $rules, $index, $count){

        if($rules['type'] && $rules['type'] != $element->getTypeName())
            return false;

        if($rules['id'] && $rules['id'] != $element->attr('id'))
            return false;

        if(count($rules['classes']) > 0 && count(array_diff($rules['classes'], (array)$element->attr('class'))) > 0)
            return false;

        if(count($rules['attributes']['exists']) > 0 && count(array_diff($rules['attributes']['exists'], array_keys($element->parameters()->toArray()))) > 0)
            return false;

        if(count($rules['attributes']['match']) > 0){

            foreach($rules['attributes']['match'] as $key => $value){

                if($element->attr($key) != $value)
                    return false;

            }

        }

        if(count($rules['ranges']) > 0){

            foreach($rules['ranges'] as $range){

                if(!in_array($index, $range))
                    return false;

            }

        }

        if(count($rules['pseudo-class']) > 0){

            $group_classes = array('first-child', 'last-child');

            foreach($rules['pseudo-class'] as $pseudo){

                if(in_array($pseudo, $group_classes)){

                    if($pseudo == 'first-child' && $index !== 0)
                        return false;
                    elseif($pseudo == 'last-child' && $index !== ($count - 1))
                        return false;

                }elseif(!$element->attr()->has($pseudo))
                    return false;

            }

        }

        if(count($rules['not']) > 0){

            foreach($rules['not'] as $rule){

                if(ElementCollection::matchElement($element, $rule, $index, $count))
                    return false;

            }

        }

        return true;

    }

    static function applyFunction(&$object, $name, $args){

    }

    public function filter($selector){

        foreach($this->elements as $id => $object){

            if(!$this->match($object, $selector))
                unset($this->elements[$id]);

        }

        return $this;

    }

    public function count(){

        return count($this->elements);

    }

    public function add($elements){

        if($elements instanceof ElementCollection)
            $elements = $elements->elements;
        elseif(!is_array($elements))
            return $this;

        $collection = new ElementCollection(null, $this->original_selector);

        $collection->elements = $this->elements + $elements;

        return $collection;

    }

    public function children($selector = null){

        $elements = array();

        foreach($this->elements as $element)
            $elements += $element->get();

        return new ElementCollection($elements, $selector);

    }

    public function find($selector = null){

        return new ElementCollection($this->elements, $selector, true);

    }

    public function __call($method, $args){

        if(is_array($this->elements)){

            foreach($this->elements as $element)
                call_user_func_array(array($element, $method), $args);

        }

        return $this;

    }

    public function __toString(){

        $output = '';

        foreach($this->elements as $element)
            $output .= $element;

        return $output;

    }

    public function get($index){

        return ake($this->elements, $index);

    }

    public function offsetExists($offset){

        return array_key_exists($offset, $this->elements);

    }

    public function offsetGet($offset){

        if(array_key_exists($offset, $this->elements))
            return $this->elements[$offset];

        return null;

    }

    public function offsetSet($offset, $value){

        if(!$value instanceof Element)
            return;

        $this->elements[$offset] = $value;

    }

    public function offsetUnset($offset){

        if(array_key_exists($offset, $this->elements))
            unset($this->elements[$offset]);

    }

    public function current(){

        return current($this->elements);

    }

    public function next(){

        return next($this->elements);

    }

    public function key(){

        return key($this->elements);

    }

    public function valid(){

        return (current($this->elements));

    }

    public function rewind(){

        return reset($this->elements);

    }

}
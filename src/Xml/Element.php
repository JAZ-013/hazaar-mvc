<?php

namespace Hazaar\Xml;

/**
 * @brief       Represents an element in an XML document.
 *
 * @detail      XML document creation and parsing has been greatly simplified with the class.  Documents can be created
 *              rapidly and will full support for attributes and namespaces.
 *
 *              ```php
 *              $xml = new Hazaar\Xml\Element('list');
 *              $xml->properties['name'] = 'myProperties';
 *              $xml->properties->add('property', 'Property #1');
 *              $xml->properties->add('property', 'Property #2');
 *              echo $xml;
 *              ```
 *
 *              The above example will output the following XML:
 *
 *              ```xml
 *              <?xml version="1.0" encoding="utf-8" ?>
 *              <list>
 *                  <properties name="myProperties">
 *                     <property>
 *                         Property #1
 *                     </property>
 *                     <property>
 *                          Property #2
 *                      </property>
 *                  </properties>
 *              </list>
 *              ```
 *
 * @since 2.0.0
 */
class Element implements \ArrayAccess, \Iterator {

    /**
     * Current supported XML version
     */
    const VERSION = '1.0';

    /**
     * Encoding format to use when generating XML as a string
     */
    const ENCODING = 'utf-8';

    /**
     * The direct parent object of the current node element
     */
    public $__parent;

    /**
     * The name of the current node element
     */
    protected $__name;

    /**
     * The namespace prefix for the current node element
     */
    protected $__namespace;

    /**
     * The default namespace prefix to use when accessing child members.
     */
    protected $__default_namespace;

    /**
     * An array of namespaces defined on this node
     */
    protected $__namespaces = array();

    /**
     * An array of attributed defined on this node
     */
    protected $__attributes = array();

    /**
     * An array to contain all the child nodes of this node element
     */
    protected $__children       = NULL;

    protected $__children_index = NULL;

    /**
     * If there are node child nodes, the value that will used for this node element.
     */
    private $__value = NULL;

    /**
     * Toggle to indicate if the Element is being accessed as an array and is reset.
     */
    private $__reset   = TRUE;

    public  $open_tag  = '<';

    public  $close_tag = '>';

    /**
     * Creates a new \Hazaar\XML\Element object
     *
     * @since 2.0.0.0
     *
     * @param string $name The name of the element to create, optionally including a namespace
     *
     * @param mixed $value The value of the element.  This can be pretty much anything, including another
     *                           \Hazaar\Xml\Element object.
     *
     * @param array $namespaces Array of namespaces to declare where the key is the prefix and the value is the
     *                           namespace URI
     *
     * @param string $open_tag Configurable open tag for elements.  XML defines this as '<'.  This can be changed for
     *                          use with alternative file formats.
     *
     * @param string $close_tag Configurable close tag for elements.  XML defines this as '>'.  This can be changed for
     *                          use with alternative file formats.
     */
    function __construct($name = NULL, $value = NULL, $namespaces = NULL, $open_tag = '<', $close_tag = '>') {

        $this->open_tag = $open_tag;

        $this->close_tag = $close_tag;

        $this->setName($name);

        if($value instanceof Element) {

            $name = $value->getName();

            $this->__children[$name] = $value;

        } else {

            $this->__value = $value;

        }

        if(is_array($namespaces)) {

            $this->__namespaces = $namespaces;

        }

    }

    public function __clone() {

        if(count($this->__children) > 0) {

            foreach($this->__children as &$child)
                $child = clone $child;

        }

    }

    /**
     * Set the open and close tag characters for parsing and generating XML.
     *
     * By changing the open and close tags we are able to use the \Hazaar\XML\Element class to work with text formats
     * other than XML but that have a similar format.
     *
     * @param string $open_tag The open tag to set.
     *
     * @param string $close_tag The close tag to set.
     */
    public function setTagChars($open_tag, $close_tag) {

        $this->open_tag = $open_tag;

        $this->close_tag = $close_tag;

        if(! is_array($this->__children))
            return;

        foreach($this->__children as $element) {

            if($element instanceof Element)
                $element->setTagChars($open_tag, $close_tag);

        }

    }

    /**
     * Sets the default namespace to use when accessing child nodes as members
     *
     * The namespace MUST be a valid namespace already defined in the objects.  If the namespace is not defined then
     * FALSE will be returned.  The namespace can be referenced with by it's alias or it's URL.  In the latter case
     * the alias will be looked up.  Also, if the URL is not found the shortcut URL will be tried which is the URL
     * with a colon appended (eg: DAV:).
     *
     * @param string $ns The name/url of the namespace to use/prefix as default.
     *
     * @return boolean
     */
    public function setDefaultNamespace($ns) {

        if(! array_key_exists($ns, $this->__namespaces)) {

            $key = NULL;

            $search = array($ns, $ns . ':');

            foreach($search as $cur) {

                if($ns = array_search($cur, $this->__namespaces))
                    break;

            }

        }

        if(! $ns)
            return FALSE;

        $this->__default_namespace = $ns;

        return TRUE;

    }

    /**
     * Returns the default namespace for the current node object
     *
     * The default namespace does not need to be set on the current object and can be set on a parent object.  This
     * method will look at it's own default namespace and if one is not set it will then request one from the parent.
     *
     * @return string The default namespace to use.
     */
    public function getDefaultNamespace() {

        if($this->__default_namespace)
            return $this->__default_namespace;

        if($this->__parent instanceof Element)
            return $this->__parent->getDefaultNamespace();

        return NULL;

    }

    /**
     * Sets the name of the current \Hazaar\XML\Element object.
     *
     * Namespaces can be specifed using the colon separator.  eg: 'namespace:name'.
     *
     *
     * ```php
     * $node->setName('C:mynode');
     * ```
     *
     * @since 2.0.0.0
     *
     * @param string $name The name to set for the current object, optionally including a namespace.
     */
    protected function setName($name) {

        if(($ns_sep = strpos($name, ':')) !== FALSE) {

            $this->__namespace = substr($name, 0, $ns_sep);

            $name = substr($name, $ns_sep + 1);

        }

        $this->__name = $name;

    }

    /**
     * Sets the parent object for the current Hazaar\Xml\Element object.
     *
     * This is an internal method and is not accessible outside of the Hazaar\Xml\Element class.
     *
     * @since 2.0.0.0
     *
     * @param \Hazaar\Xml\Element $parent The Hazaar\Xml\Element object to use as the parent.
     */
    private function setParent(Element $parent) {

        $this->__parent = $parent;

    }

    /**
     * Declare a namespace on the current node element
     *
     * @param string $prefix A namespace prefix.
     *
     * @param string $value The value of the namespace.
     */
    public function addNamespace($prefix, $value) {

        $this->__namespaces[$prefix] = $value;

    }

    /**
     * Test if a namespace is available for the current node element
     *
     * This is a recursive test, meaning if the namespace isn't defined on the current element then it's parent will be
     * checked.  This is because namespaces, once declared, are available to all child elements of the node the
     * namespace is declared on.
     *
     * @since 2.0.0.0
     *
     * @param string $prefix The namespace prefix to check.
     *
     * @return boolean
     */
    public function namespaceExists($prefix) {

        if(! ($ret = array_key_exists($prefix, $this->__namespaces))) {

            if($this->__parent instanceof Element)
                $ret = $this->__parent->namespaceExists($prefix);

        }

        return $ret;

    }

    /**
     * Set an attribute on the current Hazaar\XML\Element object.
     *
     * @since 2.0.0.0
     *
     * @param string $name The name of the attribute.
     *
     * @param string $value The value of the attribute.
     */
    public function attr($name, $value) {

        if($value instanceof \Hazaar\Date)
            $value = $value->formatTZ('Ymd\THis\Z', 'UTC');

        if(is_null($name)) {

            if($this->__parent instanceof Element) {

                $this->__parent->add($this->__name, $value);

            }

        } else {

            $this->__attributes[$name] = $value;

        }

    }

    /**
     * Return an attribute value
     *
     * @param string $name The name of the attribute to return
     *
     * @return string The value of the attribute.
     */
    public function getAttr($name) {

        return ake($this->__attributes, $name);

    }

    /**
     * Returns a list of attributes current declared on the node element.
     *
     * @since 2.0.0.0
     *
     * @return array
     */
    public function attributes() {

        return $this->__attributes;

    }

    /**
     * Adds a new element to the current node element.
     *
     * @since 2.0.0.0
     *
     * @param string $name The name of the element to create, optionally including a namespace
     *
     * @param mixed $value The value of the element.  This can be pretty much anything, including another
     *                           \Hazaar\Xml\Element object.
     *
     * @param array $namespaces Array of namespaces to declare where the key is the prefix and the value is the
     *                           namespace URI
     *
     * @return \Hazaar\Xml\Element The new child object.
     */
    public function add($name, $value = NULL, $namespaces = NULL) {

        $child = new Element($name, $value, $namespaces, $this->open_tag, $this->close_tag);

        $child->setParent($this);

        if(! $this->__name && ! $this->__parent)
            $child->__namespaces = $this->__namespaces;

        if(! is_array($this->__children))
            $this->__children = array();

        if(! is_array($this->__children_index))
            $this->__children_index = array();

        $this->__children[] =& $child;

        if(array_key_exists($name, $this->__children_index)) {

            if(! is_array($this->__children_index[$name])) {

                $this->__children_index[$name] = array(
                    $this->__children_index[$name]
                );
            }

            $this->__children_index[$name][] = $child;

        } else {

            $this->__children_index[$name] = $child;

        }

        return $child;

    }

    /**
     * Returns a child by it's node name.
     *
     * If the node doesn't exist, then it is automatically added.
     *
     * @since 2.0.0.0
     *
     * @param string $name The name of the element to create, optionally including a namespace
     *
     * @param mixed $value The value of the element.  This can be pretty much anything, including another
     *                           Hazaar\Xml\Element object.
     *
     * @param array $namespaces Array of namespaces to declare where the key is the prefix and the value is the
     *                           namespace URI
     *
     * @return \Hazaar\Xml\Element
     */
    public function child($name, $value = NULL, $namespaces = NULL) {

        if($ns = $this->getDefaultNamespace())
            $name = $ns . ':' . $name;

        if(is_array($this->__children_index) && array_key_exists($name, $this->__children_index)) {

            $child = $this->__children_index[$name];

            if(! is_null($value))
                $child->value($value);

        } else {

            $child = $this->add($name, $value, $namespaces);

        }

        return $child;

    }

    /**
     * Returns a list of all child elements on the current node element
     *
     * @since 2.0.0.0
     *
     * @return array
     */
    public function & children() {

        return $this->__children;

    }

    /**
     * Returns the number of children on the current node element
     *
     * @since 2.0.0.0
     *
     * @return integer
     */
    public function count() {

        if(! is_array($this->__children))
            return 0;

        return count($this->__children);

    }

    /**
     * Returns the TRUE or FALSE to indicate that the node has child elements
     *
     * @since 2.0.0.0
     *
     * @return integer
     */
    public function hasChildren() {

        return (is_array($this->__children) && count($this->__children) > 0);

    }

    /**
     * Get the full valid name of the current node element
     *
     * This will return the full name which includes the namespace that was originally defined ONLY if the namespace is
     * valid.  Namespaces are valid if they have also been defined with a call to Hazar\Xml\Element::addNamespace() on
     * this element or a parent element.
     *
     * If the namespace is NOT valid, then it is simply ignored.  If the namespace should be returned regardless of it's
     * validity, use the $include_invalid_namespace parameter.
     *
     *
     * @since 2.0.0.0
     *
     * @param boolean $include_invalid_namespace Include namespaces that have no been defined in the current or parent
     *                                           nodes.
     *
     * @return string
     */
    public function getName($include_invalid_namespace = FALSE) {

        if($include_invalid_namespace || ($this->__namespace && $this->namespaceExists($this->__namespace))) {

            return ($this->__namespace ? $this->__namespace . ':' : NULL) . $this->__name;

        }

        return $this->__name;

    }

    public function getLocalName() {

        return $this->__name;

    }

    public function getNamespace() {

        return $this->__namespace;

    }

    /**
     * Get or set the current value of the node element
     *
     * If the node element does not have any child nodes then it's value can be set directly.  If there are child nodes
     * defined then this value is ignored when generating XML output using the Hazaar\Xml\Element::toXML() method.
     *
     * If the $value parameter is not defined then current value is returned without modification
     *
     *
     * @since 2.0.0.0
     *
     * @param string $value The value to set.  If this is null then the value will not be modified.  To empty the value
     *                      set it to an empty string.
     *
     * @return string The current value of the element.
     */
    public function value($value = NULL) {

        if($value !== NULL) {

            $value = trim($value);

            if($value)
                $this->__value = $value;

        }

        return $this->__value;

    }

    /**
     * Returns an array of namespaces currently defined on the current node element
     *
     * Optionally return any namespaces defined anywhere in the data object model.
     *
     *
     * @since 2.0.0.0
     *
     * @param boolean $recursive Recurse through all child node elements and append their namespace declarations to the
     *                           list.
     *
     * @return array An array of defined namespaces with keys as the prefix and URIs as the value.
     */
    public function getNamespaces($recursive = FALSE) {

        $namespaces = $this->__namespaces;

        if($recursive && is_array($this->__children)) {

            foreach($this->__children as $child)
                $namespaces = array_merge($namespaces, $child->getNamespaces($recursive));

        }

        return $namespaces;

    }

    private function resolveXML($element) {

        if($element instanceof Element)
            return $element->toXML();

        if(is_array($element)) {

            $xml = '';

            foreach($element as $e)
                $xml .= $this->resolveXML($e);

            return $xml;

        }

        return NULL;

    }

    /**
     * Output the current XML structure as a string or save to file.
     *
     * This method generates the current XML structure as a returns it as a string resolving all names, namespaces and
     * child elements.
     *
     * @since 2.0.0.0
     *
     * @param string $filename Optional filename to save XML directly into.
     *
     * @return Mixed If saving to file, returns true/false indicating the file write result.  Otherwise returns the XML
     * as a string.
     */
    public function toXML($filename = NULL) {

        $xml = (! $this->__parent ? $this->open_tag . '?xml version="' . $this::VERSION . '" encoding="' . $this::ENCODING . '" ?' . $this->close_tag . "\n" : '');

        if($this->__name) {

            $xml .= $this->open_tag . $this->getName();

            foreach($this->__attributes as $name => $value) {

                $xml .= ' ' . $name . '="' . $value . '"';

            }

            foreach($this->__namespaces as $prefix => $value) {

                $xml .= ' xmlns:' . $prefix . '="' . $value . '"';

            }

        }

        if(is_array($this->__children) && count($this->__children) > 0) {

            if($this->__name)
                $xml .= $this->close_tag;

            foreach($this->__children as $children)
                $xml .= $this->resolveXML($children);

            if($this->__name)
                $xml .= $this->open_tag . '/' . $this->getName() . $this->close_tag;

        } elseif($this->__name) {

            if($this->__value) {

                $xml .= $this->close_tag . $this->__value . $this->open_tag . '/' . $this->getName() . $this->close_tag;

            } else {

                $xml .= ' /' . $this->close_tag;

            }

        }

        if($filename)
            return file_put_contents($filename, $xml);

        return $xml;

    }

    /**
     * Magic method to return the current node element as a string
     *
     * @since 2.0.0.0
     *
     * @return string
     */
    public function __toString() {

        return $this->toXML();

    }

    /**
     * Load an XML definition from a string
     *
     * The [[Hazaar\Xml\Element]] class can not only be used to generate XML, but also parse it to allow programmatic
     * access to the data structure.  This method takes a single string argument and attempts to parse it as valid XML.
     *
     * @since 2.0.0.0
     *
     * @param string $xml The XML source string
     *
     * @return boolean Indicates success or failure
     */
    public function loadXML($xml) {

        $data = '';

        $parents = array();

        $parent = NULL;

        $child = NULL;

        $len = strlen($xml);

        for($i = 0; $i < $len; $i++) {

            $c = $xml[$i];

            if($c == $this->open_tag) {

                $node = '';

                $l = NULL;

                $in_str = FALSE;

                for($i++; $i < $len; $i++) {

                    $c = $xml[$i];

                    if($c == $this->close_tag && ! $in_str)
                        break;

                    elseif($c == '"' && $l != '\\')
                        $in_str = ! ($in_str);

                    elseif($c == '!' && ! $in_str) {

                        $i++;

                        if(substr($xml, $i, 2) == '--') { //It's a comment!

                            $exit = 0;

                            for($i++; $i < $len; $i++) {

                                $c = $xml[$i];

                                if($c == '-')
                                    $exit++;

                                elseif($c == $this->close_tag && $exit == 2)
                                    break;

                                else
                                    $exit = 0;

                            }

                            $i++;

                            continue 2;

                        } elseif(substr($xml, $i, 6) == '[CDATA') {

                            $i += 6;

                            if(! $xml[$i] == '[')
                                continue;

                            $cdata = '';

                            for($i++; $i < $len; $i++) {

                                $c = $xml[$i];

                                if($c == ']') {

                                    if(substr($xml, $i, strlen($this->close_tag) + 2) == (']]' . $this->close_tag)) {

                                        $i += 3;

                                        $parent->value($cdata);

                                        continue 2;

                                    }

                                }

                                $cdata .= $c;

                            }

                        }

                    } elseif(($c == ' ' && $l == ' ') || ($in_str && $c == '\\'))
                        continue;

                    $l = $c;

                    $node .= $c;

                }

                if(substr($node, 0, 1) == '?') {

                    continue;

                } elseif(substr($node, 0, 1) == '/') {

                    if(substr($node, 1) == $parent->getName(TRUE)) {

                        $parent->value($data);

                        $parent = array_pop($parents);

                        $data = '';

                    }

                } else {

                    $parts = preg_split('/(?:\'[^\']*\'|"[^"]*")(*SKIP)(*F)|\h+/', rtrim(str_replace("\n", '', $node), '/ '));

                    if(! $parent) {

                        $this->setName(array_shift($parts));

                        $child = $this;

                    } else {

                        $child = $parent->add(array_shift($parts));

                    }

                    if(count($parts) > 0) {

                        foreach($parts as $attribute) {

                            if(strpos($attribute, '=')) {

                                list ($key, $value) = explode('=', $attribute);

                                if(substr($key, 0, 6) == 'xmlns:') {

                                    $prefix = explode(':', $key)[1];

                                    $child->addNamespace($prefix, trim($value, '"'));

                                } else {

                                    $child[$key] = trim($value, '"');
                                }

                            }

                        }

                    }

                    if(substr(trim($node), -1, 1) !== '/') {

                        array_push($parents, $parent);

                        $parent = $child;

                    }

                }

            } else {

                $data .= $c;

            }

        }

        return TRUE;

    }

    /**
     * Tests if a child element exists on the current node
     *
     * @since 2.0.0
     *
     * @param string $name The name of the child element with optional namespace
     *
     * @return boolean True if the child element exists, false otherwise.
     */
    public function hasChild($name) {

        return (is_array($this->__children_index) && array_key_exists($name, $this->__children_index));

    }

    /**
     * Tests if an attribute exists on the current node
     *
     * @since 2.0.0
     *
     * @param string $name The name of the child element with optional namespace
     *
     * @return boolean True if the child element exists, false otherwise.
     */
    public function hasAttr($name) {

        return (is_array($this->__attributes) && array_key_exists($name, $this->__attributes));

    }

    /**
     * Searches children to find an element that matches search criteria
     *
     * @since 2.0.1
     *
     * @param array $criteria An array of attribute criteria to search on.  Example: array('name' => 'test') will find elements who have a name attribute with a value of 'test'.
     *
     * @param string $name Optional node name to filter on.
     *
     * @return \Hazaar\Xml\Element The child element if found.  NULL Otherwise.
     *
     */
    public function find($criteria, $name = NULL) {

        if($this->count() > 0) {

            foreach(ake($this->__children_index, $name, array()) as $nodeName => $child) {

                foreach($criteria as $key => $value) {

                    if(! ($child->hasAttr($key) && $child[$key] == $value))
                        continue 2;

                }

                return $child;

            }

        }

        return NULL;

    }

    /**
     * Magic method to access a child element by it's name.
     *
     * This will automatically create a new child element when accessing a node that does not yet exist.
     *
     * @warning This only works when not working with namespaces.
     *
     * @since 2.0.0
     *
     * @param string $name The name of the child element to return.
     *
     * @return \Hazaar\Xml\Element The child element being requested.
     */
    public function __get($name) {

        if(! is_array($this->__children_index))
            return NULL;

        if($ns = $this->getDefaultNamespace())
            $name = $ns . ':' . $name;

        if(array_key_exists($name, $this->__children_index))
            return $this->__children_index[$name];

        return $this->child($name);

    }

    /**
     * Magic method to automatically create a new child element on access
     *
     * @warning This only works when not working with namespaces.
     *
     * @since 2.0.0
     *
     * @param string $name The name key of child node to modify.
     *
     * @param string $value he value to set.
     *
     * @return \Hazaar\Xml\Element Returns the child node being modified.
     */
    public function __set($name, $value) {

        return $this->child($name, $value);

    }

    /**
     * Set element attribute
     *
     * @param string $name The name key of child node to modify.
     *
     * @param mixed $value The value to set.
     */
    public function offsetSet($name, $value) {

        $this->attr($name, $value);

    }

    /**
     * Test if element attribute exists
     *
     * @param string $name The name key of child node to check.
     *
     * @return boolean TRUE if the child node exists, FALSE otherwise.
     */
    public function offsetExists($name) {

        return $this->hasAttr($name);

    }

    /**
     * Return an attribute value
     *
     * @param string $name The name of the attribute to return
     *
     * @return string The value of the attribute.
     */
    public function offsetGet($name) {

        return ake($this->__attributes, $name);

    }

    /**
     * Unset an elements attribute
     *
     * @param string $name The key name of the child to unset.
     *
     */
    public function offsetUnset($name) {

        if(is_array($this->__attributes) && array_key_exists($name, $this->__attributes))
            unset($this->__attributes[$name]);

    }

    /**
     * Return the current child element
     */
    public function current() {

        if(! is_array($this->__children))
            return ($this->__reset ? $this : NULL);

        return current($this->__children);

    }

    /**
     * Move to the next child element
     */
    public function next() {

        if(! is_array($this->__children))
            return ($this->__reset = FALSE);

        return next($this->__children);

    }

    /**
     * Return the key of the current child element
     */
    public function key() {

        if(! is_array($this->__children))
            return ($this->__reset ? $this->__name : NULL);

        return key($this->__children);

    }

    /**
     * Test if the current child element is valid
     */
    public function valid() {

        if(! is_array($this->__children))
            return $this->__reset;

        return (current($this->__children) instanceof Element);

    }

    /**
     * Reset the internal pointer to the first child element.
     */
    public function rewind() {

        if(! is_array($this->__children))
            return ($this->__reset = TRUE);

        return reset($this->__children);

    }

}
<?php
/**
 * @file        Hazaar/View/Helper/Html.php
 *
 * @author      Jamie Carl jamie@hazaarlabs.com
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Basic HTML output
 *
 * @detail      This view helper provides basic HTML output such as custom block and inline elements as well as built-in
 *              methods for generating TITLE, STYLE, DIV, etc, tags.
 *
 * @since       1.0.0
 */
class Html extends \Hazaar\View\Helper {

    /**
     * @detail      Root method for returning an HTML block element.
     *
     * @since       1.0.0
     *
     * @param       string $type The block type such as DIV, SPAN, H1, etc.
     *
     * @param       mixed $content The content to display inside the block.   Can be a string, object or array of
     *                              objects.
     *
     * @param       array $params Optional parameters to pass to the block such as CLASS, ID, etc.
     *
     * @param              $close   boolean Optional argument to set whether the block is closed.  This is used by more
     *                              complex methods that need to work with the content a bit more before closing the
     *                              block.  Really only used internally.
     */
    public function block($type, $content = NULL, $params = array(), $close = TRUE) {

        return new \Hazaar\Html\Block($type, $content, $params, $close);

    }

    /**
     * $details     Root method for returning an HTML inline element.
     *
     * @since       1.0.0
     *
     * @param       string $type The inline type such as A, IMG, etc.
     *
     * @param       array $params Optional array of parameters to pass to the inline element such as CLASS, ID, etc.
     */
    public function inline($type, $params = array()) {

        return new \Hazaar\Html\Inline($type, $params);

    }

    /**
     * @detail      Generate a nice 'Powered By Hazaar!' panel.  This panel can be placed in your projects to show your
     * support
     *              for Hazaar!
     *
     * @since       1.0.0
     *
     * @param       string $class A class to apply to the panel.
     */

    public function poweredby($class = 'hazaar-powered-by') {

        $image = $this->html->img($this->application->url('hazaar/file/hazaarpowered.png'));

        return $this->a('http://www.hazaarmvc.com', $image, array(
            'target' => '_blank',
            'class'  => $class
        ));

    }

    /**
     * @detail      Returns an HTML Group object.  A group object is just a container for other objects and has no
     *              visible attributes.
     *
     * @return      string Rendered output from all child objects.
     */
    public function group($params = array()) {

        return new \Hazaar\Html\Group(func_get_args($params = array()));

    }

    /**
     * @detail      Defines a comment
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Comment
     */
    public function comment($content) {

        return new \Hazaar\Html\Comment($content);

    }

    /**
     * @detail      DESCRIPTION
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\A
     */
    public function doctype($html = TRUE, $level = 5, $strict = TRUE, $params = array()) {

        return new \Hazaar\Html\Doctype($html, $level, $strict, $params);

    }

    /**
     * @detail       Defines a hyperlink
     *
     * @since        1.2
     *
     * @return      \\Hazaar\\Html\\A
     */
    public function a($href = null, $content = NULL, $params = array()) {

        return new \Hazaar\Html\A($href, $content, $params);

    }

    /**
     * @detail      Defines an abbreviation
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Abbr
     */
    public function abbr($content, $params = array()) {

        return new \Hazaar\Html\Abbr($content, $params);

    }

    /**
     * @detail      Defines contact information for the author/owner of a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Address
     */
    public function address($content, $params = array()) {

        return new \Hazaar\Html\Address($content, $params);

    }

    /**
     * @detail      Defines an area inside an image-map
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Area
     */
    public function area($href, $coords, $params = array()) {

        return new \Hazaar\Html\Area($href, $coords, $params);

    }

    /**
     * @detail      Defines an article
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Article
     */
    public function article($content, $params = array()) {

        return new \Hazaar\Html\Article($content, $params);

    }

    /**
     * @detail       Defines content aside from the page content
     *
     * @since        1.2
     *
     * @return      \\Hazaar\\Html\\Aside
     */
    public function aside($content, $params = array()) {

        return new \Hazaar\Html\Aside($content, $params);

    }

    /**
     * @detail      Defines sound content
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Audio
     */
    public function audio($autoplay = FALSE, $controls = TRUE, $params = array()) {

        return new \Hazaar\Html\Audio($autoplay, $controls, $params);

    }

    /**
     * @detail      Defines bold text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\B
     */
    public function b($content = null, $params = array()) {

        return new \Hazaar\Html\B($content, $params);

    }

    /**
     * @detail      Specifies the base URL/target for all relative URLs in a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Base
     */
    public function base($href, $target = NULL, $params = array()) {

        return new \Hazaar\Html\Base($href, $target, $params);

    }

    /**
     * @detail      Isolates a part of text that might be formatted in a different direction from other text outside it
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Bdi
     */
    public function bdi($content, $params = array()) {

        return new \Hazaar\Html\Bdi($content, $params);

    }

    /**
     * @detail      Overrides the current text direction
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Bdo
     */
    public function bdo($content, $dir = NULL, $params = array()) {

        return new \Hazaar\Html\Bdo($content, $dir, $params);

    }

    /**
     * @detail      Defines a section that is quoted from another source
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Blockquote
     */
    public function blockquote($content, $params = array()) {

        return new \Hazaar\Html\Blockquote($content, $params);

    }

    /**
     * @detail      Defines the document's body
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Body
     */
    public function body($content, $params = array()) {

        return new \Hazaar\Html\Body($content, $params);

    }

    /**
     * @detail      Defines a single line break
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Br
     */
    public function br($params = array()) {

        return new \Hazaar\Html\Br($params);

    }

    /**
     * @detail      Defines a clickable button
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Button
     */
    public function button($label, $type = 'button', $params = array()) {

        return new \Hazaar\Html\Button($label, $type, $params);

    }

    /**
     * @detail      Used to draw graphics, on the fly, via scripting (usually JavaScript)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Canvas
     */
    public function canvas($name, $params = array()) {

        return new \Hazaar\Html\Canvas($name, $params);

    }

    /**
     * @detail      Defines a table caption
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Caption
     */
    public function caption($content, $align = NULL, $params = array()) {

        return new \Hazaar\Html\Caption($content, $align, $params);

    }

    /**
     * @detail      Defines the title of a work
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Cite
     */
    public function cite($content, $params = array()) {

        return new \Hazaar\Html\Cite($content, $params);

    }

    /**
     * @detail      Defines a piece of computer code
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Code
     */
    public function code($content, $params = array()) {

        return new \Hazaar\Html\Code($content, $params);

    }

    /**
     * @detail      Specifies column properties for each column within a colgroup element
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Col
     */
    public function col($params = array()) {

        return new \Hazaar\Html\Col($params);

    }

    /**
     * @detail      Specifies a group of one or more columns in a table for formatting
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Colgroup
     */
    public function colgroup($params = array()) {

        return new \Hazaar\Html\Colgroup($params);

    }

    /**
     * @detail      Specifies a list of pre-defined options for input controls
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Datalist
     */
    public function datalist($items = array(), $name = NULL, $params = array()) {

        return new \Hazaar\Html\Datalist($items, $name, $params);

    }

    /**
     * @detail      Defines a description of an item in a definition list
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Dd
     */
    public function dd($content, $params = array()) {

        return new \Hazaar\Html\Dd($content, $params);

    }

    /**
     * @detail      Defines text that has been deleted from a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Del
     */
    public function del($content, $params = array()) {

        return new \Hazaar\Html\Del($content, $params);

    }

    /**
     * @detail      Defines additional details that the user can view or hide
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Details
     */
    public function details($content, $summary = NULL, $params = array()) {

        return new \Hazaar\Html\Details($content, $summary, $params);

    }

    /**
     * @detail      Defines a definition term
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Dfn
     */
    public function dfn($content, $params = array()) {

        return new \Hazaar\Html\Dfn($content, $params);

    }

    /**
     * @detail      Defines a dialog box or window
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Dialog
     */
    public function dialog($content, $open = FALSE, $params = array()) {

        return new \Hazaar\Html\Dialog($content, $open, $params);

    }

    /**
     * @detail      Defines a section in a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Div
     */
    public function div($content = NULL, $params = array()) {

        return new \Hazaar\Html\Div($content, $params);

    }

    /**
     * @detail       Defines a definition list
     *
     * @since        1.2
     *
     * @return      \\Hazaar\\Html\\Dl
     */
    public function dl($items = array(), $params = array()) {

        return new \Hazaar\Html\Dl($items, $params);

    }

    /**
     * @detail      Defines a term (an item) in a definition list
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Dt
     */
    public function dt($content, $params = array()) {

        return new \Hazaar\Html\Dt($content, $params);

    }

    /**
     * @detail      Defines emphasized text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Em
     */
    public function em($content, $params = array()) {

        return new \Hazaar\Html\Em($content, $params);

    }

    /**
     * @detail      Defines a container for an external (non-HTML) application
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Embed
     */
    public function embed($src, $params = array()) {

        return new \Hazaar\Html\Embed($src, $params);

    }

    /**
     * @detail      Groups related elements in a form
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Fieldset
     */
    public function fieldset($content = NULL, $params = array()) {

        return new \Hazaar\Html\Fieldset($content, $params);

    }

    /**
     * @detail      Defines a caption for a figure element
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Figcaption
     */
    public function figcaption($content, $params = array()) {

        return new \Hazaar\Html\Figcaption($content, $params);

    }

    /**
     * @detail      Specifies self-contained content
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Figure
     */
    public function figure($image, $caption = NULL, $params = array()) {

        return new \Hazaar\Html\Figure($image, $caption, $params);

    }

    /**
     * @detail      Defines a footer for a document or section
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Footer
     */
    public function footer($content, $params = array()) {

        return new \Hazaar\Html\Footer($content, $params);

    }

    /**
     * @detail      Defines an HTML form for user input
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Form
     */
    public function form($content = null, $params = array()) {

        return new \Hazaar\Html\Form($content, $params);

    }

    /**
     * @detail      Defines HTML headings
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\H1
     */
    public function h1($content = null, $params = array()) {

        return new \Hazaar\Html\H1($content, $params);

    }

    /**
     * @detail      Defines HTML headings
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\H1
     */
    public function h2($content  = null, $params = array()) {

        return new \Hazaar\Html\H2($content, $params);

    }

    /**
     * @detail      Defines HTML headings
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\H3
     */
    public function h3($content = null, $params = array()) {

        return new \Hazaar\Html\H3($content, $params);

    }

    /**
     * @detail      Defines HTML headings
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\H4
     */
    public function h4($content = null, $params = array()) {

        return new \Hazaar\Html\H4($content, $params);

    }

    /**
     * @detail      Defines HTML headings
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\H5
     */
    public function h5($content = null, $params = array()) {

        return new \Hazaar\Html\H5($content, $params);

    }

    /**
     * @detail      Defines HTML headings
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\H6
     */
    public function h6($content = null, $params = array()) {

        return new \Hazaar\Html\H6($content, $params);

    }

    /**
     * @detail      Defines information about the document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Head
     */
    public function head($content = null, $params = array()) {

        return new \Hazaar\Html\Head($content, $params);

    }

    /**
     * @detail      Defines a header for a document or section
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Header
     */
    public function header($content, $params = array()) {

        return new \Hazaar\Html\Header($content, $params);

    }

    /**
     * @detail      Groups heading (h1 to h6) elements
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Hgroup
     */
    public function hgroup($content, $params = array()) {

        return new \Hazaar\Html\Hgroup($content, $params);

    }

    /**
     * @detail      Defines a thematic change in the content
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Hr
     */
    public function hr($params = array()) {

        return new \Hazaar\Html\Hr($params);

    }

    /**
     * @detail      Defines the root of an HTML document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Html
     */
    public function html($content = null, $params = array()) {

        return new \Hazaar\Html\Html($content, $params);

    }

    /**
     * @detail       Defines a part of text in an alternate voice or mood
     *
     * @since        1.2
     *
     * @return      \\Hazaar\\Html\\I
     */
    public function i($content = null, $params = array()) {

        return new \Hazaar\Html\I($content, $params);

    }

    /**
     * @detail      Defines an inline frame
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Iframe
     */
    public function iframe($content = null, $params = array()) {

        return new \Hazaar\Html\Iframe($content, $params);

    }

    /**
     * @detail      Defines an image
     *
     * @since       1.2
     *
     * @param       string $filename The filename of the image file to display.
     *
     * @param       string $text Alternate text to display if the image can be displayed.
     *
     * @param       Array $params Option array of parameters to pass to the tag renderer.
     *
     * @return      \\Hazaar\\Html\\Img
     */
    public function img($src, $alt = NULL, $params = array()) {

        return new \Hazaar\Html\Img($src, $alt, $params);

    }

    /**
     * @detail      Returns an image link with URL to the APPLICATION_ROOT/public/images directory.  This makes it
     *              incredibly easy to create links to images stored in the public directory.
     *
     * @param       string $filename The filename of the image file to display.
     *
     * @param       string $text Alternate text to display if the image can be displayed.
     *
     * @param       Array $params Option array of parameters to pass to the tag renderer.
     *
     * @return      \\Hazaar\\Html\\Img
     */
    public function image($filename, $text = NULL, $params = array()) {

        return $this->img($this->application->url('images', $filename), $text, $params);

    }

    /**
     * @detail      Defines an input control
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Input
     */
    public function input($type, $name, $value = NULL, $params = array()) {

        return new \Hazaar\Html\Input($type, $name, $value, $params);

    }

    /**
     * @detail      Defines a text that has been inserted into a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Ins
     */
    public function ins($content, $params = array()) {

        return new \Hazaar\Html\Ins($content, $params);

    }

    /**
     * @detail      Defines keyboard input
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Kbd
     */
    public function kbd($content, $params = array()) {

        return new \Hazaar\Html\Kbd($content, $params);

    }

    /**
     * @detail      Defines a key-pair generator field (for forms)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Keygen
     */
    public function keygen($name, $params = array()) {

        return new \Hazaar\Html\Keygen($name, $params);

    }

    /**
     * @detail      Defines a label for an input element
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Label
     */
    public function label($content, $for = NULL, $params = array()) {

        return new \Hazaar\Html\Label($content, $for, $params);

    }

    /**
     * @detail      Defines a caption for a fieldset,  figure, or details element
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Legend
     */
    public function legend($content, $params = array()) {

        return new \Hazaar\Html\Legend($content, $params);

    }

    /**
     * @detail      Defines a list item
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Li
     */
    public function li($content = NULL, $params = array()) {

        return new \Hazaar\Html\Li($content, $params);

    }

    /**
     * @detail      Defines the relationship between a document and an external resource (most used to link to style
     * sheets)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Link
     */
    public function link($href, $rel = NULL, $type = NULL, $params = array()) {

        if(! preg_match('/^http[s]?:\/\//', $href))
            $href = $this->view->application->url('style', $href);

        return new \Hazaar\Html\Link($href, $rel, $type, $params);

    }

    /**
     * @detail      Defines a client-side image-map
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Map
     */
    public function map($content = null, $name = null, $params = array()) {

        return new \Hazaar\Html\Map($content, $name, $params);

    }

    /**
     * @detail      Defines marked/highlighted text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Mark
     */
    public function mark($content, $params = array()) {

        return new \Hazaar\Html\Mark($content, $params);

    }

    /**
     * @detail      Defines metadata about an HTML document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Meta
     */
    public function meta($content, $name = NULL, $params = array()) {

        return new \Hazaar\Html\Meta($content, $name, $params);

    }

    /**
     * @detail      Defines a scalar measurement within a known range (a gauge)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Meter
     */
    public function meter($content, $value = NULL, $min = NULL, $max = NULL, $params = array()) {

        return new \Hazaar\Html\Meter($content, $value, $min, $max, $params);

    }

    /**
     * @detail      Defines navigation links
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Nav
     */
    public function nav($content = NULL, $params = array()) {

        return new \Hazaar\Html\Nav($content, $params);

    }

    /**
     * @detail      Defines an alternate content for users that do not support client-side scripts
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Noscript
     */
    public function noscript($content = NULL, $params = array()) {

        return new \Hazaar\Html\Noscript($content, $params);

    }

    /**
     * @detail       Defines an embedded object
     *
     * @since        1.2
     *
     * @return      \\Hazaar\\Html\\Object
     */
    public function object($data, $type = NULL, $params = array()) {

        return new \Hazaar\Html\Object($data, $type, $params);

    }

    /**
     * @detail      Defines an ordered list
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Ol
     */
    public function ol($items = NULL, $params = array()) {

        return new \Hazaar\Html\Ol($items, $params);

    }

    /**
     * @detail      Defines a group of related options in a drop-down list
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Optgroup
     */
    public function optgroup($label = null, $options = array(), $value = null, $params = array(), $use_options_as_index = true) {

        return new \Hazaar\Html\Optgroup($label, $options, $value, $params, $use_options_as_index);

    }

    /**
     * @detail      Defines an option in a drop-down list
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Option
     */
    public function option($label, $value = NULL, $params = array()) {

        return new \Hazaar\Html\Option($label, $value, $params);

    }

    /**
     * @detail      Defines the result of a calculation
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Output
     */
    public function output($name, $for, $params = array()) {

        return new \Hazaar\Html\Output($name, $for, $params);

    }

    /**
     * @detail       Defines a paragraph
     *
     * @since        1.2
     *
     * @return      \\Hazaar\\Html\\P
     */
    public function p($content = null, $params = array()) {

        return new \Hazaar\Html\P($content, $params);

    }

    /**
     * @detail      Defines a parameter for an object
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Param
     */
    public function param($name, $value, $params = array()) {

        return new \Hazaar\Html\Param($name, $value, $params);

    }

    /**
     * @detail      Defines preformatted text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Pre
     */
    public function pre($content = null, $params = array()) {

        return new \Hazaar\Html\Pre($content, $params);

    }

    /**
     * @detail      Represents the progress of a task
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Progress
     */
    public function progress($value, $max = 100, $params = array()) {

        return new \Hazaar\Html\Progress($value, $max, $params);

    }

    /**
     * @detail      Defines a short quotation
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Q
     */
    public function q($content = null, $params = array()) {

        return new \Hazaar\Html\Q($content, $params);

    }

    /**
     * @detail      Defines what to show in browsers that do not support ruby annotations
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Rp
     */
    public function rp($content, $params = array()) {

        return new \Hazaar\Html\Rp($content, $params);

    }

    /**
     * @detail      Defines an explanation/pronunciation of characters (for East Asian typography)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Rt
     */
    public function rt($content, $params = array()) {

        return new \Hazaar\Html\Rt($content, $params);

    }

    /**
     * @detail      Defines a ruby annotation (for East Asian typography)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Ruby
     */
    public function ruby($content, $params = array()) {

        return new \Hazaar\Html\Ruby($content, $params);

    }

    /**
     * @detail      Defines text that is no longer correct
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\S
     */
    public function s($content = null, $params = array()) {

        return new \Hazaar\Html\S($content, $params);

    }

    /**
     * @detail      Defines sample output from a computer program
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Samp
     */
    public function samp($content, $params = array()) {

        return new \Hazaar\Html\Samp($content, $params);

    }

    /**
     * @detail      Defines a client-side script
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Script
     */
    public function script($content = NULL, $type = NULL, $params = array()) {

        return new \Hazaar\Html\Script($content, $type, $params);

    }

    /**
     * @detail      Defines a section in a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Section
     */
    public function section($content = null, $params = array()) {

        return new \Hazaar\Html\Section($content, $params);

    }

    /**
     * @detail      Defines a drop-down list
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Select
     */
    public function select($name, $options = array(), $value = NULL, $params = array(), $use_options_index_as_value = true) {

        return new \Hazaar\Html\Select($name, $options, $value, $params, $use_options_index_as_value);

    }

    /**
     * @detail      Defines smaller text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Small
     */
    public function small($content = null, $params = array()) {

        return new \Hazaar\Html\Small($content, $params);

    }

    /**
     * @detail      Defines multiple media resources for media elements (video and audio)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Source
     */
    public function source($source, $type = null, $mime_prefix = null, $params = array()) {

        return new \Hazaar\Html\Source($source, $type, $mime_prefix, $params);

    }

    /**
     * @detail      Defines a section in a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Span
     */
    public function span($content = NULL, $params = array()) {

        return new \Hazaar\Html\Span($content, $params);

    }

    /**
     * @detail      Defines important text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Strong
     */
    public function strong($content, $params = array()) {

        return new \Hazaar\Html\Strong($content, $params);

    }

    /**
     * @detail      Defines style information for a document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Style
     */
    public function style($selector = NULL, $elements = array()) {

        return new \Hazaar\Html\Style($selector, $elements);

    }

    /**
     * @detail      Defines subscripted text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Sub
     */
    public function sub($content, $params = array()) {

        return new \Hazaar\Html\Sub($content, $params);

    }

    /**
     * @detail      Defines a visible heading for a details element
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Summary
     */
    public function summary($content, $params = array()) {

        return new \Hazaar\Html\Summary($content, $params);

    }

    /**
     * @detail      Defines superscripted text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Sup
     */
    public function sup($content, $params = array()) {

        return new \Hazaar\Html\Sup($content, $params);

    }

    /**
     * @detail      Defines a table
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Table
     */
    public function table($content = null, $params = array()) {

        return new \Hazaar\Html\Table($content, $params);

    }

    /**
     * @detail      Groups the body content in a table
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Tbody
     */
    public function tbody($content = null, $params = array()) {

        return new \Hazaar\Html\Tbody($content, $params);

    }

    /**
     * @detail      Defines a cell in a table
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Td
     */
    public function td($content = null, $params = array()) {

        return new \Hazaar\Html\Td($content, $params);

    }

    /**
     * @detail      Defines a multiline input control (text area)
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Textarea
     */
    public function textarea($value = NULL, $params = array()) {

        return new \Hazaar\Html\Textarea($value, $params);

    }

    /**
     * @detail      Groups the footer content in a table
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Tfoot
     */
    public function tfoot($content = null, $params = array()) {

        return new \Hazaar\Html\Tfoot($content, $params);

    }

    /**
     * @detail      Defines a header cell in a table
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Th
     */
    public function th($content = null, $params = array()) {

        return new \Hazaar\Html\Th($content, $params);

    }

    /**
     * @detail      Groups the header content in a table
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Thead
     */
    public function thead($content = null, $params = array()) {

        return new \Hazaar\Html\Thead($content, $params);

    }

    /**
     * @detail      Defines a date/time
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Time
     */
    public function time($params = array()) {

        return new \Hazaar\Html\Time($params);

    }

    /**
     * @detail      Defines a title for the document
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Title
     */
    public function title($title) {

        return new \Hazaar\Html\Title($title);

    }

    /**
     * @detail      Defines a row in a table
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Tr
     */
    public function tr($content = null, $params = array()) {

        return new \Hazaar\Html\Tr($content, $params);

    }

    /**
     * @detail      Defines text that should be stylistically different from normal text
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\U
     */
    public function u($content = null, $params = array()) {

        return new \Hazaar\Html\U($content, $params);

    }

    /**
     * @detail      Defines an unordered list
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Ul
     */
    public function ul($content = null, $params = array()) {

        return new \Hazaar\Html\Ul($content, $params);

    }

    /**
     * @detail      Defines a video or movie
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Video
     */
    public function video($autoplay = FALSE, $controls = FALSE, $params = array()) {

        return new \Hazaar\Html\Video($autoplay, $controls, $params);

    }

    /**
     * @detail      Defines a possible line-break
     *
     * @since       1.2
     *
     * @return      \\Hazaar\\Html\\Wbr
     */
    public function wbr($content, $params = array()) {

        return new \Hazaar\Html\Wbr($content, $params);

    }

}


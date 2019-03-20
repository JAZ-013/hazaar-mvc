<?php
/**
 * @file        Hazaar/View/Helper/Fontawesome.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Renders icons using the Font Awesome library.
 *
 * @detail      View helper to allow easier access to Font Awesome icons.
 *
 *              Font Awesome by Dave Gandy - [[http://fortawesome.github.io/Font-Awesome]]
 *
 *              For a list of available icons, see: [[http://fortawesome.github.io/Font-Awesome/icons]]
 *
 * @since       1.0.0
 */
class Fontawesome extends \Hazaar\View\Helper {

    private $css_include;

    public function import() {

        $this->requires('cdnjs');

        $this->requires('html');

    }

    /**
     * @detail      View helper initialisation method.  Includes the font-awesome stylesheet and HTML helper.
     *
     * @since       1.0.0
     */
    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $files = array();

        if($version = ake($args, 'version')){

            $ver = new \Hazaar\Version($version);

            if($ver->compareTo('4.7.0') <= 0)
                $files[] = 'css/font-awesome.css';

        }

        $this->cdnjs->load('font-awesome', ake($args, 'version'), $files);

    }

    /**
     * @detail      Displays an FA icon.  For a list of available icons, see:
     *              [[http://fortawesome.github.com/Font-Awesome/#icons-new]]
     *
     * @since       1.0.0
     *
     * @param       string $style The name of the icon to display.  This can be found on the FA website.  It is the name
     *              of the icon minus the 'fa-' prefix.  For example: fa-camera-retro would be defined as just
     *              camera-retro.
     *
     * @param       mixed $size The size to display the icon.  This can be either a numeric value specifying the number
     *              of pixels, or a string value of 'large', '2x', '3x', '4x' or '5x' to use the built-in FA size styles.
     *
     * @param       boolean $spin Enables the spin style for the icon. Defaults to false.
     *
     * @param       boolean $border Enables a border around the icon.
     *
     * @param       Array $args Optional additional arguments to pass to the HTML element.
     */
    public function icon($style, $size = NULL, $spin = FALSE, $border = FALSE, $args = array()) {

        if(is_array($style)){

            $span = new \Hazaar\Html\Span();

            foreach($style as $id => $icon){

                if(!is_array($icon))
                    $icon = array($icon);

                $i = $this->icon(ake($icon, 0))->addClass('fa-stack-' . ($id + 1) . 'x');

                if(ake($icon, 1) === true)
                    $i->addClass('fa-inverse');

                $span->add($i);

            }

            if($size)
                $span->addClass('fa-' . $size);

            return $span->addClass('fa-stack');

        }

        if($spin === TRUE)
            $style .= ' fa-spin';

        if($size) {

            if(is_numeric($size)) {

                $args['style'] .= 'font-size: ' . $size . 'px;';

            } else {

                $style .= ' fa-' . $size;

            }

        }

        if($border === TRUE)
            $style .= ' fa-border';

        $args['class'] = (isset($args['class']) ? $args['class'] . ' ' : NULL) . 'fa fa-' . $style;

        return $this->html->block('i', NULL, $args);

    }

    public function getIconList() {

        $list = NULL;

        $cache = new \Hazaar\Cache('file');

        if(!$content = $cache->get('fontawesome')){

            $content = file_get_contents($this->css_include);

            $cache->set('fontawesome', $content);

        }

        if(preg_match_all('/fa-([\w\-]+)\:before\s?\{/', $content, $matches))
            $list = $matches[1];

        sort($list);

        return $list;

    }

}


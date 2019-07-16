<?php
/**
 * @file        Hazaar/View/Helper/Bootstrap.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Bootstrap view helper
 *
 * @detail      This view helper provides some built-in output functionality for producing view content using the
 *              Bootstrap style and javascript library. These elements have a nice pleasing style.
 *
 *              For more information on Bootstrap, see: http://twitter.github.com/bootstrap
 *
 * @since       1.0.0
 */
class Bootstrap extends \Hazaar\View\Helper {

    public function import() {

        $this->requires('html');

        $this->requires('JQuery');

        $this->requires('cdnjs');

    }

    /**
     * @detail      Initialise the view helper and include the buttons.css file.  Adds a requirement for the HTML view
     *              helper.
     */
    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $version = ake($args, 'version');

        $files = array('js/bootstrap.min.js');

        if($theme = ake($this->args, 'theme'))
            $version = $this->cdnjs->load('bootswatch', $version, array($theme . '/bootstrap.min.css'));
        else
            $files[] = 'css/bootstrap.min.css';

        $version = $this->cdnjs->load('twitter-bootstrap', $version, $files);

        //Check if we are
        if($version->compareTo('4.0') >= 0)
            $this->cdnjs->load('popper.js', null, array('umd/popper.min.js'), 1);

    }

    /**
     * @detail      This method can be used to render pleasant looking buttons using style information from the Twitter
     *              Bootstrap project.  A number of different button styles are available.  This method is also 100%
     *              compatible with the Font Awesome helper so that you can use FA icons in your button labels.
     *
     *              p(notice notice-info). For information on what button styles are available, see:
     *              [[http://twitter.github.com/bootstrap/base-css.html#buttons]]
     *
     *              Here is an example using the Font Awesome helper to include an icon on the button.
     *
     *              <pre><code class="php">
     *              <div class="container">
     *
     *                  <?=$this->bootstrap->button($this->fontawesome->icon('cog') . ' Settings');
     *
     * ?>
     *
     *              </div>
     *              </code></pre>
     *
     *              p(notice notice-info). Take note of the space before the string label.  This is required to
     *              add a nice gap between the icon and the label.</div>
     *
     * @since       1.0.0
     *
     * @param       string $name  A unique name to give to the button.  This will be used in the ID attribute and can be
     *                            used in your jQuery selectors.
     *
     * @param       string $label The label to put on the button.
     *
     * @param       string $style Any extra style info for the button.  For example, 'success' will render a
     *                            'btn-success' button.
     *
     * @param       string $size  A size style to apply to the button.  Valid sizes are large, small, mini.
     *
     * @param       Array  $args  An array of optional arguments to pass to the HTML block element.
     */
    public function button($name, $label, $style = null, $size = null, $args = array()) {

        $class = 'btn';

        if($style)
            $class .= ' btn-' . $style;

        if($size) {

            $valid = array(
                'large',
                'small',
                'mini'
            );

            if(in_array($size, $valid)) {

                $class .= ' btn-' . $size;

            }

        }

        $args['id'] = $name;

        $args['class'] = $class;

        return $this->html->button($label, 'button', $args);

    }

    public function buttonGroup($buttons, $args = array()) {

        if(! is_array($buttons))
            return null;

        $btn_out = array();

        foreach($buttons as $id => $btn) {

            $btn_out[] = $this->button($id, $btn);

        }

        $args['class'] = 'btn-group';

        return $this->html->block('div', implode("\n", $btn_out), $args);
    }

}

<?php
/**
 * @file        Hazaar/View/Helper/JQuery.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

define('JQUERY_CURRENT_VER', '1.11.0');

class JQuery extends \Hazaar\View\Helper {

    private $jquery;

    public function import() {

        $this->jquery = \Hazaar\Html\jQuery::getInstance();

    }

    /**
     * @detail      Initialise the jQuery view helper.  This view helper includes the jQuery JavaScript library that is
     *              shipped with Hazaar.  Optionally you can specify a version and that version will be downloaded from
     *              the Google APIs hosted libraries.
     *
     * @since       1.0.0
     *
     * @param       \\Hazaar\\View $view The view the helper is being added to.
     *
     * @param       string $version (Optional) version of the jQuery library to use from the Google hosted libraries
     *              server.
     */
    public function init($view, $args = array()) {

        $settings = new \Hazaar\Map(array('noload' => FALSE), $args);

        if($settings['noload'] !== TRUE) {

            /**
             * Optionally we can set a version which will use the Google hosted library as we only ship the latest
             * version
             * with Hazaar.
             */
            if($settings->has('version')) {

                $view->requires('https://ajax.googleapis.com/ajax/libs/jquery/' . $settings->version . '/jquery.min.js');

            } else {

                $jquery = 'hazaar/js/jquery' . (defined('JQUERY_CURRENT_VER') ? '-' . JQUERY_CURRENT_VER : NULL) . '.min.js';

                $view->requires($this->application->url($jquery));

            }

            if($settings->has('ui') && $settings->ui === TRUE) {

                if($settings->has('ui-version')) {

                    $view->requires('https://ajax.googleapis.com/ajax/libs/jqueryui/' . $settings->get('ui-version') . '/jquery-ui.min.js');

                } else {

                    $view->requires($this->application->url('hazaar/js/jquery-ui.min.js'));

                }

            }

            $view->requires($this->application->url('hazaar/js/jquery-helper.js'));

        }

    }

    public function exec($code) {

        return $this->jquery->exec($code);

    }

    public function post() {

        return $this->jquery->post();

    }

}



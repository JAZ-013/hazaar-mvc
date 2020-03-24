<?php
/**
 * @file        Hazaar/View/Helper/Dropzone.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       A nice and simple Ajax file browser
 *
 * @detail      View helper to allow easier access to Hazaar's built-in file browser widget.
 *
 *              See [[Using The File Browser Widget]] for more information.
 *
 * @since       2.0.0
 */
class Filebrowser extends \Hazaar\View\Helper {

    private $options;

    public function import() {

        $this->requires('html');

        $this->requires('jQuery', array('ui' => TRUE));

        $this->options = new \Hazaar\Map(array(
            'connect'    => $this->application->url('media'),
            'stylesheet' => TRUE
        ));

    }

    /**
     * @detail      View helper initialisation method.
     *
     * @since       2.0.0
     */
    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $view->requires($this->application->url('hazaar', 'file/js/filebrowser.js'));

        $this->options->extend($args);

        if($this->options->stylesheet === true)
            $view->link($this->application->url('hazaar', 'file/css/filebrowser.css'));

    }

    public function get($name, $connect = NULL, $params = array()) {

        return new \Hazaar\View\Widgets\FileBrowser($name, $this->options->connect, $params);

    }

}

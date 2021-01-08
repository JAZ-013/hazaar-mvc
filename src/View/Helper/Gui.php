<?php
/**
 * @file        Hazaar/View/Helper/Gui.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * @brief       Hazaar Built-in GUI Tools
 *
 * @since       2.3.12
 */
class Gui extends \Hazaar\View\Helper {

    private $parser;

    public function import(){

        $this->requires('jQuery');

        $this->requires('Fontawesome');

    }

    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $view->requires($this->application->url('hazaar', 'file/js/popup.js'));

        //$view->link($this->application->url('hazaar', 'file/css/popup.css'));

    }

    public function popup($content, $args = array()) {

        $id = 'popup_' . uniqid();

        $this->jquery->exec("$('#$id').popup();");

        return $this->html->div($content)->id($id);

    }

}


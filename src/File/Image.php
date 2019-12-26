<?php

namespace Hazaar\File;

class Image extends \Hazaar\File {

    private $renderer;

    function __construct($filename = NULL, $quality = NULL, $backend = NULL, $renderer = 'default') {

        parent::__construct($filename, $backend);

        $this->renderer = $this->get_renderer($renderer, $quality);

    }

    private function get_renderer($renderer, $quality) {

        switch(strtolower($renderer)) {

            case 'imagick':
            case 'default':

                if(in_array('imagick', get_loaded_extensions()))
                    return new Renderer\Imagick($quality);

            case 'gd':
            default:

                return new Renderer\GD($quality);

        }

    }

    public function set_contents($bytes) {

        $this->renderer->load($bytes, $this->mime_content_type());

    }

    public function get_contents($offset = -1, $maxlen = NULL, $allow_compress = TRUE) {

        if(! $this->renderer->loaded())
            $this->renderer->load(parent::get_contents($offset, $maxlen, $allow_compress));

        return $this->renderer->read();

    }

    private function checkLoaded() {

        if(! $this->renderer->loaded())
            $this->renderer->load(parent::get_contents());

    }

    public function quality($quality = NULL) {

        if($quality === null)
            return false;

        $this->checkLoaded();

        return $this->renderer->quality($quality);

    }

    public function width() {

        $this->checkLoaded();

        return $this->renderer->width();

    }

    public function height() {

        $this->checkLoaded();

        return $this->renderer->height();

    }

    public function has_contents(){

        return $this->renderer->width() > 0;

    }

    public function __call($func, $params) {

        if(! $this->has_contents())
            return FALSE;

        if(method_exists($this->renderer, $func)) {

            $this->checkLoaded();

            return call_user_func_array(array($this->renderer, $func), $params);

        }

        return FALSE;

    }

}

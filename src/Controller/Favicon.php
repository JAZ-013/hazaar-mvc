<?php

namespace Hazaar\Controller;

class Favicon extends \Hazaar\Controller {

    protected $request;

    public $use_metrics = false;

    public function __run() {

        if($this->application->config->app->has('favicon'))
            $filename = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_PUBLIC, $this->application->config->app->favicon);

        if(! isset($filename))
            $filename = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_SUPPORT, 'favicon.png');

        $max_width = 16;

        $max_height = 16;

        $response = new Response\Image($filename);

        $response->setController($this);

        $response->setUnmodified($this->request->getHeader('If-Modified-Since'));;

        if($response->width() > $max_width || $response->height() > $max_height)
            $response->resize($max_width, $max_height);

        return $response;

    }

}

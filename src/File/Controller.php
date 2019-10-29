<?php

namespace Hazaar\File;

class Controller extends \Hazaar\Controller\Basic {

    private $path;

    /**
     * Sets the support path to use to search for files.
     *
     * If this is not set, then the application configured support paths are used.
     *
     * @param mixed $path
     */
    public function setPath($path){

        return $this->path = realpath($path);

    }

    /**
     * Directly access a file stored in the Hazaar libs directory.
     *
     * This is used for accessing files in the libs directory, such as internal built-in JavaScript,
     * CSS, views and other files that are shipped as part of the core Hazaar MVC package.
     *
     * @param string $action
     * @throws Exception\InternalFileNotFound
     * @throws \Exception
     * @return \Hazaar\Controller\Response\File
     */
    public function __default($controller, $action) {

        $response = NULL;

        $target = ($path = $this->request->getPath()) ? $action . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path) : $action;

        if($this->path)
            $filename = $this->path . DIRECTORY_SEPARATOR . $target;
        else
            $filename = \Hazaar\Loader::getInstance()->getFilePath(FILE_PATH_SUPPORT, $target);

        if(!file_exists($filename))
            throw new Exception\InternalFileNotFound($target);

        $source = new \Hazaar\File($filename);

        $response = null;

        switch($source->mime_content_type()){
            case 'text/css':

                $response = new \Hazaar\Controller\Response\Style($source);

                break;

            case 'application/javascript':

                $response = new \Hazaar\Controller\Response\Javascript($source);

                break;

            default:

                $response = new \Hazaar\Controller\Response\File($source);

                break;

        }

        if(!$response instanceof \Hazaar\Controller\Response\File)
            throw new \Hazaar\Exception('An error ocurred constructing a response object.');

        $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        return $response;

    }

}

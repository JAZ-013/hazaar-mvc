<?php
/**
 * @file        Controller/Script.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Basic controller class
 *
 * @detail      This controller does basic stuff
 */
class Script extends \Hazaar\Controller {

    protected $request;

    private $source;

    private $filename;

    public $use_metrics = false;

    public function __initialize(\Hazaar\Application\Request $request) {

        parent::__initialize($request);

        $this->filename = $request->getPath();

        $this->source = $this->application->loader->getFilePath(FILE_PATH_VIEW, 'scripts/' . $this->filename);

    }

    public function __run() {

        if(!$this->source)
            throw new \Hazaar\Exception\FileNotFound($this->filename);

        $file = new \Hazaar\File($this->source);

        $response = new Response\Javascript($file);

        $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        return $response;

    }

}

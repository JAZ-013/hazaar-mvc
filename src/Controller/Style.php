<?php
/**
 * @file        Controller/Style.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Controller;

/**
 * @brief       Basic controller class
 *
 * @detail      THis controller does basic stuff
 */
class Style extends \Hazaar\Controller {

    private $source;

    private $filename;

    public function __initialize(\Hazaar\Application\Request $request) {

        $this->filename = $this->application->loader->getFilePath(FILE_PATH_VIEW)
            . DIRECTORY_SEPARATOR . 'styles' . DIRECTORY_SEPARATOR . $request->getPath();

        $this->source = new \Hazaar\File($this->filename);

    }

    public function __run() {

        if (!$this->source->exists())
            throw new \Hazaar\Exception\FileNotFound($this->filename);

        $mime_type = $this->source->mime_content_type();

        if(substr($mime_type, 0, strpos($mime_type, '/')) === 'image'){

            $response = new Response\Image($this->source, null, null, $this->application->config->app->get('responseImageCache'));

            if($response->setUnmodified($this->request->getHeader('If-Modified-Since')) === false){

                $params = $this->request->getParams();

                if(!(is_array($params) && count($params) > 0) || $response->checkCacheFile($params))
                    return $response;

                if($quality = ake($params, 'quality'))
                    $response->quality(intval($quality));

                $xwidth = intval(ake($params, 'xwidth'));

                $xheight = intval(ake($params, 'xheight'));

                if($xwidth > 0 || $xheight > 0)
                    $response->expand($xwidth, $xheight, ake($params, 'align'), ake($params, 'xoffsettop'), ake($params, 'xoffsetleft'));

                $width = intval(ake($params, 'width'));

                $height = intval(ake($params, 'height'));

                if($width > 0 || $height > 0) {

                    $align = $this->request->get('align', (boolify(ake($params, 'center', 'false')) ? 'center' : NULL));

                    $response->resize($width, $height, boolify(ake($params, 'crop', FALSE)), $align, boolify(ake($params, 'aspect', TRUE)), ! boolify(ake($params, 'enlarge')), ake($params, 'ratio'), intval(ake($params, 'offsettop')), intval(ake($params, 'offsetleft')));

                }

                if($filter = ake($params, 'filter'))
                    $response->filter($filter);

            }

        }else{

            $response = new Response\Style($this->source);

            $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

        }

        return $response;

    }

}


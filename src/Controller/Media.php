<?php

namespace Hazaar\Controller;

class Media extends \Hazaar\Controller\Action {

    private $allowPreview   = array(
        '/^image\//'
    );

    private $cachableParams = array(
        'width', 'height', 'format', 'quality', 'format', 'xwidth', 'xheight', 'filter'
    );

    private $auth;

    private $connector;

    private $global_cache = false;

    private $config;

    private $file;

    public function init($request) {

        $this->application->getResponseType('json');

        if(class_exists('\Hazaar\Auth\Helper'))
            $this->auth = new \Hazaar\Auth\Helper();

        $this->connector = new \Hazaar\File\BrowserConnector($this->url(), $this->allowPreview);

        $this->connector->setProgressCallback(array($this, 'progress'));

        if(($this->config = $this->loadConfig()) === false)
            throw new \Hazaar\Exception('Media controller has not been configured!');

        if($this->config->disabled === true)
            return;

        if($this->config->global->has('cache'))
            $this->global_cache = boolify($this->config->global['cache']);

        $this->loadSources($this->config, $this->connector);

    }

    private function loadConfig() {

        $defaults = array('global' =>  \Hazaar\File\Manager::$default_config);

        $config = new \Hazaar\Application\Config('media', APPLICATION_ENV, $defaults);

        if(!$config->loaded())
            return false;

        foreach($config as $source)
            $source->enhance(\Hazaar\File\Manager::$default_config);

        return $config;

    }

    private function loadSources($config, $connector, $names = array()) {

        if(! is_array($names))
            $names = array($names);

        foreach($config as $id => $source) {

            if($id == 'global' || !boolify($source->enabled) || (count($names) > 0 && ! in_array($id, $names)))
                continue;

            if($source->has('type')) {

                $manager = new \Hazaar\File\Manager($source->type, $source->get('options'), $id);

                if($manager->authorise())
                    $connector->addSource($id, $manager, $source->get('name'));

            }

        }

    }

    public function authorise($source) {

        $result = $this->connector->authorise($source, (string)$this->url(NULL, 'authorise/' . $source));

        if($result) {

            $sess = new \Hazaar\Session();

            $url = $sess->redirect_uri;

        } else {

            $url = (string)new \Hazaar\Application\Url();

        }

        $this->redirect($url);

    }

    public function progress($operation, $data) {

        if($data instanceof \Hazaar\File) {

            $data = array(
                'kind'     => $data->type(),
                'name'     => $data->basename(),
                'path'     => $data->fullpath(),
                'modified' => $data->mtime(),
                'size'     => $data->size(),
                'mime'     => (($data->type() == 'file') ? $data->mime_content_type() : 'dir'),
                'read'     => $data->is_readable(),
                'write'    => $data->is_writable()
            );

        }

        $this->stream(array('progress' => array('operation' => $operation, 'data' => $data)));

    }

    public function __default($controller, $source_name = null) {

        if($this->request->has('cmd'))
            return $this->command($this->request->get('cmd'), $this->connector);

        //Check for global authentication
        if($this->config->global->has('auth')
            && $this->config->global->auth === true
            && $this->config->global->allow['read'] !== true){

            if(!($this->auth && $this->auth->authenticated()))
                throw new \Hazaar\Exception('Unauthorised!', 403);

        }

        $source = $this->connector->source($source_name);

        if(! $source)
            throw new \Hazaar\Exception("Media source '$source_name' is unknown!", 404);

        if(!$this->config->has($source->name))
            throw new \Hazaar\Exception("Config missing for loaded source!  What the hell!?");

        //Check for source specific authentication
        if($this->config[$source->name]->has('auth')
            && $this->config[$source->name]->auth === true
            && $this->config[$source->name]->public !== true){

            if(!($this->auth && $this->auth->authenticated()))
                throw new \Hazaar\Exception('Unauthorised!', 403);

        }

        $target = $this->request->getPath();

        $this->file = $source->get($target);

        if(! $this->file->exists())
            throw new \Hazaar\Exception('File not found!', 404);

        $params = $this->request->getParams();

        /*
         * Run the media bootstrap file.
         *
         * The media bootstrap file can be used to run custom code when the media controller is
         * being used.  Normally the media controller operates independently of the rest of the
         * application.  This allows application defined code to be executed to either modify
         * or restrict access to the controller.
         */
        $media_file = APPLICATION_PATH . DIRECTORY_SEPARATOR . ake($this->application->config->app->files, 'media', 'media.php');

        if(file_exists($media_file)){

            $bootstrap = include($media_file);

            if($bootstrap === FALSE)
                throw new \Hazaar\Exception('Access to the requested file is restricted.', 401);

        }

        /*
         * Check if we can offload this preview to the content provider.
         *
         * If a cachable action is requested (ie: resize) then we can use the preview URI.
         *
         * If no actions are requested and the file has a direct URI then redirect to that.
         */
        if(count(array_intersect(array_keys($params), $this->cachableParams)) > 0) {

            if($preview_uri = $this->file->preview_uri($params))
                $this->redirect($preview_uri);

        } else if($direct_uri = $this->file->direct_uri()) {

            $direct_uri = new \Hazaar\Http\Uri($direct_uri, $this->request->getParams());

            $this->redirect($direct_uri);

        }

        if($this->file->is_dir()){

            if($this->config->global->allow['dir'] !== true)
                throw new \Hazaar\Exception('Directory listings are currently disabled.', 403);

            $response = new \Hazaar\Controller\Response\View('@media/dir');

            $response->source = $source->getOption('name');

            $response->path =  $this->file->fullpath();

            $response->vpath = '/' . $source_name . ($target ? '/' . $target : '');

            $response->root = ($this->file->fullpath() === '/');

            $dir = $this->file->dir();

            $response->dir = $this->file->dir();

        }else{

            $response = new \Hazaar\Controller\Response\File($this->file);

            if(preg_match_array($this->allowPreview, $this->file->mime_content_type()) > 0)
                $response = new \Hazaar\Controller\Response\Image($this->file);

            if($this->request->has('download') && boolify($this->request->download))
                $response->setDownloadable(TRUE);

            if(count(array_intersect(array_keys($params), $this->cachableParams)) > 0) {

                $cache_dir = NULL;

                $cache = ($this->config[$source->name]->has('cache') ? $this->config[$source->name]->cache : $this->global_cache);

                if($cache = boolify($this->request->get('cache', $cache))) {

                    try {

                        $cache_dir = $this->application->runtimePath('imagecache', TRUE);

                    }
                    catch(Exception $e) {

                        $cache = FALSE;

                    }

                }

                $hash = md5($this->file->fullpath() . '-' . json_encode($params));

                $cache_file = $cache_file = $cache_dir . '/' . $hash;

                /**
                 * Check the cache first for an image with these same request params.
                 */
                if($cache && file_exists($cache_file) && filesize($cache_file) > 0) {

                    $response->setLastModified(filemtime($cache_file));

                    //If the file has been modified, setUnmodified will return false because a 304 header is not set so we must load the content
                    if($response->setUnmodified($this->request->getHeader('If-Modified-Since')) === false){

                        $response->setHeader('X-Media-Cached', 'true');

                        $response->setContent(file_get_contents($cache_file));

                        $response->setContentType(file_get_contents($cache_file . '.info'));

                    }

                } else {

                    $params['ratio'] = ($this->request->has('ratio') ? $this->request->ratio : NULL);

                    if($this->request->has('format'))
                        $response->setFormat($this->request->format);

                    if($thumbnail = $this->file->thumbnail($params)) {

                        $response->setContent($thumbnail);

                    } else {

                        switch($this->file->mime_content_type()) {
                            case 'application/pdf':
                            case 'image/svg+xml':

                                if(! in_array('imagick', get_loaded_extensions()))
                                    throw new \Hazaar\Exception('Not supported', 406);

                                $temp_dir = $this->application->runtimePath('temp', TRUE);

                                $temp_file = $temp_dir . '/' . $hash;

                                file_put_contents($temp_file, $this->file->get_contents());

                                $pdf = new \Imagick($temp_file . '[0]');

                                $pdf->setResolution(300, 300);

                                $pdf->setImageFormat('png');

                                $response->setContent($pdf->getImageBlob());

                                unlink($temp_file);

                                break;

                            default:

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

                                break;

                        }

                    }

                    if($cache && is_writable($cache_dir)) {

                        file_put_contents($cache_file, $response->getContent());

                        file_put_contents($cache_file . '.info', $response->getContentType());

                    }

                    $response->setLastModified(time());

                }

            }else{

                $response->setUnmodified($this->request->getHeader('If-Modified-Since'));

            }

        }

        return $response;

    }

    private function command($cmd, $connector) {

        //Check for global command authentication
        if($this->config->global->has('auth')
            && $this->config->global->auth === true
            && $this->config->global->allow['cmd'] !== true){

            if(!($this->auth && $this->auth->authenticated()))
                throw new \Hazaar\Exception('Unauthorised!', 403);

        }

        if($cmd == 'authorise') {

            if(intval($_SERVER['REDIRECT_STATUS']) !== 200)
                throw new \Hazaar\Exception('Authorisation process failed!');

            $sess = new \Hazaar\Session();

            $sess->redirect_uri = $_SERVER['HTTP_REFERER'];

            return $connector->authorise($this->request->source, $this->url(NULL, 'authorise/' . $this->request->source));

        } else {

            if(($auth = $connector->authorised()) !== TRUE)
                $this->stream(array('auth' => $auth));

        }

        $reflection = new \ReflectionClass($connector);

        if(! $reflection->hasMethod($cmd))
            throw new \Hazaar\Exception('Bad request');

        unset($this->request->cmd);

        $method = $reflection->getMethod($cmd);

        $params = $method->getParameters();

        $args = array();

        $rqParams = $this->request->getParams();

        if(count($_FILES) > 0) {

            $files = new \Hazaar\File\Upload();

            foreach($files->keys() as $key)
                $rqParams[$key] = $files->get($key);

        }

        foreach($params as $param)
            $args[$param->getPosition()] = ake($rqParams, $param->getName(), ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : NULL));

        ksort($args);

        $response = $method->invokeArgs($connector, $args);

        if(strtolower($this->request->getHeader('X-Request-Type')) == 'stream')
            return $this->stream($response);

        return $response;

    }
}

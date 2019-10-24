<?php

namespace Hazaar\File\Backend;

class WebDAV extends \Hazaar\Http\WebDAV implements _Interface {

    public  $separator  = '/';
    
    private $options;

    private $cache;

    private $meta = array();

    static public function label(){

        return "WebDAV";

    }

    public function __construct($options) {

        $this->options = new \Hazaar\Map(array(
            'cache_backend' => 'file',
            'cache_meta'    => TRUE
        ), $options);

        if(! $this->options->has('baseuri'))
            throw new \Hazaar\Exception('WebDAV file browser backend requires a URL!');

        $this->cache = new \Hazaar\Cache($this->options['cache_backend'], array('use_pragma' => FALSE, 'namespace' => 'webdav_' . $this->options->baseuri . '_' . $this->options->username));

        if($this->options->get('cache_meta', FALSE)) {

            if(($meta = $this->cache->load('meta')) !== FALSE)
                $this->meta = $meta;

        }

        parent::__construct($this->options->toArray());

    }

    public function __destruct() {

        if($this->options->get('cache_meta', FALSE))
            $this->cache->save('meta', $this->meta);

    }

    public function refresh($reset = TRUE) {

        return TRUE;

    }

    private function updateMeta($path) {

        if(!($meta = $this->propfind($path)))
            return false;

        $meta = array_merge($this->meta, $meta);

        foreach($meta as $name => $info) {

            $name = '/' . trim($name, '/');

            $info['scanned'] = ($name == $path);

            $this->meta[$name] = $info;

        }

        return true;

    }

    /*
     * Metadata Operations
     */
    public function scandir($path, $regex_filter = NULL, $recursive = FALSE) {

        $path = '/' . trim($path, '/');

        if(! array_key_exists($path, $this->meta) || $this->meta[$path]['scanned'] == FALSE)
            $this->updateMeta($path);

        if(! ($pathMeta = ake($this->meta, $path)))
            return FALSE;

        if(! array_key_exists('collection', $pathMeta['resourcetype']))
            return FALSE;

        $list = array();

        foreach($this->meta as $name => $item) {

            if($name == '/' || pathinfo($name, PATHINFO_DIRNAME) !== $path)
                continue;

            $list[] = basename($name);

        }

        return $list;

    }

    public function info($path) {

        $path = '/' . trim($path, '/');

        if($meta = ake($this->meta, $path))
            return $meta;

        if(!$this->updateMeta($path))
            return null;

        return ake($this->meta, $path);

    }

    public function search($query, $include_deleted = FALSE) {

        dir('not done yet!');

        $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/search/auto/', 'POST');

        $request->query = $query;

        if($this->options->has('file_limit'))
            $request->file_limit = $this->options['file_limit'];

        $request->include_deleted = $include_deleted;

        if(! ($response = $this->sendRequest($request)))
            return FALSE;

        return $response;

    }

    //Check if file/path exists
    public function exists($path) {

        if(($info = $this->info($path)) !== FALSE)
            return TRUE;

        return FALSE;

    }

    public function realpath($path) {

        return $path;

    }

    public function is_readable($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        return in_array('R', str_split($info['permissions']));

    }

    public function is_writable($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        return in_array('W', str_split($info['permissions']));

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        if(is_array($info['resourcetype']) && array_key_exists('collection', $info['resourcetype']))
            return TRUE;

        return FALSE;

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        var_dump(__METHOD__);

        exit;

        return FALSE;

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        return ! $this->is_dir($path);

    }

    //Returns the file type
    public function filetype($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        return (is_array($info['resourcetype']) && array_key_exists('collection', $info['resourcetype']) ? 'dir' : 'file');

    }

    //Returns the file modification time
    public function filectime($path) {

        if(! ($info = $this->info($path)))
            return false;

        return strtotime($info['getcreated']);

    }

    //Returns the file modification time
    public function filemtime($path) {

        if(! ($info = $this->info($path)))
            return false;

        return strtotime($info['getlastmodified']);

    }

    public function touch($path){

        return false;

    }

    //Returns the file modification time
    public function fileatime($path) {

        return false;

    }

    public function filesize($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        if($this->is_dir($path))
            return intval($info['size']);

        return intval($info['getcontentlength']);

    }

    public function fileperms($path) {

        var_dump(__METHOD__);

        exit;

        return 0666;

    }

    public function chmod($path, $mode) {

        var_dump(__METHOD__);

        exit;

        return TRUE;

    }

    public function chown($path, $user) {

        var_dump(__METHOD__);

        exit;

        return TRUE;

    }

    public function chgrp($path, $group) {

        var_dump(__METHOD__);

        exit;

        return TRUE;

    }

    public function unlink($path) {

        var_dump(__METHOD__);

        exit;

        $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/fileops/delete', 'POST');

        $request->root = 'auto';

        $request->path = $path;

        if(! ($response = $this->sendRequest($request)))
            return FALSE;

        if($response->is_deleted) {

            $key = strtolower($response->path);

            if(array_key_exists($key, $this->meta))
                unset($this->meta[$key]);

            $this->clear_meta($response->path);

            return TRUE;

        }

        return FALSE;

    }

    public function mime_content_type($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        return $info['getcontenttype'];

    }

    public function md5Checksum($path) {

        var_dump(__METHOD__);

        exit;

        return md5($this->read($path));

    }

    public function thumbnail($path, $params = array()) {

        return FALSE;

        var_dump(__METHOD__);

        exit;

        if(! ($info = $this->info($path)))
            return NULL;

        if($info['thumb_exists']) {

            $size = 'l';

            if($width < 32 && $height < 32)
                $size = 'xs';
            elseif($width < 64 && $height < 64)
                $size = 's';
            elseif($width < 128 && $height < 128)
                $size = 'm';
            elseif($width < 640 && $height < 480)
                $size = 'l';
            elseif($width < 1024 && $height < 768)
                $size = 'xl';

            $request = new \Hazaar\Http\Request('https://api-content.dropbox.com/1/thumbnails/auto' . $path, 'GET');

            $request->format = $format;

            $request->size = $size;

            $response = $this->sendRequest($request, FALSE);

            $image = new \Hazaar\File\Image($path, NULL, $this);

            $image->set_contents($response);

            $image->resize($width, $height, TRUE, TRUE, FALSE, TRUE);

            return $image->get_contents();

        }

        return FALSE;

    }

    /*
     * File Operations
     */
    public function mkdir($path) {

        var_dump(__METHOD__);

        exit;

        $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/fileops/create_folder', 'POST');

        $request->root = 'auto';

        $request->path = $path;

        if(! ($response = $this->sendRequest($request)))
            return FALSE;

        if(boolify($response->is_dir)) {

            $this->meta[strtolower($response->path)] = $response->toArray();

            return TRUE;

        }

        return FALSE;

    }

    public function rmdir($path, $recurse = false) {

        var_dump(__METHOD__);

        exit;

        return $this->unlink($path);

    }

    public function copy($src, $dst, $recursive = FALSE) {

        var_dump(__METHOD__);

        exit;

        if($this->is_file($dst))
            return FALSE;

        $dst = rtrim($dst, '/') . '/' . basename($src);

        if($this->exists($dst))
            return FALSE;

        $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/fileops/copy', 'POST');

        $request->root = 'auto';

        $request->from_path = $src;

        $request->to_path = $dst;

        if(! ($response = $this->sendRequest($request)))
            return FALSE;

        $this->meta[strtolower($response->path)] = $response->toArray();

        $key = $this->options['app_key'] . '::' . strtolower($src);

        if($meta = $this->cache->load($key))
            $this->cache->save($this->options['app_key'] . '::' . strtolower($response->path), $meta);

        return TRUE;

    }

    public function link($src, $dst) {

        var_dump(__METHOD__);

        exit;

        return FALSE;

    }

    public function move($src, $dst) {

        var_dump(__METHOD__);

        exit;

        if($this->is_file($dst))
            return FALSE;

        $dst = rtrim($dst, '/') . '/' . basename($src);

        if($this->exists($dst))
            return FALSE;

        $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/fileops/move', 'POST');

        $request->root = 'auto';

        $request->from_path = $src;

        $request->to_path = $dst;

        if(! ($response = $this->sendRequest($request)))
            return FALSE;

        $this->meta[strtolower($response->path)] = $response->toArray();

        $key = $this->options['app_key'] . '::' . strtolower($src);

        if($meta = $this->cache->load($key)) {

            $this->cache->save($this->options['app_key'] . '::' . strtolower($response->path), $meta);

            $this->cache->remove($key);

        }

        return TRUE;

    }

    /*
     * Access operations
     */
    public function read($path, $offset = -1, $maxlen = NULL) {

        $response = $this->get($this->getAbsoluteUrl($path), 10, $offset, $maxlen);

        if($response->status !== 200)
            return FALSE;

        return $response->body;

    }

    public function write($path, $data, $content_type, $overwrite = FALSE) {

        var_dump(__METHOD__);

        exit;

        $request = new \Hazaar\Http\Request('https://api-content.dropbox.com/1/files_put/auto' . $path, 'POST');

        $request->setHeader('Content-Type', $content_type);

        if($overwrite)
            $request->overwrite = TRUE;

        $request->body = $data;

        if(! ($response = $this->sendRequest($request)))
            return FALSE;

        $this->meta[strtolower($response->path)] = $response->toArray();

        return TRUE;

    }

    public function upload($path, $file, $overwrite = TRUE) {

        if(! (($srcFile = ake($file, 'tmp_name')) && $filetype = ake($file, 'type')))
            return FALSE;

        $fullPath = rtrim($path, '/') . '/' . $file['name'];

        return $this->write($fullPath, file_get_contents($srcFile), $filetype, $overwrite = FALSE);

    }

    public function get_meta($path, $key = NULL) {

        var_dump(__METHOD__);

        exit;

        if($meta = $this->cache->load($this->options['app_key'] . '::' . strtolower($path)))
            return ake($meta, $key);

        return NULL;

    }

    public function set_meta($path, $values) {

        var_dump(__METHOD__);

        exit;

        if(! ($meta = $this->cache->load($this->options['app_key'] . '::' . strtolower($path))))
            $meta = array();

        $meta[$key] = $value;

        $this->cache->save($this->options['app_key'] . '::' . strtolower($path), $meta);

        return TRUE;

    }

    private function clear_meta($path) {

        var_dump(__METHOD__);

        exit;

        $this->cache->remove($this->options['app_key'] . '::' . strtolower($path));

        return TRUE;

    }

    public function preview_uri($path) {

        return FALSE;

    }

    public function direct_uri($path) {

        return FALSE;

    }

}

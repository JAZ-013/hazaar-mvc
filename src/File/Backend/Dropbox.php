<?php

namespace Hazaar\File\Backend;

class Dropbox extends \Hazaar\Http\Client implements _Interface {

    public  $separator  = '/';
    
    private $options;

    private $cache;

    private $meta = array();

    private $cursor;

    static public function label(){

        return 'Dropbox';

    }
    
    public function __construct($options) {

        parent::__construct();

        $this->options = new \Hazaar\Map(array(
            'oauth2_method' => 'POST',
            'oauth_version' => '2.0',
            'file_limit'    => 1000,
            'cache_backend' => 'file',
            'oauth2'        => array('access_token' => NULL)
        ), $options);

        if(! ($this->options->has('app_key') && $this->options->has('app_secret')))
            throw new Exception\DropboxError('Dropbox filesystem backend requires both app_key and app_secret.');

        $this->cache = new \Hazaar\Cache($this->options['cache_backend'], array('use_pragma' => FALSE, 'namespace' => 'dropbox_' . $this->options->app_key));

        if(($oauth2 = $this->cache->get('oauth2_data')))
            $this->options['oauth2'] = $oauth2;

        if(($cursor = $this->cache->get('cursor')) !== FALSE)
            $this->cursor = $cursor;

        if(($meta = $this->cache->get('meta')) !== FALSE)
            $this->meta = $meta;

    }

    public function __destruct() {

        if($this->cache instanceof \Hazaar\Cache) {

            $this->cache->set('meta', $this->meta);

            $this->cache->set('cursor', $this->cursor);

        }

    }

    public function authorise($redirect_uri = NULL) {

        if(($code = ake($_REQUEST, 'code')) && ($state = ake($_REQUEST, 'state'))) {

            if($state != $this->cache->pull('oauth2_state'))
                throw new \Hazaar\Exception('Bad state!');

            $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/oauth2/token', $this->options['oauth2_method']);

            $request->populate(array(
                                   'code'          => $code,
                                   'grant_type'    => 'authorization_code',
                                   'client_id'     => $this->options['app_key'],
                                   'client_secret' => $this->options['app_secret'],
                                   'redirect_uri'  => $redirect_uri
                               ));

            $response = $this->send($request);

            if($response->status !== 200)
                return FALSE;

            if($auth = json_decode($response->body, TRUE)) {

                $this->cache->set('oauth2_data', $auth);

                return TRUE;

            }

        }

        return $this->authorised();

    }

    public function authorised() {

        return ($this->options->has('oauth2') && $this->options['oauth2']['access_token'] !== NULL);

    }

    public function buildAuthURL($redirect_uri) {

        $state = md5(uniqid());

        $this->cache->set('oauth2_state', $state);

        $params = array(
            'response_type=code',
            'client_id=' . $this->options['app_key'],
            'redirect_uri=' . $redirect_uri,
            'state=' . $state
        );

        return 'https://www.dropbox.com/1/oauth2/authorize?' . implode('&', $params);

    }

    private function sendRequest(\Hazaar\Http\Request $request, $is_meta = TRUE) {

        $request->setHeader('Authorization', 'Bearer ' . $this->options['oauth2']['access_token']);

        $response = $this->send($request);

        if($response->status != 200) {

            $meta = new \Hazaar\Map($response->body);

            if($meta->has('error')) {

                $err = $meta->error;

            } else {

                $err = 'Unknown error!';

            }

            throw new Exception\DropboxError($err, $response->status);

        }

        if($is_meta == TRUE) {

            $meta = new \Hazaar\Map($response->body);

            if($meta->has('error')) {

                throw new Exception\DropboxError($meta->error);

            }

        } else {

            $meta = $response->body;

        }

        return $meta;

    }

    public function refresh($reset = FALSE) {

        if(! $this->authorised())
            return FALSE;

        $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/delta', 'POST');

        if(! $reset && count($this->meta) && $this->cursor)
            $request->cursor = $this->cursor;

        $response = $this->sendRequest($request);

        $this->cursor = $response->cursor;

        if($response->reset === TRUE)
            $this->meta = array('/' => array(
                'bytes'        => 0,
                'icon'         => 'folder',
                'path'         => '/',
                'is_dir'       => TRUE,
                'thumb_exists' => FALSE,
                'root'         => 'app_folder',
                'modified'     => 'Thu, 21 May 2015 06:06:57 +0000',
                'size'         => '0 bytes'
            ));

        foreach($response->entries as $entry) {

            list($path, $meta) = $entry->toArray();

            if($meta) {

                $this->meta[$path] = $meta;

            } elseif(array_key_exists($path, $this->meta)) {

                unset($this->meta[$path]);

            }

        }

        ksort($this->meta);

        return TRUE;

    }

    /*
     * Metadata Operations
     */
    public function scandir($path, $regex_filter = NULL, $recursive = FALSE) {

        if(! $this->authorised())
            return NULL;

        $path = strtolower('/' . ltrim($path, '/'));

        if(! ($pathMeta = ake($this->meta, $path)))
            return FALSE;

        if(! $pathMeta['is_dir'])
            return FALSE;

        $list = array();

        foreach($this->meta as $name => $meta) {

            if($name == '/' || pathinfo($name, PATHINFO_DIRNAME) !== $path)
                continue;

            $list[] = basename($meta['path']);

        }

        return $list;

    }

    public function info($path) {

        if(! $this->cursor)
            $this->refresh();

        if(! ($meta = ake($this->meta, strtolower($path))))
            return FALSE;

        return $meta;

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

        return $this->exists($path);

    }

    public function is_writable($path) {

        return $this->exists($path);

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        return $info['is_dir'];

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        return FALSE;

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        return ! ($info['is_dir']);

    }

    //Returns the file type
    public function filetype($path) {

        if(! ($info = $this->info($path)))
            return NULL;

        return ($info['is_dir'] ? 'dir' : 'file');

    }

    //Returns the file modification time
    public function filectime($path) {

        if(! ($info = $this->info($path)))
            return false;

        return strtotime($info['created']);

    }

    //Returns the file modification time
    public function filemtime($path) {

        if(! ($info = $this->info($path)))
            return false;

        return strtotime($info['modified']);

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

        return $info['bytes'];

    }

    public function fileperms($path) {

        return 0666;

    }

    public function chmod($path, $mode) {

        return TRUE;

    }

    public function chown($path, $user) {

        return TRUE;

    }

    public function chgrp($path, $group) {

        return TRUE;

    }

    public function unlink($path) {

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

        return ($info['is_dir'] ? 'dir' : $info['mime_type']);

    }

    public function md5Checksum($path) {

        return md5($this->read($path));

    }

    public function thumbnail($path, $params = array()) {

        if(! ($info = $this->info($path)))
            return NULL;

        if($info['thumb_exists']) {

            $size = 'l';

            $width = ake($params, 'width');

            $height = ake($params, 'height');

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

            if($format = ake($params, 'format'))
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

        $path = $this->resolvePath($path);

        if(!$this->exists($path))
            return false;

        if($recurse) {

            $dir = $this->scandir($path, NULL, TRUE);

            foreach($dir as $file) {

                if($file == '.' || $file == '..')
                    continue;

                $fullpath = $path . DIRECTORY_SEPARATOR . $file;

                if($this->is_dir($fullpath)) {

                    $this->rmdir($fullpath, TRUE);

                } else {

                    $this->unlink($fullpath);

                }

            }

        }

        return $this->unlink($path);

    }

    public function copy($src, $dst, $recursive = FALSE) {

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

        if($meta = $this->cache->get($key))
            $this->cache->set($this->options['app_key'] . '::' . strtolower($response->path), $meta);

        return TRUE;

    }

    public function link($src, $dst) {

        return FALSE;

    }

    public function move($src, $dst) {

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

        if($meta = $this->cache->get($key)) {

            $this->cache->set($this->options['app_key'] . '::' . strtolower($response->path), $meta);

            $this->cache->remove($key);

        }

        return TRUE;

    }

    /*
     * Access operations
     */
    public function read($path, $offset = -1, $maxlen = NULL) {

        $request = new \Hazaar\Http\Request('https://api-content.dropbox.com/1/files/auto' . $path, 'GET');

        if($offset >= 0) {

            $range = 'bytes=' . $offset . '-';

            if($maxlen) {

                $range .= ($offset + ($maxlen - 1));

            }

            $request->setHeader('Range', $range);

        }

        return $this->sendRequest($request, FALSE);

    }

    public function write($path, $data, $content_type, $overwrite = FALSE) {

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

        if($meta = $this->cache->get($this->options['app_key'] . '::' . strtolower($path)))
            return ake($meta, $key);

        return NULL;

    }

    public function set_meta($path, $values) {

        if(! ($meta = $this->cache->get($this->options['app_key'] . '::' . strtolower($path))))
            $meta = array();

        $meta = array_merge($meta, $values);

        $this->cache->set($this->options['app_key'] . '::' . strtolower($path), $meta);

        return TRUE;

    }

    private function clear_meta($path) {

        $this->cache->remove($this->options['app_key'] . '::' . strtolower($path));

        return TRUE;

    }

    public function preview_uri($path, $params = array()) {

        $width = intval(ake($params, 'width', ake($params, 'height', 64)));

        if($width >= 1024)
            $size = 'w1024h768';
        elseif($width >= 640)
            $size = 'w640h480';
        elseif($width >= 128)
            $size = 'w128h128';
        elseif($width >= 64)
            $size = 'w64h64';
        else
            $size = 'w32h32';

        $params = array(
            'authorization=Bearer ' . $this->options['oauth2']['access_token'],
            'arg={"path":"' . $path . '","size":"' . $size . '"}'
        );

        return 'https://content.dropboxapi.com/2/files/get_thumbnail?' . implode('&', $params);

    }

    public function direct_uri($path) {

        if(! $this->exists($path))
            return FALSE;

        $info = $this->info($path);

        if($info['is_dir'])
            return FALSE;

        if($info && ($media = ake($info, 'media')) && strtotime($media['expires']) > time())
            return $media['url'];

        $request = new \Hazaar\Http\Request('https://api.dropbox.com/1/media/auto' . $path, 'POST');

        $response = $this->sendRequest($request);

        if($response->url) {

            $this->meta[strtolower($path)]['media'] = $response->toArray();

            return $response->url;

        }

        return FALSE;

    }

}

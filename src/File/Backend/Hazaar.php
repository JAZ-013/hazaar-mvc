<?php

namespace Hazaar\File\Backend;

class Hazaar implements _Interface {

    public  $separator  = '/';
    
    private $options;

    private $pathCache = array();

    private $client;

    private $meta      = array();

    static public function label(){

        return "Hazaar VFS";

    }

    public function __construct($options = array()) {

        $this->options = new \Hazaar\Map(array(
                                             'url' => NULL
                                         ), $options);

        $this->client = new \Hazaar\Http\Client();

    }

    private function request($cmd, $params = array(), $mime_parts = array()) {

        $request = new \Hazaar\Http\Request($this->options->url, 'POST');

        if(is_array($params) && count($params) > 0)
            $request->populate($params);

        $request->cmd = $cmd;

        if(is_array($mime_parts) && count($mime_parts) > 0) {

            foreach($mime_parts as $part) {

                if(! count($part) == 2)
                    continue;

                $request->addMultipart($part[0], $part[1]);

            }

        }

        $response = $this->client->send($request);

        if($response->status == 200)
            return json_decode($response->body, TRUE);

        return FALSE;

    }

    public function refresh($reset = FALSE) {

        $this->pathCache = array();

        return TRUE;

    }

    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

        if(! $this->pathCache) {

            $this->pathCache = array(
                '/' => array(
                    'id'     => base64url_encode('/'),
                    'kind'   => 'dir',
                    'name'   => 'ROOT',
                    'path'   => '/',
                    'link'   => $this->options->url,
                    'parent' => NULL,
                    'mime'   => 'dir',
                    'read'   => TRUE,
                    'write'  => FALSE,
                    'dirs'   => 0,
                    'files'  => array()
                )
            );

            if($paths = $this->request('tree')) {

                foreach($paths as $p) {

                    list($source, $base) = explode(':', base64url_decode($p['parent']), 2);

                    if(! $base) {

                        $p['name'] = $source;

                        $p['parent'] = $this->pathCache['/']['id'];

                    }

                    $source_path = '/' . $source . $p['path'];

                    if($p['path'] == '/') {

                        $source_path = rtrim($source_path, '/');

                        $this->pathCache['/']['dirs']++;

                    }

                    $this->pathCache[$source_path] = $p;

                }

            }

        }

        if(! array_key_exists($path, $this->pathCache))
            return FALSE;

        if(! array_key_exists('files', $this->pathCache[$path])) {

            $this->pathCache[$path]['files'] = array();

            if($info = $this->request('open', array('target' => $this->pathCache[$path]['id'], 'with_meta' => TRUE))) {

                foreach($info['files'] as $file)
                    $this->pathCache[$path]['files'][$file['name']] = $file;

            }

        }

        $items = array();

        foreach($this->pathCache as $d) {

            if($d['parent'] === $this->pathCache[$path]['id'])
                $items[] = $d['name'];

        }

        foreach($this->pathCache[$path]['files'] as $file)
            $items[] = $file['name'];

        return $items;

    }

    //Check if file/path exists
    public function exists($path) {

        return is_array($this->info($path));

    }

    public function realpath($path) {

        return $path;

    }

    public function is_readable($path) {

        if($info = $this->info($path))
            return ake($info, 'read', FALSE);

        return FALSE;

    }

    public function is_writable($path) {

        if($info = $this->info($path))
            return ake($info, 'write', FALSE);

        return FALSE;

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        if($info = $this->info($path))
            return (ake($info, 'kind') == 'dir');

        return FALSE;

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        return FALSE;

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        if($info = $this->info($path))
            return (ake($info, 'kind', 'file') == 'file');

        return FALSE;

    }

    //Returns the file type
    public function filetype($path) {

        if($info = $this->info($path))
            return ake($info, 'kind', 'file');

        return FALSE;

    }

    //Returns the file modification time
    public function filectime($path) {

        if($info = $this->info($path))
            return ake($info, 'created');

        return FALSE;

    }

    //Returns the file modification time
    public function filemtime($path) {

        if($info = $this->info($path))
            return ake($info, 'modified');

        return FALSE;

    }

    //Returns the file modification time
    public function fileatime($path) {

        return FALSE;

    }

    public function filesize($path) {

        if($info = $this->info($path))
            return ake($info, 'size', 0);

        return FALSE;

    }

    public function unlink($path) {

        if(! ($info = $this->info($path)))
            return FALSE;

        $result = $this->request('unlink', array('target' => $info['id']));

        if(is_array($result)) {

            if($info['kind'] == 'dir') {

                dump('delete dir');

            } else {

                $dir = dirname($path);

                if(array_key_exists($dir, $this->pathCache))
                    unset($this->pathCache[$dir]['files'][$info['name']]);

            }

            return TRUE;

        }

        return FALSE;

    }

    public function mime_content_type($path) {

        if($info = $this->info($path))
            return ake($info, 'mime', FALSE);

        return FALSE;

    }

    public function md5Checksum($path) {

        return md5($this->read($path));

    }

    public function thumbnail($path, $params = array()) {

        if($link = ake($this->info($path), 'link')) {

            $uri = new \Hazaar\Http\Uri($link);

            $uri->setParams($params);

            return file_get_contents((string)$uri);

        }

        return FALSE;

    }

    public function & info($path) {

        $is_dir = $this->scandir($path);

        if($is_dir === FALSE) {

            $dir = $this->info(dirname($path));

            if($info = ake(ake($dir, 'files'), basename($path)))
                return $info;

        } else {

            if($info = ake($this->pathCache, $path))
                return $info;

        }

        return FALSE;

    }

    public function mkdir($path) {

        $parent = $this->info(dirname($path));

        $result = $this->request('mkdir', array('parent' => $parent['id'], 'name' => basename($path)));

        if($tree = ake($result, 'tree')) {

            foreach($tree as $d) {

                $source = explode(':', base64url_decode($d['id']), 2)[0];

                $source_path = '/' . $source . $d['path'];

                $this->pathCache[$source_path] = $d;

            }

            return TRUE;

        }

        return FALSE;

    }

    public function rmdir($path, $recurse = FALSE) {

        $info = ake($this->pathCache, $path);

        if(! $info['kind'] == 'dir')
            return FALSE;

        $result = $this->request('rmdir', array('target' => $info['id']));

        if(ake($result, 'ok', FALSE)) {

            unset($this->pathCache[$path]);

            return TRUE;

        }

        return FALSE;

    }

    public function read($path) {

        if(! ($info = $this->info($path)))
            return FALSE;

        dump($info);

        dump(__METHOD__);

        return FALSE;

    }

    public function write($path, $bytes, $content_type, $overwrite = FALSE) {

        $parent = $this->info(dirname($path));

        if(! $parent)
            return FALSE;

        $content = array($bytes, array(
            'Content-Disposition' => 'form-data; name="file"; filename="' . basename($path) . '"',
            'Content-Type'        => $content_type
        ));

        $info = $this->request('upload', array('parent' => $parent['id'], 'overwrite' => $overwrite), array($content));

        if($info) {

            if($fileInfo = ake($info, 'file')) {

                if($meta = ake($this->meta, $path))
                    $this->request('set_meta', array('target' => $fileInfo['id'], 'values' => $meta));

                $source_path = '/' . explode(':', base64url_decode($fileInfo['parent']), 2)[0];

                if(array_key_exists($source_path, $this->pathCache))
                    $this->pathCache[$source_path]['files'][$fileInfo['name']] = $fileInfo;

                return TRUE;

            }

        }

        return FALSE;

    }

    public function upload($path, $file, $overwrite = FALSE) {

        dump(__METHOD__);

        return FALSE;

    }

    public function copy($src, $dst, $recursive = FALSE) {

        dump(__METHOD__);

        return FALSE;

    }

    public function link($src, $dst) {

        dump(__METHOD__);

        return FALSE;

    }

    public function move($src, $dst) {

        dump(__METHOD__);

        return FALSE;

    }

    public function delete($path) {

        dump(__METHOD__);

        return FALSE;

    }

    public function fileperms($path) {

        dump(__METHOD__);

        if(! ($info = $this->info($path)))
            return FALSE;

        return ake($info, 'mode');

    }

    public function chmod($path, $mode) {

        return FALSE;

    }

    public function chown($path, $user) {

        return FALSE;

    }

    public function chgrp($path, $group) {

        return FALSE;

    }

    public function set_meta($path, $values) {

        if($info =& $this->info($path)) {

            $info['meta'] = array_merge(ake($info, 'meta'), $values);

            return $this->request('set_meta', array('target' => $info['_id'], 'values' => $values));

        }

        $this->meta[$path] = array_merge(ake($this->meta, $path, array()), $values);

        return TRUE;

    }

    public function get_meta($path, $key = NULL) {

        if($info = $this->info($path))
            return ($key ? ake(ake($info, 'meta'), $key) : ake($info, 'meta'));

        return FALSE;

    }

    public function preview_uri($path) {

        if($info = $this->info($path))
            return ake($info, 'previewLink');

        return FALSE;

    }

    public function direct_uri($path) {

        if($info = $this->info($path))
            return ake($info, 'link');

        return FALSE;

    }

}

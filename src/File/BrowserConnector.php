<?php

namespace Hazaar\File;

class BrowserConnector {

    private $sources = array();

    private $url;

    private $allowPreview;

    private $progressCallBack;

    public function __construct($url = NULL, $allowPreview = NULL) {

        $this->url = $url;

        $this->allowPreview = $allowPreview;
    }

    public function setProgressCallback($callback) {

        $this->progressCallBack = $callback;

    }

    public function addSource($id, $source, $name = NULL) {

        if($source instanceof \Hazaar\File\Manager) {

            if(! $name)
                $name = ucfirst($id);

        } else {

            if(! $name)
                $name = basename($source);

            if(! file_exists($source))
                throw new Exception\BrowserRootNotFound();

            $source = new Manager('local', array('root' => rtrim($source, '/\\')));

        }

        $source->setOption('name', $name);

        $this->sources[$id] = $source;

        return TRUE;

    }

    public function authorised() {

        foreach($this->sources as $id => $source) {

            if(! $source->authorised())
                return $id;

        }

        return TRUE;

    }

    public function authorise($sourceName, $redirect_uri = NULL) {

        if(! ($source = ake($this->sources, $sourceName)))
            return FALSE;

        return $source->authorise($redirect_uri);

    }

    public function source($target) {

        if(array_key_exists($target, $this->sources))
            return $this->sources[$target];

        $raw = base64url_decode($target);

        if(($pos = strpos($raw, ':')) > 0)
            $source = substr($raw, 0, $pos);
        else
            $source = $raw;

        return ake($this->sources, $source, FALSE);

    }

    public function path($target) {

        $raw = base64url_decode($target);

        if(($pos = strpos($raw, ':')) > 0)
            return substr($raw, $pos + 1);

        return FALSE;

    }

    private function target($source, $path = NULL) {

        if($source instanceof \Hazaar\File\Manager)
            $source = array_search($source, $this->sources);

        return base64url_encode($source . ':' . $path);

    }

    public function info(\Hazaar\File\Manager $source, $file) {

        if(!($file instanceof \Hazaar\File || $file instanceof \Hazaar\File\Dir))
            throw new \Hazaar\Exception('$file must be either Hazaar\File or Hazaar\File\Dir when calling info()');

        $is_dir = $file instanceof \Hazaar\File\Dir || $file->is_dir();

        $parent = ($file->fullpath() == '/') ? $this->target($source) : $this->target($source, $file->dirname() . '/');

        $fileId = $this->target($source, $source->fixPath($file->dirname(), $file->basename()) . ($is_dir ? '/' : ''));

        $linkURL = rtrim($this->url, '/') . '/' . $source->name . rtrim($file->dirname(), '/') . '/' . $file->basename();

        $downloadURL = $linkURL . '?download=true';

        $info = array(
            'id'           => $fileId,
            'kind'         => $file->type(),
            'name'         => $file->basename(),
            'path'         => $file->fullpath(),
            'link'         => $linkURL,
            'downloadLink' => $downloadURL,
            'parent'       => $parent,
            'modified'     => $file->mtime(),
            'size'         => $file->size(),
            'mime'         => (($file->type() == 'file') ? $file->mime_content_type() : 'dir'),
            'read'         => $file->is_readable(),
            'write'        => $file->is_writable()
        );

        if($is_dir) {

            $info['dirs'] = 0;

            $dir = $source->dir($file->fullpath());

            while(($file = $dir->read()) != FALSE) {

                if($file instanceof Dir)
                    $info['dirs']++;

            }

        } elseif($file instanceof \Hazaar\File && $file->is_readable() && preg_match_array($this->allowPreview, $info['mime'])) {

            $info['previewLink'] = rtrim($this->url, '/') . '/' . $source->name . rtrim($file->dirname(), '/') . '/' . $file->basename() . '?width={$w}&height={$h}&crop=true';

        }

        return $info;

    }

    public function tree($target = NULL, $depth = NULL) {

        $tree = array();

        if($target) {

            if(! count($this->sources) > 0)
                return FALSE;

            if(! $source = $this->source($target))
                return FALSE;

            $path = trim($this->path($target));

            $dir = $source->dir($path);

            if(substr($path, -1) === '/'){

                while(($file = $dir->read()) !== FALSE) {

                    if(! $file instanceof Dir)
                        continue;

                    $tree[] = $this->info($source, $file);

                    if($depth > 0 || $depth === NULL) {

                        $sub = $this->tree($this->target($source, $file->fullpath()), (($depth !== NULL) ? $depth - 1 : NULL));

                        $tree = array_merge($tree, $sub);

                    }

                }

            }else{

                $tree = array($this->info($source, $dir));

            }

        } else {

            foreach($this->sources as $id => $source) {

                if($source->refresh() === FALSE)
                    return FALSE;

                if(! ($root = $source->get('/')))
                    continue;

                $info = $this->info($source, $root);

                $info['name'] = $source->getOption('name');

                $info['expanded'] = (array_search($id, array_keys($this->sources)) > 0) ? FALSE : TRUE;

                $tree[] = $info;

                if($depth > 0 || $depth === NULL) {

                    $sub = $this->tree($this->target($source, $root->fullpath()), (($depth !== NULL) ? $depth - 1 : NULL));

                    $tree = array_merge($tree, $sub);

                }

            }

        }

        return $tree;

    }

    public function open($target, $tree = FALSE, $depth = 1, $filter = NULL, $with_meta = FALSE) {

        if(! count($this->sources) > 0)
            return FALSE;

        if(! $target)
            $target = $this->target(array_keys($this->sources)[0], '/');

        if(! $source = $this->source($target))
            return FALSE;

        $source->refresh();

        $files = array();

        $path = rtrim($source->fixPath($this->path($target)), '/') . '/';

        $dir = $source->dir($path);

        while(($file = $dir->read()) !== FALSE) {

            if(! $file instanceof \Hazaar\File)
                continue;

            if(! $file->is_readable())
                continue;

            if($filter && ! preg_match('/' . $filter . '/', $file->mime_content_type()))
                continue;

            $info = $this->info($source, $file);

            if($with_meta)
                $info['meta'] = $file->get_meta();

            $files[] = $info;

        }

        $result = array(
            'cwd'   => array(
                'id'     => $this->target($source, $path),
                'name'   => $path,
                'source' => array_search($source, $this->sources)
            ),
            'sys'   => array(
                'max_upload_size' => min(bytes_str(ini_get('upload_max_filesize')), bytes_str(ini_get('post_max_size')))
            ),
            'files' => $files
        );

        if(boolify($tree) === true)
            $result['tree'] = $this->tree($target, $depth);

        return $result;

    }

    public function get($target) {

        $source = $this->source($target);

        $path = $this->path($target);

        $file = $source->get($path);

        $response = new \Hazaar\Controller\Response\File($file);

        $response->setDownloadable(TRUE);

        return $response;

    }

    public function getFile($source, $path = '/') {

        if($source = $this->source($source))
            return $source->get($path);

        return FALSE;

    }

    public function getFileByPath($path) {

        list($source, $path) = explode('/', $path, 2);

        if($source = $this->source($source))
            return $source->get($path);

        return FALSE;

    }

    public function mkdir($parent, $name) {

        $source = $this->source($parent);

        $path = rtrim($this->path($parent), '/') . '/' . $name;

        if($source->mkdir($path)) {

            return array('tree' => array($this->info($source, $source->get($path))));

        }

        return array('ok' => FALSE);

    }

    public function rmdir($target, $recurse = FALSE) {

        $source = $this->source($target);

        $path = $this->path($target);

        $result = $source->rmdir($path, $recurse);

        return array('ok' => $result);

    }

    public function unlink($target) {

        if(! is_array($target))
            $target = array($target);

        $out = array('items' => array());

        foreach($target as $item) {

            $source = $this->source($item);

            $path = $this->path($item);

            $result = $source->unlink($path);

            if($result)
                $out['items'][] = $item;

        }

        return $out;

    }

    public function copy($from, $to) {

        $srcSource = $this->source($from);

        $srcPath = $this->path($from);

        $files = $srcSource->find(NULL, $srcPath);

        if($this->progressCallBack)
            call_user_func_array($this->progressCallBack, array('copy', array('init' => count($files))));

        $dstSource = $this->source($to);

        $dstPath = $this->path($to);

        $result = $dstSource->copy($srcPath, $dstPath, $srcSource, TRUE, $this->progressCallBack);

        if($result) {

            $out = array();

            if(! ($file = $dstSource->get(rtrim($dstPath, '/') . '/' . basename($srcPath))))
                return FALSE;

            $info = $this->info($dstSource, $file);

            if($info['kind'] == 'dir')
                $out['tree'] = array($info);

            else
                $out['items'] = array($info);

            return $out;

        }

        return array('ok' => FALSE);

    }

    public function move($from, $to) {

        $srcSource = $this->source($from);

        $srcPath = $this->path($from);

        $dstSource = $this->source($to);

        $dstPath = $this->path($to);

        $result = $dstSource->move($srcPath, $dstPath, $srcSource);

        if($result) {

            $out = array();

            if(! ($file = $dstSource->get(rtrim($dstPath, '/') . '/' . basename($srcPath))))
                return FALSE;

            $info = $this->info($dstSource, $file);

            if($info['kind'] == 'dir') {

                $out['rmdir'] = array($from);

                $out['tree'] = array($info);

            } else {

                $out['unlink'] = array($from);

                $out['items'] = array($info);

            }

            return $out;

        }

        return array('ok' => FALSE);

    }

    public function rename($target, $name, $with_meta = FALSE) {

        $manager = $this->source($target);

        $path = $this->path($target);

        $new = rtrim(dirname($path), '/') . '/' . $name;

        if($manager->move($path, $new)) {

            $file = $manager->get($new);

            $info = $this->info($manager, $file);

            if($with_meta)
                $info['meta'] = $file->get_meta();

            return array('ok' => TRUE, 'rename' => array($target => $info));

        }

        return array('ok' => FALSE);

    }

    public function upload($parent, $file, $relativePath = NULL) {

        if(! (array_key_exists('tmp_name', $file) && array_key_exists('name', $file) && array_key_exists('type', $file)))
            return FALSE;

        if(! $file['tmp_name'])
            return FALSE;

        $source = $this->source($parent);

        $path = rtrim($this->path($parent), '/') . '/';

        $info = array();

        if($relativePath) {

            $parts = explode('/', dirname($relativePath));

            for($i = 0; $i < count($parts); $i++) {

                $newPath = $path . implode('/', array_slice($parts, 0, $i + 1));

                if(! $source->exists($newPath)) {

                    if(! $source->mkdir($newPath))
                        throw new \Hazaar\Exception('Could not create parent directories');

                    if($newDir = $source->get($newPath))
                        $info['tree'][] = $this->info($source, $newDir);

                }

            }

            $path .= dirname($relativePath) . '/';

        }

        $result = $source->upload($path, $file);

        if($result) {

            $fullpath = $path . $file['name'];

            if(! ($f = $source->get($fullpath)))
                return array('ok' => FALSE);

            $info['file'] = $this->info($source, $f);

            return $info;

        }

        return array('ok' => FALSE);

    }

    public function get_meta($target, $key = NULL) {

        $source = $this->source($target);

        $path = $this->path($target);

        if($meta = $source->get_meta($path, $key))
            return array('ok' => TRUE, 'value' => $meta);

        return array('ok' => FALSE);
    }

    public function set_meta($target, $values) {

        $source = $this->source($target);

        $path = $this->path($target);

        if($source->set_meta($path, $values))
            return array('ok' => TRUE);

        return array('ok' => FALSE);

    }

    public function snatch($url, $target) {

        $out = array('ok' => FALSE, 'reason' => 'unknown');

        $client = new \Hazaar\Http\Client();

        $request = new \Hazaar\Http\Request($url);

        $response = $client->send($request);

        if($response->status == 200) {

            $source = $this->source($target);

            $path = rtrim($this->path($target), '/') . '/' . basename($url);

            if($source->write($path, $response->body, $response->getHeader('content-type'))) {

                $file = $source->get($path);

                $items = array(
                    $this->info($source, $file)
                );

                return array('ok' => TRUE, 'items' => $items);

            } else {

                $out['reason'] = 'Downloaded OK, but unable to write file to storage server.';

            }

        } else {

            $out['reason'] = 'Remote server returned HTTP response ' . $response->status;

        }

        return $out;

    }

    public function search($target, $query){

        $source = $this->source($target);

        $path = $this->path($target);

        $list = $source->find($query, $path, true);

        if(!is_array($list))
            throw new \Hazaar\Exception('Search failed!');

        foreach($list as &$item)
            $item = $this->info($source, $source->get($item));

        return $list;

    }

}


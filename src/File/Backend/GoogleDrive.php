<?php

namespace Hazaar\File\Backend;

class GoogleDrive extends \Hazaar\Http\Client implements _Interface {

    public  $separator  = '/';
    
    private $scope      = array(
        'https://www.googleapis.com/auth/drive'
    );

    private $options;

    private $cache;

    private $meta       = array();

    private $meta_items = array(
        'kind',
        'id',
        'title',
        'parents',
        'labels',
        'copyable',
        'editable',
        'mimeType',
        'createdDate',
        'modifiedDate', 'fileSize',
        'downloadUrl',
        'exportLinks',
        'thumbnailLink',
        'webContentLink',
        'md5Checksum'
    );

    private $cursor;

    static public function label(){

        return 'GoogleDrive';

    }
    
    public function __construct($options) {

        parent::__construct();

        $this->options = new \Hazaar\Map(array(
                                             'cache_backend'    => 'file',
                                             'oauth2'           => array('access_token' => NULL),
                                             'refresh_attempts' => 5,
                                             'maxResults'       => 100
                                         ), $options);

        if(! ($this->options->has('client_id') && $this->options->has('client_secret')))
            throw new Exception\DropboxError('Google Drive filesystem backend requires both client_id and client_secret.');

        $cacheOps = array(
            'use_pragma' => FALSE,
            'namespace'  => 'googledrive_' . $this->options->client_id
        );

        $this->cache = new \Hazaar\Cache($this->options['cache_backend'], $cacheOps);

        $this->oauth2ID = 'oauth2_data::' . md5(implode('', $this->scope));

        $this->reload();

    }

    public function reload() {

        if(($oauth2 = $this->cache->get($this->oauth2ID)))
            $this->options['oauth2'] = $oauth2;

        if(($cursor = $this->cache->get('cursor')) !== FALSE)
            $this->cursor = $cursor;

        if(($meta = $this->cache->get('meta')) !== FALSE)
            $this->meta = $meta;

    }

    public function reset() {

        unset($this->options['oauth2']);

        $this->meta = array();

        $this->cache->remove($this->oauth2ID);

        $this->cache->remove('cursor');

        $this->cache->remove('meta');

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

            $request = new \Hazaar\Http\Request('https://accounts.google.com/o/oauth2/token', 'POST');

            $request->code = $code;

            $request->redirect_uri = (string)$redirect_uri;

            $request->grant_type = 'authorization_code';

        } elseif($this->options['oauth2']->has('refresh_token') && $refresh_token = $this->options['oauth2']->get('refresh_token')) {

            $request = new \Hazaar\Http\Request('https://www.googleapis.com/oauth2/v3/token', 'POST');

            $request->refresh_token = $refresh_token;

            $request->grant_type = 'refresh_token';

        } else {

            return $this->authorised();

        }

        $request->client_id = $this->options['client_id'];

        $request->client_secret = $this->options['client_secret'];

        $response = $this->send($request);

        if($response->status !== 200)
            return FALSE;

        if($auth = json_decode($response->body, TRUE)) {

            $this->options['oauth2']->extend($auth);

            $this->cache->set($this->oauth2ID, $this->options['oauth2']->toArray(), -1);

            return TRUE;

        }

        return FALSE;

    }

    public function authorised() {

        return ($this->options->has('oauth2') && $this->options['oauth2']['access_token'] !== NULL);

    }

    public function buildAuthURL($redirect_uri = NULL) {

        $state = md5(uniqid());

        $this->cache->set('oauth2_state', $state, 300);

        $params = array(
            'response_type=code',
            'access_type=offline',
            'approval_prompt=force',
            'client_id=' . $this->options->client_id,
            'scope=' . implode(' ', $this->scope),
            'redirect_uri=' . $redirect_uri,
            'state=' . $state
        );

        return 'https://accounts.google.com/o/oauth2/auth?' . implode('&', $params);

    }

    private function sendRequest(\Hazaar\Http\Request $request, $is_meta = TRUE) {

        $count = 0;

        while(++$count) {

            if($count > $this->options['refresh_attempts'])
                throw new \Hazaar\Exception('Too many refresh attempts!');

            $request->setHeader('Authorization', $this->options['oauth2']['token_type'] . ' ' . $this->options['oauth2']['access_token']);

            $response = $this->send($request);

            if($response->status >= 200 && $response->status < 206) {

                break;

            } elseif($response->status == 401) {

                if(! $this->authorise())
                    throw new \Hazaar\Exception('Unable to refresh access token!');

            } else {

                if($response->getHeader('content-type') == 'application/json') {

                    $meta = new \Hazaar\Map($response->body);

                    if($meta->has('error')) {

                        $err = $meta->error;

                    } else {

                        $err = 'Unknown error!';

                    }

                    $message = $err->message;

                    $code = $err->code;

                } else {

                    $message = $response->body;

                    $code = intval($response->status);

                }

                throw new \Hazaar\Exception($message, $code);

            }

        }

        if($is_meta == TRUE) {

            $meta = new \Hazaar\Map($response->body);

            if($meta->has('error'))
                throw new \Hazaar\Exception($meta->error);

        } else {

            $meta = $response->body;

        }

        return $meta;

    }

    public function refresh($reset = FALSE) {

        if($reset || count($this->meta) == 0) {

            $this->meta = array();

            $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/changes', 'GET');

            $response = $this->sendRequest($request);

            $this->cursor = intval($response->largestChangeId);

            $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files/root', 'GET');

            $response = $this->sendRequest($request);

            $this->meta[$response->id] = array_intersect_key($response->toArray(), array_flip($this->meta_items));

            $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files', 'GET');

            if($this->options->has('maxResults'))
                $request->maxResults = $this->options['maxResults'];

            while(TRUE) {

                $response = $this->sendRequest($request);

                if(! $response)
                    return FALSE;

                foreach($response->items->toArray() as $item)
                    $this->meta[$item['id']] = array_intersect_key($item, array_flip($this->meta_items));

                if(! $response->has('nextPageToken'))
                    break;

                $request->pageToken = $response->nextPageToken;

            }

            return TRUE;

        }

        $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/changes?pageToken=' . ($this->cursor + 1), 'GET');

        $response = $this->sendRequest($request);

        $this->cursor = $response->largestChangeId;

        if(! $response)
            return FALSE;

        $items = array();

        $deleted = array();

        foreach($response->items->toArray() as $item) {

            if($item['deleted'] == TRUE && array_key_exists($item['fileId'], $this->meta)) {

                $items = array_merge($items, $this->resolveItem($this->meta[$item['fileId']]));

                $deleted[] = $item['fileId'];

            } else {

                $file = array_intersect_key($item['file'], array_flip($this->meta_items));

                $this->meta[$item['fileId']] = $file;

                $items = array_merge($items, $this->resolveItem($file));

            }

        }

        foreach($deleted as $fileId)
            unset($this->meta[$fileId]);

        return $items;

    }

    private function itemHasParent($item, $parentId) {

        if(! (array_key_exists('parents', $item) && is_array($item['parents'])))
            return FALSE;

        foreach($item['parents'] as $itemParent) {

            if($itemParent['id'] == $parentId)
                return TRUE;

        }

        return FALSE;

    }

    private function resolvePath($path) {

        $path = '/' . ltrim($path, '/');

        $parent = NULL;

        foreach($this->meta as $item) {

            if($item['parents'] === NULL) {

                $parent = $item;

                break;

            }

        }

        if(! $parent)
            return FALSE; //This should never happen!

        if($path !== '/') {

            $parts = explode('/', $path);

            /*
             * Paths have a forward slash on the start and end so we need to drop the first and last elements.
             */
            array_shift($parts);

            foreach($parts as $part) {

                if(! $part)
                    continue;

                $id = $parent['id'];

                $parent = NULL;

                foreach($this->meta as $item) {

                    if($item['title'] == $part && $this->itemHasParent($item, $id))
                        $parent = $item;

                }

                if(! $parent)
                    return FALSE;

            }

        }

        return $parent;

    }

    private function resolveItem($item) {

        $path = array();

        if($parents = ake($item, 'parents')) {

            foreach($parents as $parentRef) {

                if(! ($parent = ake($this->meta, $parentRef['id'])))
                    continue;

                $parentPaths = $this->resolveItem($parent);

                foreach($parentPaths as $index => $value)
                    $path[] = rtrim($value, '/') . '/' . $item['title'];

            }

        } else
            $path[] = '/';

        return $path;

    }

    //Get a directory listing
    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

        $parent = $this->resolvePath($path);

        $items = array();

        foreach($this->meta as $id => $item) {

            if(! $item['parents'] || $item['labels']['trashed'])
                continue;

            if($this->itemHasParent($item, $parent['id']))
                $items[] = $item['title'];

        }

        return $items;

    }

    //Check if file/path exists
    public function exists($path) {

        if($item = $this->resolvePath($path))
            return ! (ake($item['labels'], 'trashed', FALSE));

        return FALSE;

    }

    public function realpath($path) {

        return $path;

    }

    public function is_readable($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return ake($item, 'copyable', FALSE);

    }

    public function is_writable($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return ake($item, 'editable', FALSE);

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return ($item['mimeType'] == 'application/vnd.google-apps.folder');

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        die(__METHOD__);

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        return ! ($this->is_dir($path));

    }

    //Returns the file type
    public function filetype($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        if($item['mimeType'] == 'application/vnd.google-apps.folder')
            return 'dir';

        return 'file';

    }

    //Returns the file modification time
    public function filectime($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return strtotime($item['createdDate']);

    }

    //Returns the file modification time
    public function filemtime($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return strtotime($item['modifiedDate']);

    }

    //Returns the file modification time
    public function fileatime($path) {

        return false;

    }

    public function filesize($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return ake($item, 'fileSize', 0);

    }

    public function fileperms($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        die(__METHOD__);

    }

    public function chmod($path, $mode) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        die(__METHOD__);

    }

    public function chown($path, $user) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        die(__METHOD__);

    }

    public function chgrp($path, $group) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        die(__METHOD__);

    }

    public function unlink($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        if(! ($parent = $this->resolvePath(dirname($path))))
            return FALSE;

        if(count($item['parents']) > 1) {

            $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files/' . $item['id'] . '/parents/' . $parent['id'], 'DELETE', 'application/json');

            $this->sendRequest($request, FALSE);

        } else {

            $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files/' . $item['id'], 'DELETE');

            $this->sendRequest($request, FALSE);

        }

        return TRUE;

    }

    public function mime_content_type($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return ake($item, 'mimeType');

    }

    public function md5Checksum($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return ake($item, 'md5Checksum');

    }

    public function thumbnail($path, $params = array()) {

        return FALSE;

    }

    //Create a directory
    public function mkdir($path) {

        if(! ($parent = $this->resolvePath(dirname($path))))
            return FALSE;

        $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files', 'POST', 'application/json');

        $request->title = basename($path);

        $request->parents = array(array('id' => $parent['id']));

        $request->mimeType = 'application/vnd.google-apps.folder';

        $response = $this->sendRequest($request);

        if($response) {

            $this->meta[$response['id']] = $response->toArray();

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

    //Copy a file from src to dst
    public function copy($src, $dst, $recursive = FALSE) {

        if(! ($item = $this->resolvePath($src)))
            return FALSE;

        if(! ($parent = $this->resolvePath($dst)))
            return FALSE;

        $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files/' . $item['id'] . '/copy', 'POST', 'application/json');

        $request->title = $item['title'];

        $request->parents = array(array('id' => $parent['id']));

        $response = $this->sendRequest($request);

        if($response) {

            $this->meta[$response['id']] = $response->toArray();

            return TRUE;

        }

        return FALSE;

    }

    public function link($src, $dst) {

        if(! ($item = $this->resolvePath($src)))
            return FALSE;

        if(! ($parent = $this->resolvePath($dst)))
            return FALSE;

        $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files/' . $item['id'] . '/parents/' . $parent['id'], 'POST', 'application/json');

        $this->sendRequest($request, FALSE);

        return TRUE;

    }

    //Move a file from src to dst
    public function move($src, $dst) {

        if(! ($item = $this->resolvePath($src)))
            return FALSE;

        if(! ($srcParent = $this->resolvePath(dirname($src))))
            return FALSE;

        if(! ($dstParent = $this->resolvePath($dst)))
            return FALSE;

        $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files/' . $item['id'] . '/parents', 'POST', 'application/json');

        $request->populate(array('id' => $dstParent['id']));

        $response = $this->sendRequest($request);

        if($response) {

            $this->meta[$item['id']]['parents'][] = $request->toArray();

            $request = new \Hazaar\Http\Request('https://www.googleapis.com/drive/v2/files/' . $item['id'] . '/parents/' . $srcParent['id'], 'DELETE', 'application/json');

            $this->sendRequest($request, FALSE);

            return TRUE;

        }

        return FALSE;

    }

    //Read the contents of a file
    public function read($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        if(! ($downloadUrl = ake($item, 'downloadUrl'))) {

            if($exportLinks = ake($item, 'exportLinks')) {

                if(array_key_exists('application/rtf', $exportLinks))
                    $downloadUrl = $exportLinks['application/rtf'];

                elseif(array_key_exists('application/pdf', $exportLinks))
                    $downloadUrl = $exportLinks['application/pdf'];

                else
                    return NULL;

            }

        }

        $request = new \Hazaar\Http\Request($downloadUrl, 'GET');

        return $this->sendRequest($request, FALSE);

    }

    //Write the contents of a file
    public function write($file, $data, $content_type, $overwrite = FALSE) {

        if(! $overwrite && $this->exists($file))
            return FALSE;

        if(! ($parent = $this->resolvePath(dirname($file))))
            return FALSE;

        $request = new \Hazaar\Http\Request('https://www.googleapis.com/upload/drive/v2/files?uploadType=multipart', 'POST');

        $request->addMultipart(array('title' => basename($file), 'parents' => array(array('id' => $parent['id']))), 'application/json');

        $request->addMultipart($data, $content_type);

        $response = $this->sendRequest($request);

        if($response) {

            $this->meta[$response['id']] = $response->toArray();

            return TRUE;

        }

        return FALSE;

    }

    public function upload($path, $file, $overwrite = TRUE) {

        if(! (($srcFile = ake($file, 'tmp_name')) && $filetype = ake($file, 'type')))
            return FALSE;

        $fullPath = rtrim($path, '/') . '/' . $file['name'];

        return $this->write($fullPath, file_get_contents($srcFile), $filetype, $overwrite = FALSE);

    }

    public function set_meta($path, $values) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        var_dump($item);

        die(__METHOD__);

    }

    public function get_meta($path, $key = NULL) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        var_dump($item);

        die(__METHOD__);

    }

    public function preview_uri($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        if(! ($link = ake($item, 'thumbnailLink')))
            return FALSE;

        if(($pos = strrpos($link, '=')) > 0)
            $link = substr($link, 0, $pos);

        $link .= '=w{$w}-h{$h}-p';

        return $link;

    }

    public function direct_uri($path) {

        if(! ($item = $this->resolvePath($path)))
            return FALSE;

        return str_replace('&export=download', '', ake($item, 'webContentLink'));

    }

}


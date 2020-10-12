<?php

namespace Hazaar\File\Backend;

class MongoDB implements _Interface {

    public  $separator  = '/';

    private $options;

    private $db;

    private $gridFS;

    private $collection;

    private $rootObject;

    static public function label(){

        return "MongoDB GridFS";

    }
    
    public function __construct($options = array()) {

        $this->options = ($options instanceof \Hazaar\Map) ? $options : new \Hazaar\Map($options);

        $this->db = new \Hazaar\MongoDB\Adapter($this->options);

        $this->gridFS = $this->db->getGridFS();

        $reflection = new \ReflectionClass($this->gridFS);

        $filesName = $reflection->getProperty('filesName');

        $filesName->setAccessible(TRUE);

        $this->collection = $this->db->selectCollection($filesName->getValue($this->gridFS));

        //$this->collection->ensureIndex(array('filename' => 1, 'parents' => 1), array('unique' => TRUE));

        //$this->collection->ensureIndex(array('md5' => 1), array('unique' => TRUE, 'sparse' => TRUE));

        $this->loadRootObject();

    }

    public function refresh($reset = TRUE) {

        return TRUE;

    }

    public function loadRootObject() {

        if(! ($this->rootObject = $this->collection->findOne(array('parents' => array('$type' => 10))))) {

            $root = array(
                'kind'         => 'dir',
                'filename'     => 'ROOT',
                'parents'      => NULL,
                'uploadDate'   => new \MongoDB\BSON\UTCDateTime(),
                'modifiedDate' => NULL,
                'length'       => 0,
                'mime_type'    => 'directory'
            );

            $this->collection->insertOne($root);

            /*
             * If we are recreating the ROOT document then everything is either
             *
             * a) New - In which case this won't do a thing
             *      - or possibly -
             * b) Screwed - In which case this should make everything work again.
             *
             */
            $this->collection->updateOne(array('parents' => array('$not' => array('$type' => 10))), array(
                '$set' => array(
                    'parents' => array($root['_id'])
                )
            ), array('multiple' => TRUE));

            $this->rootObject = $root;

        }

        return is_array($this->rootObject);

    }

    private function loadObjects(&$parent = NULL) {

        if(! is_array($parent))
            return FALSE;

        $criteria = array(
            '$and'    => array(
                array('filename' => array('$exists' => TRUE)),
                array('filename' => array('$ne' => NULL)),
            ),
            'parents' => $parent['_id']
        );

        $q = $this->collection->find($criteria);

        $parent['items'] = array();

        while($object = $q->getNext())
            $parent['items'][$object['filename']] = $object;

        return TRUE;

    }

    private function & info($path) {

        $parent =& $this->rootObject;

        if($path === '/')
            return $parent;

        $parts = explode('/', $path);

        /*
         * Paths have a forward slash on the start so we need to drop the first element.
         */
        array_shift($parts);

        foreach($parts as $part) {

            if($part === '')
                continue;

            if(! (array_key_exists('items', $parent) && is_array($parent['items'])))
                $this->loadObjects($parent);

            if(! array_key_exists($part, $parent['items']))
                return FALSE;

            $parent =& $parent['items'][$part];

            if(! $parent)
                return FALSE;

        }

        return $parent;

    }

    public function fsck() {

        $c = $this->collection->find(array(), array('filename' => TRUE, 'parents' => TRUE));

        while($file = $c->getNext()) {

            $update = array();

            if(! is_array($file['parents']))
                continue;

            /*
             * Make sure an objects parents exist!
             *
             * NOTE: This is allowed to be slow as it is never usually executed.
             */
            foreach($file['parents'] as $index => $parentID) {

                $parent = $this->collection->findOne(array('_id' => $parentID));

                if(! $parent)
                    $update[] = $index;

            }

            if(count($update) > 0) {

                foreach($update as $index)
                    unset($file['parents'][$index]);

                /*
                 * Fix up any parentless objects
                 */
                if(count($file['parents']) == 0)
                    $file['parents'] = array($this->rootObject['_id']);

                $this->collection->updateOne(array('_id' => $file['_id']), array('$set' => array('parents' => $file['parents'])));

            }

        }

        $this->loadRootObject();

        return TRUE;

    }

    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

        if(! ($parent = $this->info($path)))
            return FALSE;

        if(! array_key_exists('items', $parent))
            $this->loadObjects($parent);

        $list = array();

        foreach($parent['items'] as $filename => $file) {

            $fullpath = $path . $file['filename'];

            if($regex_filter && ! preg_match($regex_filter, $fullpath))
                continue;

            $list[] = $file['filename'];

        }

        return $list;

    }

    //Check if file/path exists
    public function exists($path) {

        return is_array($this->info($path));

    }

    public function realpath($path) {

        return $path;

    }

    public function is_readable($path) {

        return TRUE;

    }

    public function is_writable($path) {

        return TRUE;

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
            return ake($info, 'uploadDate')->sec;

        return FALSE;

    }

    //Returns the file modification time
    public function filemtime($path) {

        if($info = $this->info($path))
            return ake($info, 'modifiedDate', $info['uploadDate'], TRUE)->sec;

        return FALSE;

    }

    public function touch($path){

        if(!($info = $this->info($path)))
            return FALSE;

        $parent =& $this->info(dirname($path));

        $data = array('$set'  => array('modifiedDate' => new \MongoDB\BSON\UTCDateTime));

        $ret = $this->collection->updateOne(array('_id' => $info['_id']), $data);

        return $ret->isAcknowledged();

    }

    //Returns the file modification time
    public function fileatime($path) {

        return FALSE;

    }

    public function filesize($path) {

        if(! ($info = $this->info($path)))
            return FALSE;

        return ake($info, 'length', 0);

    }

    public function fileperms($path) {

        if(! ($info = $this->info($path)))
            return FALSE;

        return ake($info, 'mode');

    }

    public function mime_content_type($path) {

        if($info = $this->info($path))
            return ake($info, 'mime_type', FALSE);

        return FALSE;

    }

    public function md5Checksum($path) {

        if($info = $this->info($path))
            return ake($info, 'md5');

        return FALSE;

    }

    public function thumbnail($path, $params = array()) {

        return FALSE;

    }

    public function mkdir($path) {

        if($info = $this->info($path))
            return FALSE;

        $parent =& $this->info(dirname($path));

        $info = array(
            'kind'         => 'dir',
            'parents'      => array($parent['_id']),
            'filename'     => basename($path),
            'length'       => 0,
            'uploadDate'   => new \MongoDB\BSON\UTCDateTime(),
            'modifiedDate' => NULL
        );

        $ret = $this->collection->insertOne($info);

        if($ret->isAcknowledged()) {

            if(! array_key_exists('items', $parent))
                $parent['items'] = array();

            $parent['items'][$info['filename']] = $info;

            return TRUE;

        }

        return FALSE;

    }

    public function unlink($path) {

        if($info = $this->info($path)) {

            $parent =& $this->info(dirname($path));

            if(($index = array_search($parent['_id'], $info['parents'])) !== FALSE) {

                if(count($info['parents']) > 1) {

                    $data = array(
                        '$set'  => array('modifiedDate' => new \MongoDB\BSON\UTCDateTime),
                        '$pull' => array('parents' => $info['parents'][$index])
                    );

                    $ret = $this->collection->updateOne(array('_id' => $info['_id']), $data);

                } else {

                    $ret = $this->gridFS->remove(array('_id' => $info['_id']));

                }

                if($ret->isAcknowledged()) {

                    unset($parent['items'][$info['filename']]);

                    return TRUE;

                }

            }

        }

        return FALSE;

    }

    public function rmdir($path, $recurse = FALSE) {

        if($info = $this->info($path)) {

            if($info['kind'] != 'dir')
                return FALSE;

            $dir = $this->scandir($path, NULL, TRUE);

            if(count($dir) > 0) {

                if($recurse) {

                    foreach($dir as $file) {

                        $fullPath = $path . '/' . $file;

                        if($this->is_dir($fullPath))
                            $this->rmdir($fullPath, TRUE);

                        else
                            $this->unlink($fullPath);

                    }

                } else {

                    return FALSE;

                }

            }

            if($path == '/')
                return TRUE;

            return $this->unlink($path);

        }

        return FALSE;

    }

    public function read($path) {

        if(! ($item = $this->info($path)))
            return FALSE;

        if(! ($file = $this->gridFS->findOne(array('_id' => $item['_id']))))
            return FALSE;

        $this->collection->updateOne(array('_id' => $item['_id']), array('$inc' => array('accessCount' => 1), '$set' => array('accessDate' => new \MongoDB\BSON\UTCDateTime())));

        return $file->getBytes();

    }

    public function write($path, $bytes, $content_type = null, $overwrite = FALSE) {

        $parent =& $this->info(dirname($path));

        if(! $parent)
            return FALSE;

        $md5 = md5($bytes);

        if($info = $this->collection->findOne(array('md5' => $md5))) {

            if(in_array($parent['_id'], $info['parents']))
                return FALSE;

            $data = array(
                '$set'  => array('modifiedDate' => new \MongoDB\BSON\UTCDateTime),
                '$push' => array('parents' => $parent['_id'])
            );

            $ret = $this->collection->updateOne(array('_id' => $info['_id']), $data);

            if($ret->isAcknowledged()) {

                if(! array_key_exists('items', $parent))
                    $parent['items'] = array();

                $parent['items'][$info['filename']] = $info;

                return TRUE;

            }

        } else {

            $fileInfo = array(
                'kind'         => 'file',
                'parents'      => array($parent['_id']),
                'filename'     => basename($path),
                'mime_type'    => $content_type,
                'modifiedDate' => NULL,
                'md5'          => $md5
            );

            if($info = $this->info($path))
                $fileInfo['meta'] = ake($info, 'meta');

            if($id = $this->gridFS->storeBytes($bytes, $fileInfo)) {

                $fileInfo['_id'] = $id;

                $fileInfo['length'] = strlen($bytes);

                if(! array_key_exists('items', $parent))
                    $parent['items'] = array();

                $parent['items'][$fileInfo['filename']] = $fileInfo;

                return TRUE;

            }

        }

        return FALSE;

    }

    public function upload($path, $file, $overwrite = FALSE) {

        $parent =& $this->info($path);

        if(! $parent)
            return FALSE;

        $md5 = md5_file($file['tmp_name']);

        if($info = $this->collection->findOne(array('md5' => $md5))) {

            if(in_array($parent['_id'], $info['parents']))
                return FALSE;

            $data = array(
                '$set'  => array('modifiedDate' => new \MongoDB\BSON\UTCDateTime),
                '$push' => array('parents' => $parent['_id'])
            );

            $ret = $this->collection->updateOne(array('_id' => $info['_id']), $data);

            if($ret->isAcknowledged()) {

                if(! array_key_exists('items', $parent))
                    $parent['items'] = array();

                $parent['items'][$info['filename']] = $info;

                return TRUE;

            }

        } else {

            $fileInfo = array(
                'kind'         => 'file',
                'parents'      => array($parent['_id']),
                'filename'     => $file['name'],
                'mime_type'    => $file['type'],
                'modifiedDate' => NULL,
                'md5'          => $md5
            );

            if($id = $this->gridFS->storeFile($file['tmp_name'], $fileInfo)) {

                $fileInfo['_id'] = $id;

                $fileInfo['length'] = $file['size'];

                if(! array_key_exists('items', $parent))
                    $parent['items'] = array();

                $parent['items'][$fileInfo['filename']] = $fileInfo;

                return TRUE;

            }

        }

        return FALSE;

    }

    public function copy($src, $dst, $recursive = FALSE) {

        if(! ($source = $this->info($src)))
            return FALSE;

        $dstParent =& $this->info($dst);

        if($dstParent) {

            if($dstParent['kind'] !== 'dir')
                return FALSE;

        } else {

            $dstParent =& $this->info(dirname($dst));

        }

        if(! $dstParent)
            return FALSE;

        $data = array(
            '$set' => array('modifiedDate' => new \MongoDB\BSON\UTCDateTime)
        );

        if(! in_array($dstParent['_id'], $source['parents']))
            $data['$push'] = array('parents' => $dstParent['_id']);

        $ret = $this->collection->updateOne(array('_id' => $source['_id']), $data);

        if($ret->isAcknowledged()) {

            if(! array_key_exists('items', $dstParent))
                $dstParent['items'] = array();

            $dstParent['items'][$source['filename']] = $source;

            return TRUE;

        }

        return FALSE;

    }

    public function link($src, $dst) {

        if(! ($source = $this->info($src)))
            return FALSE;

        $dstParent =& $this->info($dst);

        if($dstParent) {

            if($dstParent['kind'] !== 'dir')
                return FALSE;

        } else {

            $dstParent =& $this->info(dirname($dst));

        }

        if(! $dstParent)
            return FALSE;

        $data = array(
            '$set' => array('modifiedDate' => new \MongoDB\BSON\UTCDateTime)
        );

        if(! in_array($dstParent['_id'], $source['parents']))
            $data['$push'] = array('parents' => $dstParent['_id']);

        $ret = $this->collection->updateOne(array('_id' => $source['_id']), $data);

        if($ret->isAcknowledged()) {

            if(! array_key_exists('items', $dstParent))
                $dstParent['items'] = array();

            $dstParent['items'][$source['filename']] = $source;

            return TRUE;

        }

        return FALSE;

    }

    public function move($src, $dst) {

        if(substr($dst, 0, strlen($src)) == $src)
            return FALSE;

        if(! ($source = $this->info($src)))
            return FALSE;

        $srcParent =& $this->info(dirname($src));

        $data = array(
            '$set' => array('modifiedDate' => new \MongoDB\BSON\UTCDateTime)
        );

        $dstParent =& $this->info($dst);

        if($dstParent) {

            //If the destination exists and is NOT a directory, return false so we don't overwrite an existing file.
            if($dstParent['kind'] !== 'dir')
                return FALSE;

        } else {

            //We are renaming the file.

            if($source['filename'] != basename($dst))
                $dstParent['filename'] = $data['$set']['filename'] = basename($dst);

            $dstParent =& $this->info(dirname($dst));

            //Update the parents items array key with the new name.
            $basename = basename($src);

            $dstParent['items'][basename($dst)] = $dstParent['items'][$basename];

            unset($dstParent['items'][$basename]);

        }

        if(! in_array($dstParent['_id'], $source['parents']))
            $data['$push'] = array('parents' => $dstParent['_id']);

        $ret = $this->collection->updateOne(array('_id' => $source['_id']), $data);

        if($ret->isAcknowledged()) {

            if(! array_key_exists('items', $dstParent))
                $dstParent['items'] = array();

            $dstParent['items'][$source['filename']] = $source;

            if($srcParent['_id'] != $dstParent['_id']) {

                unset($srcParent['items'][$source['filename']]);

                $this->collection->updateOne(array('_id' => $source['_id']), array('$pull' => array('parents' => $srcParent['_id'])));

            }

            return TRUE;

        }

        return FALSE;

    }

    public function chmod($path, $mode) {

        if(! is_int($mode))
            return FALSE;

        if($target =& $this->info($path)) {

            $target['mode'] = $mode;

            $ret = $this->collection->updateOne(array('_id' => $target['_id']), array('$set' => array('mode' => $mode)));

            return $ret->isAcknowledged();

        }

        return FALSE;

    }

    public function chown($path, $user) {

        if($target =& $this->info($path)) {

            $target['owner'] = $user;

            $ret = $this->collection->updateOne(array('_id' => $target['_id']), array('$set' => array('owner' => $user)));

            return $ret->isAcknowledged();

        }

        return FALSE;

    }

    public function chgrp($path, $group) {

        if($target =& $this->info($path)) {

            $target['group'] = $group;

            $ret = $this->collection->updateOne(array('_id' => $target['_id']), array('$set' => array('group' => $group)));

            return $ret->isAcknowledged();

        }

        return FALSE;

    }

    public function set_meta($path, $values) {

        if($target =& $this->info($path)) {

            $data = array();

            foreach($values as $key => $value)
                $data['meta.' . $key] = $value;

            $ret = $this->collection->updateOne(array('_id' => $target['_id']), array('$set' => $data));

            return $ret->isAcknowledged();

        }

        if($parent =& $this->info(dirname($path))) {

            $parent['items'][basename($path)]['meta'] = $values;

            return TRUE;

        }

        return FALSE;

    }

    public function get_meta($path, $key = NULL) {

        if(! ($info = $this->info($path)))
            return FALSE;

        if(array_key_exists('meta', $info)) {

            if($key)
                return ake($info['meta'], $key);

            return $info['meta'];

        }

        return NULL;

    }

    public function preview_uri($path) {

        return FALSE;

    }

    public function direct_uri($path) {

        return FALSE;

    }

}

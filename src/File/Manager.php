<?php

namespace Hazaar\File;

use \Hazaar\Application;

use \Hazaar\File;
use \Hazaar\File\Dir;

class Manager implements Backend\_Interface {

    static private $backend_aliases = array(
        'googledrive' => 'GoogleDrive',
        'mongodb'     => 'MongoDB',
        'sharepoint'  => 'SharePoint',
        'webdav'      => 'WebDAV'
    );

    static public  $default_config = array(
        'enabled' => true,
        'auth' => false,
        'allow' => array(
            'read' => false,        //Default disallow reads when auth enabled
            'cmd'  => false,        //Default disallow file manager commands
            'dir' => true,          //Allow directory listings
            'filebrowser' => false  //Allow access to the JS file browser
        ),
        'userdef' => array(),
        'failover' => false
    );

    static private $default_backend;

    static private $default_backend_options;

    private        $backend;

    private        $backend_name;

    private        $options = array();

    public         $name;

    private        $failover = false;

    private        $in_failover = false;

    function __construct($backend = NULL, $backend_options = array(), $name = NULL) {

        if(!$backend) {

            if(Manager::$default_backend) {

                $backend = Manager::$default_backend;

                $backend_options = Manager::$default_backend_options;

            } else {

                $backend = 'local';

                $backend_options = array('root' => ((substr(PHP_OS, 0, 3) == 'WIN') ? substr(APPLICATION_PATH, 0, 3) : '/'));

            }

        }

        $class = 'Hazaar\File\Backend\\' . ake(self::$backend_aliases, $backend, ucfirst($backend));

        if(!class_exists($class))
            throw new Exception\BackendNotFound($backend);

        $this->backend_name = $backend;

        $this->backend = new $class($backend_options);

        if(!$this->backend instanceof \Hazaar\File\Backend\_Interface)
            throw new Exception\InvalidBackend($backend);

        if(! $name)
            $name = strtolower($backend);

        $this->name = $name;

    }

    function __destruct(){

        try{

            if($this->failover && $this->in_failover === false)
                $this->failoverSync();

        }catch(\Exception $e){

            //Silently make no difference to the world around you

        }

    }

    static public function getAvailableBackends(){

        $composer = Application::getInstance()->composer();

        if(!property_exists($composer, 'require'))
            return false;

        foreach($composer->require as $lib => $ver){

            $lib_path = realpath(APPLICATION_PATH
                . DIRECTORY_SEPARATOR . '..'
                . DIRECTORY_SEPARATOR . 'vendor'
                . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $lib));

            if(substr(dirname($lib_path), -10) !== 'hazaarlabs')
                continue;

            $backend_path = $lib_path
                . DIRECTORY_SEPARATOR . 'src'
                . DIRECTORY_SEPARATOR . 'File'
                . DIRECTORY_SEPARATOR . 'Backend';

            if(!file_exists($backend_path))
                continue;

            $dir = dir($backend_path);

            while(($file = $dir->read()) !== false){

                if(substr($file, -4) !== '.php' || substr($file, 0, 1) === '.' || substr($file, 0, 1) === '_')
                    continue;


                $source = ake(pathinfo($file), 'filename');

                $class = 'Hazaar\\File\\Backend\\' . $source;

                if(!class_exists($class))
                    continue;

                $backend = array(
                    'name'  => strtolower($source),
                    'label' => $class::label(),
                    'class' => $class
                );

                $backends[] = $backend;

            }

        }

        return $backends;

    }

    /**
     * Loads a Manager class by name as configured in media.json config
     *
     * @param mixed $name The name of the media source to load
     *
     * @param array $options Extra options to override configured options
     */
    static public function select($name, $options = null){

        $config = new Application\Config('media');

        if(!$config->has($name))
            return false;

        $source = new \Hazaar\Map(\Hazaar\File\Manager::$default_config, $config->get($name));

        if($options !== null)
            $source->options->extend($options);

        $manager = new \Hazaar\File\Manager($source->type, $source->get('options'), $name);

        if($source->failover === true || $config->global->failover === true)
            $manager->activateFailover();

        return $manager;

    }

    public function refresh($reset = FALSE) {

        return $this->backend->refresh($reset);

    }

    static public function configure($backend, $options) {

        if(! $options instanceof \Hazaar\Map)
            $options = new \Hazaar\Map($options);

        Manager::$default_backend = $backend;

        Manager::$default_backend_options = $options;

    }

    public function activateFailover(){

        $this->failover = new Manager('local', array(
            'root' => Application::getInstance()->runtimePath('media' . DIRECTORY_SEPARATOR . $this->name, true)
        ));

    }

    public function failoverSync(){

        if(!($this->failover && $this->backend->is_dir('/')))
            return false;

        $clean = array('dir' => array(), 'file' => array());

        $names = $this->failover->find();

        foreach($names as $name){

            $item = $this->failover->get($name);

            if($item->is_dir()){

                if($this->backend->exists($name) || $this->backend->mkdir($name))
                    $clean['dir'][] = $name;

            }elseif($item instanceof \Hazaar\File){

                $this->backend->write($name, $item->get_contents(), $item->mime_content_type(), true);

                $clean['file'][] = $name;

            }

        }

        foreach($clean['file'] as $file)
            $this->failover->unlink($file);

        foreach($clean['dir'] as $dir)
            $this->failover->rmdir($dir);
        
        return true;

    }

    public function getBackend() {

        return $this->backend;

    }

    public function getBackendName() {

        return $this->backend_name;

    }

    public function setOption($name, $value) {

        $this->options[$name] = $value;

    }

    public function getOption($name) {

        return ake($this->options, $name);

    }
    
    public function fixPath($path, $file = NULL) {

        $path = '/' . trim(str_replace('\\', '/', $path), '/');

        if($file)
            $path .= ((substr($path, -1, 1) !== '/') ? '/' : NULL) . $file;

        return $path;

    }
    
    /*
     * Authorisation Methods
     *
     * These are used by certain backends that require OAuth-like user authorisation
     */
    public function authorise($redirect_uri = NULL) {

        if(! method_exists($this->backend, 'authorise')
            || $this->backend->authorised())
            return TRUE;

        $result = $this->backend->authorise($redirect_uri);

        if($result === FALSE) {

            header('Location: ' . $this->backend->buildAuthUrl($redirect_uri));

            exit;

        }

        return $result;

    }

    /**
     * Alias to authorise() which is the CORRECT spelling.
     *
     * @param array $options
     * @return bool
     */
    public function authorize($options = array()) {

        return $this->authorise($options);

    }

    public function authorised() {

        if(! method_exists($this->backend, 'authorised'))
            return TRUE;

        return $this->backend->authorised();

    }

    public function authorized() {

        return $this->authorised();

    }

    public function reset() {

        if(! method_exists($this->backend, 'reset'))
            return TRUE;

        return $this->backend->reset();

    }

    public function buildAuthURL($callback_url) {

        if(! method_exists($this->backend, 'buildAuthURL'))
            return FALSE;

        return $this->backend->buildAuthURL($callback_url);

    }

    /**
     * Return a file object for a given path.
     * 
     * @param mixed $path The path to a file object
     * 
     * @return File The File object.
     */
    public function get($path) {

        return new \Hazaar\File($this->fixPath($path), $this);

    }

    /**
     * Return a directory object for a given path.
     *
     * @param mixed $path The path to a directory object
     *
     * @return Dir The directory object.
     */
    public function dir($path = '/') {

        return new Dir($this->fixPath($path), $this);

    }

    public function toArray($path, $sort = SCANDIR_SORT_ASCENDING, $allow_hidden = false){

        return $this->backend->scandir($this->fixPath($path), $sort, $allow_hidden);

    }

    public function find($search = NULL, $path = '/', $case_insensitive = false) {

        if(method_exists($this->backend, 'find'))
            return $this->backend->find($search, $path, $case_insensitive);

        $dir = $this->dir($path);

        $list = array();

        while(($file = $dir->read()) != FALSE) {

            if($file->is_dir()) {

                $list[] = $file->fullpath();

                $list = array_merge($list, $this->find($search, $file->fullpath()));

            } else {

                if($search) {

                    $first = substr($search, 0, 1);

                    if((ctype_alnum($first) || $first == '\\') == false
                        && $first == substr($search, -1, 1)) {

                        if(! preg_match($search . ($case_insensitive ? 'i' : ''), $file->basename()))
                            continue;

                    } elseif(! fnmatch($search, $file->basename(), ($case_insensitive ? FNM_CASEFOLD : 0))) {

                        continue;

                    }

                }

                $list[] = $file->fullpath();

            }

        }

        return $list;

    }

    public function exists($path) {

        return $this->backend->exists($this->fixPath($path));

    }

    public function read($file, $offset = NULL, $maxlen = NULL) {

        $bytes = null;

        if($this->failover && $this->failover->exists($file)){

            $f = $this->failover->get($file); //Make the file as a directory to store logs

            $bytes = $f->get_contents();

        }else{

            $bytes = $this->backend->read($this->fixPath($file), $offset, $maxlen);

        }

        return $bytes;

    }

    public function write($file, $data, $content_type = null, $overwrite = FALSE) {

        $result = false;

        try{

            $result = $this->backend->write($this->fixPath($file), $data, $content_type, $overwrite);

        }catch(Backend\Exception\Offline $e){

            if(!$this->failover)
                throw $e;

            $this->in_failover = true;

            $f = $this->failover->get($file); //Make the file as a directory to store logs

            if($f->is_dir())
                throw new \Exception('File exists and is not a file!');

            if(!$f->parent()->exists())
                $f->parent()->create(true);

            $result = $f->put_contents($data) > 0;

        }

        return $result;

    }

    public function upload($path, $file, $overwrite = FALSE) {

        return $this->backend->upload($this->fixPath($path), $file, $overwrite);

    }

    public function store($source, $target) {

        dir('revamp this');

        $file = new \Hazaar\File($source);

        if(substr(trim($target), -1, 1) != '/')
            $target .= '/';

        return $this->backend->write($target . $file->filename(), $file->getContents(), $file->getMimeType());

    }

    public function search($query) {

        return $this->backend->search($query);

    }

    private function deepCopy($src, $dst, $srcManager, $progressCallback = NULL) {

        $dstPath = rtrim($dst, '/') . '/' . basename($src);

        if(! $this->exists($dstPath))
            $this->mkdir($dstPath);

        $dir = new Dir($src, $srcManager, $this);

        while(($f = $dir->read()) != FALSE) {

            if($progressCallback)
                call_user_func_array($progressCallback, array('copy', $f));

            if($f->type() == 'dir')
                $this->deepCopy($f->fullpath(), $dstPath, $srcManager, $progressCallback);

            else
                $this->backend->write($this->fixPath($dstPath, $f->basename()), $f->get_contents(), $f->mime_content_type());

        }

        return TRUE;

    }

    /*
     * File Operations
     */
    public function copy($src, $dst, $srcManager = NULL, $recursive = FALSE, $progressCallback = NULL) {

        if($srcManager !== $this) {

            $file = new \Hazaar\File($src, $srcManager);

            switch($file->type()) {
                case 'file':

                    return $this->backend->write($this->fixPath($dst, $file->basename()), $file->get_contents(), $file->mime_content_type());

                    break;

                case 'dir':

                    if(! $recursive)
                        return FALSE;

                    return $this->deepCopy($file->fullpath(), $dst, $srcManager, $progressCallback);

                    break;

            }

            throw new \Hazaar\Exception("Copy of source type '" . $file->type() . "' between different sources is currently not supported");

        }

        return $this->backend->copy($src, $dst, $recursive);

    }

    public function move($src, $dst, $srcManager = NULL) {

        if($srcManager instanceof Manager && $srcManager->getBackend() !== $this->backend) {

            $file = $srcManager->get($src);

            switch($file->type()) {
                case 'file':

                    $result = $this->backend->write($this->fixPath($dst, $file->basename()), $file->get_contents(), $file->mime_content_type());

                    if($result)
                        return $srcManager->unlink($src);

                    return FALSE;

                    break;

                case 'dir':

                    $result = $this->deepCopy($file->fullpath(), $dst, $srcManager->getBackend());

                    if($result)
                        return $srcManager->rmdir($src, TRUE);

                    return FALSE;

                    break;

            }

            throw new \Hazaar\Exception("Move of source type '" . $file->type() . "' between different sources is currently not supported.");

        }

        return $this->backend->move($src, $dst);

    }

    public function mkdir($path) {

        return $this->backend->mkdir($this->fixPath($path));

    }

    public function rmdir($path, $recurse = FALSE) {

        return $this->backend->rmdir($this->fixPath($path), $recurse);

    }

    public function unlink($path) {

        return $this->backend->unlink($this->fixPath($path));

    }

    public function isEmpty($path){

        $files = $this->backend->scandir($this->fixPath($path));

        return (count($files) === 0);

    }

    public function filesize($path) {

        return $this->backend->filesize($this->fixPath($path));

    }

    /*
     * Advanced backend dependant features
     */
    public function fsck() {

        if(method_exists($this->backend, 'fsck')) {

            return $this->backend->fsck();

        }

        return TRUE;

    }

    public function thumbnail($path, $width = 100, $height = 100, $format = 'jpeg') {

        if(method_exists($this->backend, 'thumbnail')) {

            return $this->backend->thumbnail($path, $width, $height, $format);

        }

        return FALSE;

    }

    public function link($src, $dst) {

        if(method_exists($this->backend, 'link'))
            return $this->backend->link($src, $dst);

        return FALSE;

    }

    public function share($path) {

        if(method_exists($this->backend, 'share'))
            return $this->backend->share($path);

        return FALSE;

    }

    public function get_meta($path, $key = NULL) {

        return $this->backend->get_meta($path, $key);

    }

    public function set_meta($path, $values) {

        return $this->backend->set_meta($path, $values);

    }

    public function uri($path = null){

        return new Application\Url('media', $this->name . ($path ? '/' . ltrim($path, '/') : ''));

    }

    //Get a directory listing
    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE, $relative_path = null){

        if(($items = $this->backend->scandir($this->fixPath($path))) === false)
            return false;

        if(!$relative_path) 
            $relative_path = rtrim($path, '/') . '/';

        foreach($items as &$item){

            $fullpath = $this->fixPath($path, $item);

            $item = ($this->is_dir($fullpath) ? new \Hazaar\File\Dir($fullpath, $this, $relative_path) : new \Hazaar\File($fullpath, $this, $relative_path));

        }

        if($this->failover && $this->failover->exists($path))
            $items = array_merge($items, $this->failover->scandir($path, $regex_filter, $show_hidden));
        
        return $items;
        
    }

    public function touch($path){

        return $this->backend->touch($this->fixPath($path));
        
    }

    public function realpath($path){

        return $this->backend->realpath($this->fixPath($path));
        
    }

    public function is_readable($path){

        return $this->backend->is_readable($this->fixPath($path));
        
    }

    public function is_writable($path){

        return $this->backend->is_writable($this->fixPath($path));
        
    }

    //TRUE if path is a directory
    public function is_dir($path){

        return $this->backend->is_dir($this->fixPath($path));
        
    }

    //TRUE if path is a symlink
    public function is_link($path){

        return $this->backend->is_link($this->fixPath($path));
        
    }

    //TRUE if path is a normal file
    public function is_file($path){

        return $this->backend->is_file($this->fixPath($path));
        
    }

    //Returns the file type
    public function filetype($path){

        return $this->backend->filetype($this->fixPath($path));
        
    }

    //Returns the file create time
    public function filectime($path){

        return $this->backend->filectime($this->fixPath($path));
        
    }

    //Returns the file modification time
    public function filemtime($path){

        return $this->backend->filemtime($this->fixPath($path));
        
    }

    //Returns the file access time
    public function fileatime($path){

        return $this->backend->fileatime($this->fixPath($path));
        
    }

    public function fileperms($path){

        return $this->backend->fileperms($this->fixPath($path));
        
    }

    public function chmod($path, $mode){

        return $this->backend->chmod($this->fixPath($path), $mode);
        
    }

    public function chown($path, $user){

        return $this->backend->chown($this->fixPath($path), $user);
        
    }

    public function chgrp($path, $group){

        return $this->backend->chgrp($this->fixPath($path), $group);
        
    }

    public function mime_content_type($path){

        return $this->backend->mime_content_type($this->fixPath($path));

    }

    public function md5Checksum($path){

        return $this->backend->md5Checksum($this->fixPath($path));

    }

    public function preview_uri($path){

        return $this->backend->preview_uri($this->fixPath($path));
        
    }

    public function direct_uri($path){

        return $this->backend->direct_uri($this->fixPath($path));
        
    }

}

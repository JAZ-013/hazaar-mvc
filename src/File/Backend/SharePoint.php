<?php

namespace Hazaar\File\Backend;

class SharePoint extends \Hazaar\Http\Client implements _Interface {

    public  $separator  = '/';

    private $options;

    static public function label(){

        return "Microsoft SharePoint";

    }
    
    public function __construct($options) {

        parent::__construct();

        $this->options = new \Hazaar\Map(array('webURL'   => '', 'username' => '', 'password' => ''), $options);

        if(! ($this->options->isEmpty('webURL') && $this->options->isEmpty('username') && $this->options->isEmpty('password')))
            throw new Exception\DropboxError('SharePoint filesystem backend requires a webURL, username and password.');

    }

    public function reload() {

    }

    public function reset() {

    }

    public function __destruct() {

    }

    public function authorise($redirect_uri = NULL) {

        return false;

    }

    public function authorised() {

        return false;

    }

    public function refresh($reset = FALSE) {

    }

    //Get a directory listing
    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE) {

    }

    //Check if file/path exists
    public function exists($path) {

        return FALSE;

    }

    public function realpath($path) {

        return false;

    }

    public function is_readable($path) {

        return false;

    }

    public function is_writable($path) {

        return false;

    }

    //TRUE if path is a directory
    public function is_dir($path) {

        return false;

    }

    //TRUE if path is a symlink
    public function is_link($path) {

        return false;

    }

    //TRUE if path is a normal file
    public function is_file($path) {

        return false;

    }

    //Returns the file type
    public function filetype($path) {

        return false;

    }

    //Returns the file modification time
    public function filectime($path) {

        return false;

    }

    //Returns the file modification time
    public function filemtime($path) {

        return false;

    }

    //Returns the file modification time
    public function fileatime($path) {

        return false;

    }

    public function filesize($path) {

        return false;

    }

    public function fileperms($path) {

        return false;

    }

    public function chmod($path, $mode) {

        return false;

    }

    public function chown($path, $user) {

        return false;

    }

    public function chgrp($path, $group) {

        return false;

    }

    public function unlink($path) {

        return false;

    }

    public function mime_content_type($path) {

        return false;

    }

    public function md5Checksum($path) {

        return false;

    }

    public function thumbnail($path, $params = array()) {

        return FALSE;

    }

    //Create a directory
    public function mkdir($path) {

        return false;

    }

    public function rmdir($path, $recurse = false) {

        return false;

    }

    //Copy a file from src to dst
    public function copy($src, $dst, $recursive = FALSE) {

        return false;

    }

    public function link($src, $dst) {

        return false;

    }

    //Move a file from src to dst
    public function move($src, $dst) {

        return false;

    }

    //Read the contents of a file
    public function read($path) {

        return false;

    }

    //Write the contents of a file
    public function write($file, $data, $content_type, $overwrite = FALSE) {

        return false;

    }

    public function upload($path, $file, $overwrite = TRUE) {

        return false;

    }

    public function set_meta($path, $values) {

        return false;

    }

    public function get_meta($path, $key = NULL) {

        return false;

    }

    public function preview_uri($path) {

        return false;

    }

    public function direct_uri($path) {

        return false;

    }

}

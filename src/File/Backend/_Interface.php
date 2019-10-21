<?php

namespace Hazaar\File\Backend;

interface _Interface {

    static public function label();

    public function refresh($reset = FALSE);

    //Get a directory listing
    public function scandir($path, $regex_filter = NULL, $show_hidden = FALSE);

    public function touch($path);

    //Check if file/path exists
    public function exists($path);

    public function realpath($path);

    public function is_readable($path);

    public function is_writable($path);

    //TRUE if path is a directory
    public function is_dir($path);

    //TRUE if path is a symlink
    public function is_link($path);

    //TRUE if path is a normal file
    public function is_file($path);

    //Returns the file type
    public function filetype($path);

    //Returns the file create time
    public function filectime($path);

    //Returns the file modification time
    public function filemtime($path);

    //Returns the file access time
    public function fileatime($path);

    public function filesize($path);

    public function fileperms($path);

    public function chmod($path, $mode);

    public function chown($path, $user);

    public function chgrp($path, $group);

    public function unlink($path);

    public function mime_content_type($path);

    public function md5Checksum($path);

    public function thumbnail($path, $params = array());

    //Create a directory
    public function mkdir($path);

    public function rmdir($path, $recurse = false);

    //Copy a file from src to dst
    public function copy($src, $dst, $recursive = FALSE);

    //Create a link to a file
    public function link($src, $dst);

    //Move a file from src to dst
    public function move($src, $dst);

    //Read the contents of a file
    public function read($file);

    //Write the contents of a file
    public function write($file, $data, $content_type, $overwrite = FALSE);

    //Upload a file that was uploaded with a POST
    public function upload($path, $file, $overwrite = FALSE);

    public function set_meta($path, $values);

    public function get_meta($path, $key = NULL);

    public function preview_uri($path);

    public function direct_uri($path);

}


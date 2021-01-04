<?php

namespace Hazaar\File;

interface _Interface {

    public function backend();

    public function getBackend();

    public function getManager();

    public function set_meta($values);

    public function get_meta($key = NULL);

    public function toString();

    public function __tostring();

    public function basename();

    public function dirname();

    public function name();

    public function fullpath();

    public function extension();

    public function size();

    public function exists();

    public function realpath();

    public function is_readable();

    public function is_writable();

    public function is_file();

    public function is_dir();

    public function is_link();

    public function dir($child = null);

    public function parent();

    public function type();

    public function ctime();

    public function mtime();

    public function touch();

    public function atime();

    public function unlink();

    public function media_uri($set_path = null);

    public function rename($newname, $overwrite = false);

}

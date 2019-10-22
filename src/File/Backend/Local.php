<?php

namespace Hazaar\File\Backend;

class Local implements _Interface {

    public  $separator  = DIRECTORY_SEPARATOR;

    private $options;

    static  $mime_types = null;

    private $meta = array();

    static public function label(){

        return 'Local Filesystem Storage';

    }

    static public function lookup_content_type($extension){

        if(! is_array(self::$mime_types)) {

            self::$mime_types = array();

            $mt_file = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'mime.types');

            $h = fopen($mt_file, 'r');

            while($line = fgets($h)) {

                $line = trim($line);

                if(substr($line, 0, 1) == '#' || strlen($line) == 0)
                    continue;

                if(preg_match('/^(\S*)\s*(.*)$/', $line, $matches)) {

                    $extens = explode(' ', $matches[2]);

                    foreach($extens as $value) {
                        if($value)
                            self::$mime_types[strtolower($value)] = $matches[1];
                    }

                }

            }

            fclose($h);

        }

        return ake(self::$mime_types, $extension);

    }

    public function __construct($options = array()) {

        $root = ((substr(PHP_OS, 0, 3) == 'WIN') ? substr(APPLICATION_PATH, 0, 3) : DIRECTORY_SEPARATOR);

        $this->options = new \Hazaar\Map(array('display_hidden' => false, 'root' => $root), $options);

    }

    public function refresh($reset = false) {

        return true;

    }

    public function resolvePath($path, $file = null) {

        $path = \Hazaar\Loader::fixDirectorySeparator($path);

        $base = $this->options->get('root', DIRECTORY_SEPARATOR);

        if($path == DIRECTORY_SEPARATOR)
            $path = $base;
        elseif(substr($path, 1, 1) !== ':') //Not an absolute Windows path
            $path = $base . ((substr($base, -1, 1) != DIRECTORY_SEPARATOR) ? DIRECTORY_SEPARATOR : null) . trim($path, DIRECTORY_SEPARATOR);

        if($file)
            $path .= ((strlen($path) > 1) ? DIRECTORY_SEPARATOR : null) . trim($file, DIRECTORY_SEPARATOR);

        return $path;

    }

    public function scandir($path, $regex_filter = null, $show_hidden = false) {

        $list = array();

        $path = $this->resolvePath($path);

        if(! is_dir($path))
            return false;

        $dir = dir($path);

        while(($file = $dir->read()) != false) {

            if($file == '.metadata')
                continue;

            if(($show_hidden == false && substr($file, 0, 1) == '.') || $file == '.' || $file == '..')
                continue;

            if($regex_filter && ! preg_match($regex_filter, $file))
                continue;

            $list[] = $file;

        }

        return $list;

    }

    public function read($file, $offset = -1, $maxlen = null) {

        $file = $this->resolvePath($file);

        $ret = false;

        if(file_exists($file)) {

            if($offset >= 0) {

                if($maxlen) {

                    $ret = file_get_contents($file, false, null, $offset, $maxlen);

                } else {

                    $ret = file_get_contents($file, false, null, $offset);

                }

            } else {

                $ret = file_get_contents($file);

            }

        }

        return $ret;

    }

    public function write($file, $data, $content_type, $overwrite = true) {

        $file = $this->resolvePath($file);

        if(file_exists($file) && $overwrite == false)
            return false;

        if(($ret = file_put_contents($file, $data)) !== false)
            return true;

        return false;

    }

    public function upload($path, $file, $overwrite = true) {

        $path = \Hazaar\Loader::fixDirectorySeparator($path);

        $fullPath = $this->resolvePath(rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file['name']);

        if(file_exists($fullPath) && $overwrite == false)
            return false;

        return move_uploaded_file($file['tmp_name'], $fullPath);

    }

    public function copy($src, $dst, $recursive = false) {

        $src = rtrim(\Hazaar\Loader::fixDirectorySeparator($src), DIRECTORY_SEPARATOR);

        $dst = rtrim(\Hazaar\Loader::fixDirectorySeparator($dst), DIRECTORY_SEPARATOR);

        if($this->is_file($src)) {

            $rSrc = $this->resolvePath($src);

            $rDst = $this->resolvePath($dst);

            if($this->is_dir($dst))
                $rDst = $this->resolvePath($dst, basename($src));

            $ret = copy($rSrc, $rDst);

            if($ret) {

                if($srcMeta = $this->meta($rSrc))
                    $this->meta[$rDst] = array($srcMeta, true);

                return true;

            }

        } elseif($this->is_dir($src) && $recursive) {

            $dst .= DIRECTORY_SEPARATOR . basename($src);

            if(! $this->exists($dst))
                $this->mkdir($dst);

            $dir = $this->scandir($src);

            foreach($dir as $file) {

                $fullpath = $src . DIRECTORY_SEPARATOR . $file;

                if($this->is_dir($fullpath))
                    $this->copy($fullpath, $dst, true);

                else
                    $this->copy($fullpath, $dst);

            }

            return true;

        }

        return false;

    }

    public function link($src, $dst) {

        $rSrc = $this->resolvePath($src);

        $rDst = $this->resolvePath($dst);

        if(file_exists($rDst))
            return false;

        return link($rSrc, $rDst);

    }

    public function move($src, $dst) {

        $rSrc = $this->resolvePath($src);

        $rDst = $this->resolvePath($dst);

        if(is_dir($rDst))
            $rDst = $this->resolvePath($dst, basename($src));

        if(substr($dst, 0, strlen($src)) == $src)
            return false;

        $ret = rename($rSrc, $rDst);

        if($ret) {

            if($srcMeta = $this->meta($rSrc)) {

                $this->meta[$rDst] = array($srcMeta, true);

                unset($this->meta[$rSrc]);

            }

            return true;

        }

        return false;

    }

    public function unlink($path) {

        $realPath = $this->resolvePath($path);

        if((file_exists($realPath) || is_link($realPath)) && ! is_dir($realPath)) {

            $ret = unlink($realPath);

            if($ret) {

                $metafile = dirname($realPath) . DIRECTORY_SEPARATOR . '.metadata' . DIRECTORY_SEPARATOR . basename($realPath);

                if(file_exists($metafile))
                    unlink($metafile);

                return true;

            }

        }

        return false;

    }

    public function mime_content_type($path) {

        $path = $this->resolvePath($path);

        if(! file_exists($path))
            return null;

        $info = pathinfo($path);

        if($extension = strtolower(ake($info, 'extension')))
            return self::lookup_content_type($extension);

        if(function_exists('finfo_open')){

            $const = defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;

            $mime = finfo_open($const);

            if(! empty($mime)) {

                if($type = finfo_file($mime, $path))
                    return $type;

            }

        }

        return 'text/text';

    }

    public function md5Checksum($path) {

        if($path = $this->resolvePath($path))
            return md5_file($path);

        return null;

    }

    public function thumbnail($path, $params = array()) {

        return false;

    }

    /**
     * Makes directory
     * @param mixed $path
     * @return bool
     */
    public function mkdir($path) {

        $path = $this->resolvePath($path);

        if(file_exists($path))
            return false;

        return mkdir($path);

    }

    /**
     * Removes directory
     * @param mixed $path
     * @param mixed $recurse
     * @return bool
     */
    public function rmdir($path, $recurse = false) {

        $realPath = $this->resolvePath($path);

        if(! is_dir($realPath))
            return false;

        if($recurse) {

            $dir = $this->scandir($path, null, true);

            foreach($dir as $file) {

                if($file == '.' || $file == '..')
                    continue;

                $fullpath = $path . DIRECTORY_SEPARATOR . $file;

                if($this->is_dir($fullpath)) {

                    $this->rmdir($fullpath, true);

                } else {

                    $this->unlink($fullpath);

                }

            }

        }

        if($path == DIRECTORY_SEPARATOR)
            return true;

        //Hack to get PHP on windows to let go of the now empty directory so that we can remove it
        $handle = opendir($realPath);

        closedir($handle);

        return rmdir($realPath);

    }

    /**
     * Checks whether a file or directory exists
     * @param mixed $path
     * @return bool
     */
    public function exists($path) {

        return file_exists($this->resolvePath($path));

    }

    /**
     * Returns canonicalized absolute pathname
     * @param mixed $path
     * @return string
     */
    public function realpath($path) {

        return realpath($this->resolvePath($path));

    }

    /**
     * true if path is a readable
     * @param mixed $path
     * @return bool
     */
    public function is_readable($path) {

        return is_readable($this->resolvePath($path));

    }

    /**
     * true if path is writable
     * @param mixed $path
     * @return bool
     */
    public function is_writable($path) {

        return is_writable($this->resolvePath($path));

    }

    /**
     * true if path is a directory
     * @param mixed $path
     * @return bool
     */
    public function is_dir($path) {

        return is_dir($this->resolvePath($path));

    }

    /**
     * true if path is a symlink
     * @param mixed $path
     * @return bool
     */
    public function is_link($path) {

        return is_link($this->resolvePath($path));

    }

    /**
     * true if path is a normal file
     * @param mixed $path
     * @return bool
     */
    public function is_file($path) {

        return is_file($this->resolvePath($path));

    }

    /**
     * Returns the file type
     * @param mixed $path
     * @return string
     */
    public function filetype($path) {

        return filetype($this->resolvePath($path));

    }

    /**
     * Returns the file create time
     * @param mixed $path
     * @return int
     */
    public function filectime($path) {

        return filectime($this->resolvePath($path));

    }

    /**
     * Returns the file modification time
     * @param mixed $path
     * @return int
     */
    public function filemtime($path) {

        return filemtime($this->resolvePath($path));

    }

    /**
     * Sets access and modification time of file
     *
     * @param mixed $path
     *
     * @return bool
     */
    public function touch($path){

        $path = $this->resolvePath($path);

        return touch($path);
    }

    //Returns the file access time
    public function fileatime($path) {

        return fileatime($this->resolvePath($path));

    }

    public function filesize($path) {

        return filesize($this->resolvePath($path));

    }

    public function fileperms($path) {

        return fileperms($this->resolvePath($path));

    }

    public function chmod($path, $mode) {

        return chmod($this->resolvePath($path), $mode);

    }

    public function chown($path, $user) {

        return chown($this->resolvePath($path), $user);

    }

    public function chgrp($path, $group) {

        return chgrp($this->resolvePath($path), $group);

    }

    private function meta($fullpath) {

        $metafile = dirname($fullpath) . DIRECTORY_SEPARATOR . '.metadata';

        if(array_key_exists($metafile, $this->meta))
            return $this->meta[$metafile];

        $this->meta[$metafile] = $db = new \Hazaar\Btree(dirname($fullpath) . DIRECTORY_SEPARATOR . '.metadata');

        if(!($meta = $db->get($fullpath))){

            $meta = array();

            /**
             * Generate Image Meta
             */
            if(substr($this->mime_content_type($fullpath), 0, 5) == 'image') {

                $size = getimagesize($fullpath);

                $meta['width'] = $size[0];

                $meta['height'] = $size[1];

                $meta['bits'] = ake($size, 'bits');

                $meta['channels'] = ake($size, 'channels');

            }

            $db->set($fullpath, $meta);

        }

        return $db;

    }

    public function set_meta($path, $values) {

        $fullpath = $this->resolvePath($path);

        $db = $this->meta($fullpath);

        $meta = $db->get($fullpath);

        if(is_array($meta) && is_array($values))
            $values = array_merge($meta, $values);

        $db->set($fullpath, $values);

        return true;

    }

    public function get_meta($path, $key = null) {

        $fullpath = $this->resolvePath($path);

        $db = $this->meta($fullpath);

        $meta = $db->get($fullpath);

        if($key)
            return ake($meta, $key);

        return $meta;

    }

    public function preview_uri($path) {

        return false;

    }

    public function direct_uri($path) {

        return false;

    }

}

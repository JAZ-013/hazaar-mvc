<?php

namespace Hazaar\File;

class Dir {

    private $path;

    private $backend;

    private $manager;

    private $files;

    private $allow_hidden = FALSE;

    private $__media_uri;

    private $relative_path;

    function __construct($path, Backend\_Interface $backend = NULL, Manager $manager = null, $relative_path = null) {

        if(! $backend)
            $backend = new Backend\Local(array('root' => ((substr(PHP_OS, 0, 3) == 'WIN') ? substr(APPLICATION_PATH, 0, 3) : '/')));

        $this->backend = $backend;

        $this->manager = $manager;

        $this->path = $this->fixPath($path);

        $this->relative_path = rtrim(str_replace('\\', '/', $relative_path), '/');

    }

    public function fixPath($path, $file = NULL) {

        if($file)
            $path .= ((strlen($path) > 1) ? $this->backend->separator : NULL) . $file;

        $sep = (($this->backend->separator === '/') ? '\\' : '/');

        return str_replace($sep, $this->backend->separator, $path);

    }

    public function __toString(){

        return $this->path();

    }

    public function path($suffix = NULL) {

        return $this->fixPath($this->path, $suffix);

    }

    public function fullpath($suffix = null){

        return $this->path($suffix);

    }

    public function realpath($suffix = NULL) {

        return $this->backend->realpath($this->fixPath($this->path, $suffix));

    }

    public function dirname(){

        return  str_replace('\\', '/', dirname($this->fixPath($this->path)));

    }

    public function basename(){

        return basename($this->fixPath($this->path));

    }

    public function mtime(){

        return $this->backend->filemtime($this->fixPath($this->path));

    }

    public function size(){

        return $this->backend->filesize($this->fixPath($this->path));

    }

    public function type(){

        return $this->backend->filetype($this->fixPath($this->path));

    }

    public function exists($filename = null) {

        return $this->backend->exists(rtrim($this->path, '/') . ($filename ? '/' . $filename : ''));

    }

    public function is_readable() {

        return $this->backend->is_readable($this->path);

    }

    public function is_writable() {

        return $this->backend->is_writable($this->path);

    }

    public function allow_hidden($toggle = TRUE) {

        $this->allow_hidden = $toggle;

    }

    public function create($recursive = FALSE) {

        if(! $recursive)
            return $this->backend->mkdir($this->path);

        $parents = array();

        $last = $this->path;

        while(!$this->backend->exists($last)) {

            $parents[] = $last;

            //Gets dirname an ensures separator is a forward slash (/).
            $last = str_replace(DIRECTORY_SEPARATOR, $this->backend->separator, dirname($last));

            if($last == $this->backend->separator)
                break;

        }

        while($parent = array_pop($parents)) {

            if(! $this->backend->mkdir($parent))
                return FALSE;

        }

        return TRUE;

    }

    /**
     * Delete the directory, optionally removing all it's contents.
     *
     * Executing this method will simply delete or "unlink" the directory.  Normally it must be empty
     * to succeed.  However specifying the $recursive parameter as TRUE will delete everything inside
     * the directory, recursively (obviously).
     *
     * @param mixed $recursive
     * @return mixed
     */
    public function delete($recursive = FALSE) {

        return $this->backend->rmdir($this->path, $recursive);

    }


    public function isEmpty(){

        $files = $this->backend->scandir($this->path);

        return (count($files) === 0);

    }

    /**
     * Empty a directory of all it's contents.
     *
     * This is the same as calling delete(true) except that the directory itself is not deleted.
     *
     * By default hidden files are not deleted.  This is for protection.  You can choose to delete them
     * as well by setting $include_hidden to true.
     *
     * @param mixed $include_hidden Also delete hidden files.
     *
     * @return boolean
     */
    public function empty($include_hidden = false){

        $org = null;

        if($include_hidden && !$this->allow_hidden){

            $org = $this->allow_hidden;

            $this->allow_hidden = true;

        }

        $this->rewind();

        while($file = $this->read())
            $file->unlink();

        if($org !== null)
            $this->allow_hidden = $org;

        return true;

    }

    public function close() {

        $this->files = NULL;

    }

    public function read($regex_filter = NULL) {

        if(! is_array($this->files)) {

            $this->files = $this->backend->scandir($this->path, $regex_filter, $this->allow_hidden);

            if(($file = $this->rewind()) == FALSE)
                return FALSE;

        } else {

            if(($file = next($this->files)) === FALSE)
                return FALSE;

        }

        $relative_path = $this->relative_path ? $this->relative_path : $this->path;

        if($this->backend->is_dir($this->fixPath($this->path, $file)))
            return new \Hazaar\File\Dir($this->fixPath($this->path, $file), $this->backend, $this->manager, $relative_path);

        return new \Hazaar\File($this->fixPath($this->path, $file), $this->backend, $this->manager, $relative_path);

    }

    public function rewind() {

        if(! is_array($this->files))
            return FALSE;

        return reset($this->files);

    }

    /**
     * Find files in the current path optionally recursing into sub directories.
     *
     * @param string $pattern The pattern to match against.  This can be either a wildcard string, such as
     *                                  "*.txt" or a regex pattern.  Regex is detected if the string is longer than a
     *                                  single character and first character is the same as the last.
     * @param bool $recursive If TRUE the search will recurse into sub directories.
     * @param bool $case_sensitive If TRUE character case will be honoured.
     * @param int $depth Recursion depth.  NULL will always recurse.  0 will prevent recursion.
     * @return array    Returns an array of matches files.
     */
    public function find($pattern, $show_hidden = FALSE, $case_sensitive = TRUE, $depth = null) {

        $start = rtrim($this->path, $this->backend->separator) . $this->backend->separator;

        $list = array();

        if(!($dir = $this->backend->scandir($start, NULL, TRUE)))
            return null;

        $relative_path = $this->relative_path ? $this->relative_path : $this->path;

        foreach($dir as $file) {

            if(($show_hidden === FALSE && substr($file, 0, 1) == '.'))
                continue;

            $item = $start . $file;

            if($this->backend->is_dir($item) && ($depth === null || $depth > 0)) {

                $subdir = new \Hazaar\File\Dir($item, $this->backend, $this->manager, $relative_path);

                if($subdiritems = $subdir->find($pattern, $show_hidden, $case_sensitive, (($depth === null) ? $depth : $depth - 1)))
                    $list = array_merge($list, $subdiritems);

            }else{

                if(strlen($pattern) > 1 && substr($pattern, 0, 1) == substr($pattern, -1, 1)) {

                    if(preg_match($pattern . ($case_sensitive ? NULL : 'i'), $file) == 0)
                        continue;

                } elseif(! fnmatch($pattern, $file, $case_sensitive ? 0 : FNM_CASEFOLD))
                    continue;

                $list[] = new \Hazaar\File($item, $this->backend, $this->manager, $relative_path);

            }

        }

        return $list;

    }

    public function copyTo($target, $recursive = FALSE, $transport_callback = NULL) {

        $target = $this->fixPath($target);

        if($this->backend->exists($target)) {

            if(! $this->backend->is_dir($target))
                return FALSE;

        } else if(! $this->backend->mkdir($target))
            return FALSE;

        $dir = $this->backend->scandir($this->path, NULL, TRUE);

        foreach($dir as $cur) {

            if($cur == '.' || $cur == '..')
                continue;

            $sourcePath = $this->fixPath($this->path, $cur);

            $targetPath = $this->fixPath($target, $cur);

            if(is_array($transport_callback) && count($transport_callback) == 2) {

                /*
                 * Call the transport callback.  If it returns true, do the copy.  False means do not copy this file.
                 * This gives the callback a chance to perform the copy itself in a special way, or ignore a
                 * file/directory
                 */
                if(! call_user_func_array($transport_callback, array($sourcePath, $targetPath)))
                    continue;

            }

            if($this->backend->is_dir($sourcePath)) {

                if($recursive) {

                    $dir = new Dir($sourcePath, $this->backend, $this->manager);

                    $dir->copyTo($targetPath, $recursive, $transport_callback);

                }

            } else {

                $perms = $this->backend->fileperms($sourcePath);

                $this->backend->copy($sourcePath, $targetPath);

                $this->backend->chmod($targetPath, $perms);

            }

        }

        return TRUE;

    }

    public function get($child, $force_dir = false) {

        $path = $this->path($child);

        if($force_dir === true || (file_exists($path) && is_dir($path)))
            return new \Hazaar\File\Dir($path, $this->backend, $this->manager);

        $relative_path = $this->relative_path ? $this->relative_path : $this->path;

        return new \Hazaar\File($this->path($child), $this->backend, $this->manager, $relative_path);

    }

    public function dir($child) {

        $relative_path = $this->relative_path ? $this->relative_path : $this->path;

        return new \Hazaar\File\Dir($this->path($child), $this->backend, $this->manager, $relative_path);

    }

    public function toArray(){

        return $this->backend->scandir($this->path, null, $this->allow_hidden);

    }

    /**
     * Copy a file object into the current directory
     *
     * @param \Hazaar\File $file The file to put in this directory
     *
     * @return mixed
     */
    public function put(\Hazaar\File $file, $overwrite = false){

        return $file->copyTo($this->path, $overwrite, false, $this->backend);

    }

    /**
     * Download a file from a URL directly to the directory and return a new File object
     *
     * This is useful for download large files as this method will write the file directly
     * to storage.  Currently, only local storage is supported as this uses OS file access.
     *
     * @param mixed $source_url The source URL of the file to download
     * @param mixed $timeout The download timeout after which an exception will be thrown
     * @throws \Exception
     * @return \Hazaar\File A file object for accessing the newly created file
     */
    public function download($source_url, $timeout = 60){

        $file = $this->get(basename($source_url));

        $file->open('w+');

        $url = str_replace(" ","%20", $source_url);

        if(function_exists('curl_version')){

            $ch = curl_init($url);

            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

            curl_setopt($ch, CURLOPT_FILE, $file->get_resource());

            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);

            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

            if(!curl_exec($ch))
                throw new \Hazaar\Exception(curl_error($ch));

            curl_close($ch);

        }elseif(ini_get('allow_url_fopen') ) {

            $options = array(
                'http' => array(
                    'method'  => 'GET',
                    'timeout' => $timeout,
                    'follow_location' => 1
                )
            );

            if(!($result = file_get_contents($url, false, stream_context_create($options))))
                throw new \Hazaar\Exception('Download failed.  Zero bytes received.');

            $file->write($result);

        }

        $file->close();

        return $file;

    }

    public function media_uri($set_path = null){

        if($set_path !== null){

            if(!$set_path instanceof \Hazaar\Http\Uri)
                $set_path = new \Hazaar\Http\Uri($set_path);

            $this->__media_uri = $set_path;

        }

        if($this->__media_uri)
            return $this->__media_uri;

        if(!$this->manager)
            return null;

        return $this->manager->uri($this->fullpath());

    }

    public function get_meta($key = NULL) {

        return $this->backend->get_meta($this->path, $key);

    }

    public function sync(Dir $source, $recursive = false){

        while($item = $source->read()){

            if($item instanceof Dir){

                if($recursive === false)
                    continue;

                $dir = $this->get($item->basename(), true);

                if(!$dir->exists())
                    $dir->create();

                $dir->sync($item, $recursive);

            }elseif($item instanceof \Hazaar\File){

                if(!$this->exists($item->basename()))
                    $this->put($item, true);

            }

        }

        return true;

    }

}
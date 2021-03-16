<?php

namespace Hazaar\File;

define('HZ_SYNC_DIR', 1);

define('HZ_SYNC_DIR_COMPLETE', 2);

define('HZ_SYNC_FILE', 3);

define('HZ_SYNC_FILE_UPDATE', 4);

define('HZ_SYNC_FILE_COMPLETE', 5);

define('HZ_SYNC_ERROR', 6);

class Dir implements _Interface {

    private $path;

    private $backend;

    private $manager;

    private $files;

    private $allow_hidden = FALSE;

    private $__media_uri;

    private $relative_path;

    function __construct($path, Manager $manager = null, $relative_path = null) {

        if(!$manager)
            $manager = new Manager();

        $this->manager = $manager;

        $this->path = $this->manager->fixPath($path);

        if($relative_path)
            $this->relative_path = rtrim(str_replace('\\', '/', $relative_path), '/');

    }

    public function backend(){

        return strtolower((new \ReflectionClass($this->manager))->getShortName());

    }

    public function getBackend(){

        return $this->manager;

    }

    public function getManager(){

        return $this->manager;

    }

    public function set_meta($values) {

        return $this->manager->set_meta($this->path, $values);

    }

    public function get_meta($key = NULL) {

        return $this->manager->get_meta($this->path, $key);

    }

    public function toString(){

        return $this->path();

    }

    public function __toString(){

        return $this->toString();

    }

    public function path($suffix = NULL) {

        return $this->path . ($suffix ? '/' . $suffix : '');

    }

    public function fullpath($suffix = null){

        return $this->path($suffix);

    }

    public function realpath($suffix = NULL) {

        return $this->manager->realpath($this->path, $suffix);

    }

    public function dirname(){

        return  str_replace('\\', '/', dirname($this->path));

    }

    public function name(){

        return pathinfo($this->path, PATHINFO_BASENAME);

    }

    public function extension() {

        return pathinfo($this->path, PATHINFO_EXTENSION);

    }

    public function basename(){

        return basename($this->path);

    }

    public function size(){

        return $this->manager->filesize($this->path);

    }

    public function type(){

        return $this->manager->filetype($this->path);

    }

    public function exists($filename = null) {

        return $this->manager->exists(rtrim($this->path, '/') . ($filename ? '/' . $filename : ''));

    }

    public function is_readable() {

        if(!$this->exists())
            return false;

        return $this->manager->is_readable($this->path);

    }

    public function is_writable() {

        return $this->manager->is_writable($this->path);

    }

    public function is_file() {

        if(!$this->exists())
            return false;

        return $this->manager->is_file($this->path);

    }

    public function is_dir() {

        if(!$this->exists())
            return false;

        return $this->manager->is_dir($this->path);

    }

    public function is_link() {

        if(!$this->exists())
            return false;

        return $this->manager->is_link($this->path);

    }

    public function parent() {

        return new File\Dir($this->dirname(), $this->manager);

    }

    public function ctime() {

        if(!$this->exists())
            return false;

        return $this->manager->filectime($this->path);

    }

    public function mtime() {

        if(!$this->exists())
            return false;

        return $this->manager->filemtime($this->path);

    }

    public function touch(){

        if(!$this->exists())
            return false;

        return $this->manager->touch($this->path);

    }

    public function atime() {

        if(!$this->exists())
            return false;

        return $this->manager->fileatime($this->path);

    }

    public function allow_hidden($toggle = TRUE) {

        $this->allow_hidden = $toggle;

    }

    public function create($recursive = false) {

        if($recursive !== true)
            return $this->manager->mkdir($this->path);

        $parents = array();

        $last = $this->path;

        while(!$this->manager->exists($last)) {

            $parents[] = $last;

            $last = $this->manager->fixPath(dirname($last));

            if($last === '/')
                break;

        }

        while($parent = array_pop($parents)) {

            if(! $this->manager->mkdir($parent))
                return FALSE;

        }

        return TRUE;

    }

    public function rename($newname, $overwrite = false){

        return $this->manager->move($this->path, $this->dirname() . '/' . $newname, $overwrite);

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

        return $this->manager->rmdir($this->path, $recursive);

    }

    /**
     * File::unlink() compatible delete that removes dir and all contents (ie: recursive).
     */
    public function unlink(){

        return $this->delete(true);

    }

    public function isEmpty(){

        $files = $this->manager->scandir($this->path);

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

        if(!is_array($this->files)) {

            if(!($files = $this->manager->scandir($this->path, $regex_filter, $this->allow_hidden)))
                return false;

            $this->files = $files;

            if(($file = $this->rewind()) == FALSE)
                return FALSE;

        } else {

            if(($file = next($this->files)) === FALSE)
                return FALSE;

        }

        return $file;

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

        $list = array();

        if(!($dir = $this->manager->scandir($this->path, NULL, TRUE)))
            return null;

        $relative_path = $this->relative_path ? $this->relative_path : $this->path;

        foreach($dir as $file) {

            if(($show_hidden === FALSE && substr($file, 0, 1) == '.'))
                continue;

            if($file->is_dir() && ($depth === null || $depth > 0)) {

                $subdir = new \Hazaar\File\Dir($file, $this->manager, $relative_path);

                if($subdiritems = $subdir->find($pattern, $show_hidden, $case_sensitive, (($depth === null) ? $depth : $depth - 1)))
                    $list = array_merge($list, $subdiritems);

            }else{

                if(strlen($pattern) > 1 && substr($pattern, 0, 1) == substr($pattern, -1, 1)) {

                    if(preg_match($pattern . ($case_sensitive ? NULL : 'i'), $file) == 0)
                        continue;

                } elseif(! fnmatch($pattern, $file->basename(), $case_sensitive ? 0 : FNM_CASEFOLD))
                    continue;

                $list[] = $file;

            }

        }

        return $list;

    }

    public function copyTo($target, $recursive = FALSE, $transport_callback = NULL) {

        if($this->manager->exists($target)) {

            if(! $this->manager->is_dir($target))
                return FALSE;

        } else if(! $this->manager->mkdir($target))
            return FALSE;

        $dir = $this->manager->scandir($this->path, NULL, TRUE);

        foreach($dir as $cur) {

            if($cur == '.' || $cur == '..')
                continue;

            $sourcePath = $this->path . '/' . $cur;

            $targetPath = $target . '/' . $cur;

            if(is_array($transport_callback) && count($transport_callback) == 2) {

                /*
                 * Call the transport callback.  If it returns true, do the copy.  False means do not copy this file.
                 * This gives the callback a chance to perform the copy itself in a special way, or ignore a
                 * file/directory
                 */
                if(! call_user_func_array($transport_callback, array($sourcePath, $targetPath)))
                    continue;

            }

            if($this->manager->is_dir($sourcePath)) {

                if($recursive) {

                    $dir = new Dir($sourcePath, $this->manager);

                    $dir->copyTo($targetPath, $recursive, $transport_callback);

                }

            } else {

                $perms = $this->manager->fileperms($sourcePath);

                $this->manager->copy($sourcePath, $targetPath);

                $this->manager->chmod($targetPath, $perms);

            }

        }

        return TRUE;

    }

    public function get($child, $force_dir = false) {

        $path = $this->path($child);

        if($force_dir === true)
            return $this->getDir($child);

        return new \Hazaar\File($this->path($child), $this->manager, $this->relative_path ? $this->relative_path : $this->path);

    }

    public function getDir($child){

        return new \Hazaar\File\Dir($this->path($path), $this->manager);

    }

    public function mime_content_type(){

        return 'httpd/unix-directory';

    }

    public function dir($child = null) {

        $relative_path = $this->relative_path ? $this->relative_path : $this->path;

        return new \Hazaar\File\Dir($this->path($child), $this->manager, $relative_path);

    }

    public function toArray(){

        return $this->manager->scandir($this->path, null, $this->allow_hidden);

    }

    /**
     * Copy a file object into the current directory
     *
     * @param \Hazaar\File $file The file to put in this directory
     *
     * @return mixed
     */
    public function put(\Hazaar\File $file, $overwrite = false){

        return $file->copyTo($this->path, $overwrite, false, $this->manager);

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

    private function callSyncCallback(){

        $args = func_get_args();

        $callback = array_shift($args);

        if(!is_callable($callback))
            return true;

        return call_user_func_array($callback, $args) !== false ? true : false;

    }

    public function sync(Dir $source, $recursive = false, $progress_callback = null, $max_retries = 3){

        if($this->callSyncCallback($progress_callback, HZ_SYNC_DIR, ['src' => $source, 'dst' => $this]) !== true)
            return false;

        if(!$this->exists())
            $this->create();

        while($item = $source->read()){

            $retries = $max_retries;

            for($i = 0; $i < $retries; $i++){

                try{

                    $result = true;

                    if($item->is_dir()){

                        if($recursive === false)
                            continue 2;

                        $this->get($item->basename(), true)->sync($item, $recursive, $progress_callback);

                    }elseif($item instanceof \Hazaar\File){

                        $target = null;

                        if($this->callSyncCallback($progress_callback, HZ_SYNC_FILE, ['src' => $item, 'dst' => $this]) !== true)
                            continue 2;

                        if(!($sync = (!$this->exists($item->basename())))){

                            $target_file = $this->get($item->basename());

                            $sync = $item->mtime() > $target_file->mtime();

                        }

                        if($sync && $this->callSyncCallback($progress_callback, HZ_SYNC_FILE_UPDATE, ['src' => $item, 'dst' => $this]) === true)
                            $target = $this->put($item, true);

                        $this->callSyncCallback($progress_callback, HZ_SYNC_FILE_COMPLETE, ['src' => $item, 'dst' => $this, 'target' => $target]);

                    }

                    continue 2;

                }
                catch(\Throwable $e){

                    //If we get an exception, it could be due to a network issue, so notify any callbacks to handle the situation
                    if(is_callable($progress_callback)){

                        //Check the result of the callback.  False will retry the file a maximumu of 3 times.  Anything else will continue.
                        if($this->callSyncCallback($progress_callback, HZ_SYNC_ERROR, ['src' => $source, 'dst' => $this, 'err' => $e]) !== true)
                            continue 2;

                    }else{

                        //Otherwise maintain old behavior and hang back for sec to try again
                        sleep(1);

                    }

                }

            }

            throw (isset($e) ? $e : new \Exception('Unknown error!'));

        }

        $this->callSyncCallback($progress_callback, HZ_SYNC_DIR_COMPLETE, ['src' => $source, 'dst' => $this]);

        return true;

    }

    public function write($file, $bytes, $content_type = null){

        return $this->manager->write($this->manager->fixPath($this->path, $file), $bytes, $content_type);

    }


}
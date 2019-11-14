<?php

namespace Hazaar;

define('FILE_FILTER_IN', 0);

define('FILE_FILTER_OUT', 1);

define('FILE_FILTER_SET', 2);

class File {

    protected $backend;

    protected $manager;

    public    $source_file;

    protected $info;

    protected $mime_content_type;

    /*
     * Any overridden file contents.
     *
     * This is normally used when performing operations on the file in memory, such as resizing an image.
     */
    protected $contents;

    protected $resource;

    protected $handle;

    protected $relative_path;

    /*
     * Encryption bits
     */
    static public $default_cipher = 'aes-256-ctr';

    static public $default_key = 'hazaar_secret_badass_key';

    private $encrypted = false;

    private $__media_uri;

    /*
     * Content filters
     */
    private $filters = array();

    function __construct($file = null, File\Backend\_Interface $backend = NULL, File\Manager $manager = null, $relative_path = null) {

        if($file instanceof \Hazaar\File) {

            $this->backend = $file->backend;

            $this->manager = $file->manager;

            $this->source_file = $file->source_file;

            $this->info = $file->info;

            $this->mime_content_type = $file->mime_content_type;

        }elseif(is_resource($file)){

            $meta = stream_get_meta_data($file);

            $this->backend = new \Hazaar\File\Backend\Local();

            $this->source_file = $meta['uri'];

            $this->resource = $file;

        } else {

            if(empty($file))
                $file = Application::getInstance()->runtimePath('tmp', true) . DIRECTORY_SEPARATOR . uniqid();

            $this->source_file = $file;

            if(! $backend)
                $backend = new File\Backend\Local(array('root' => ((substr(PHP_OS, 0, 3) == 'WIN') ? substr(APPLICATION_PATH, 0, 3) : '/')));

            if(! $backend instanceof File\Backend\_Interface)
                throw new \Hazaar\Exception('Can not create new file object without a valid file backend!');

            $this->backend = $backend;

            $this->manager = $manager;

        }

        $this->relative_path = rtrim(str_replace('\\', '/', $relative_path), '/');

    }

    public function __destruct(){

        $this->close();

    }

    public function backend(){

        return strtolower((new \ReflectionClass($this->backend))->getShortName());

    }

    public function getBackend(){

        return $this->backend;

    }

    public function getManager(){

        return $this->manager;

    }

    /**
     * Content filters
     */

    public function registerFilter($type, $callable){

        if(!array_key_exists($type, $this->filters))
            $this->filters[$type] = array();

        if(is_string($callable))
            $callable = array($this, $callable);

        if(!is_callable($callable))
            return false;

        $this->filters[$type][] = $callable;

        return true;

    }

    public function set_meta($values) {

        return $this->backend->set_meta($this->source_file, $values);

    }

    public function get_meta($key = NULL) {

        return $this->backend->get_meta($this->source_file, $key);

    }

    /*
     * Basic output functions
     */
    public function toString() {

        return $this->fullpath();

    }

    public function __tostring() {

        return $this->toString();

    }

    /*
     * Standard filesystem functions
     */
    public function basename() {

        return basename($this->source_file);

    }

    public function dirname() {

        /*
         * Hack: The str_replace() call makes all directory separaters consistent as /.
         * The use of DIRECTORY_SEPARATOR should only be used in the local backend.
         */
        return str_replace('\\', '/', dirname($this->source_file));

    }

    public function name() {

        return pathinfo($this->source_file, PATHINFO_FILENAME);

    }

    public function fullpath() {

        $dir = $this->dirname();

        return rtrim($dir, '/') . '/' . $this->basename();

    }

    /**
     * Get the relative path of the file.
     *
     * If the file was returned from a [[Hazaar\File\Dir]] object, then it will have a stored
     * relative path.  Otherwise any file/path can be provided in the form of another [[Hazaar\File\\
     * object, [[Hazaar\File\Dir]] object, or string path, and the relative path to the file will
     * be returned.
     *
     * @param mixed $path Optional path to use as the relative path.
     *
     * @return boolean|string The relative path.  False when $path is not valid
     */
    public function relativepath($path = null){

        if($path !== null){

            if($path instanceof File)
                $path = $path->dirname();
            if($path instanceof File\Dir)
                $path = $path->fullpath();
            elseif(!is_string($path))
                return false;

            $source_path = explode('/', trim(str_replace('\\', '/', dirname($this->source_file)), '/'));

            $path = explode('/', trim(str_replace('\\', '/', $path), '/'));

            $index = 0;

            while (isset($source_path[$index])
                && isset($path[$index])
                && $source_path[$index] === $path[$index])
                $index++;

            $diff = count($source_path) - $index;

            return implode('/', array_merge(array_fill(0, $diff, '..'), array_slice($path, $index)));

        }


        if(!$this->relative_path)
            return $this->fullpath();

        $dir_parts = explode('/', $this->dirname());

        $rel_parts = explode('/', $this->relative_path);

        $path = null;

        foreach($dir_parts as $index => $part){

            if(array_key_exists($index, $rel_parts) && $rel_parts[$index] === $part)
                continue;

            $dir_parts =  array_slice($dir_parts, $index);

            if(($count = count($rel_parts) - $index) > 0)
                $dir_parts = array_merge(array_fill(0, $count, '..'), $dir_parts);

            $path = implode('/', $dir_parts);

            break;

        }

        return ($path ? $path . '/' : '') . $this->basename();

    }

    public function extension() {

        return pathinfo($this->source_file, PATHINFO_EXTENSION);

    }

    public function size() {

        if($this->contents)
            return strlen($this->contents);

        if(!$this->exists())
            return false;

        return $this->backend->filesize($this->source_file);

    }

    /*
     * Standard file modification functions
     */
    public function exists() {

        return $this->backend->exists($this->source_file);

    }

    public function realpath() {

        return $this->backend->realpath($this->source_file);

    }

    public function is_readable() {

        if(!$this->exists())
            return false;

        return $this->backend->is_readable($this->source_file);

    }

    public function is_writable() {

        return $this->backend->is_writable($this->source_file);

    }

    public function is_dir() {

        if(!$this->exists())
            return false;

        return $this->backend->is_dir($this->source_file);

    }

    public function dir() {

        if($this->is_dir())
            return new File\Dir($this->source_file, $this->backend, $this->manager);

        return FALSE;

    }

    public function is_link() {

        if(!$this->exists())
            return false;

        return $this->backend->is_link($this->source_file);

    }

    public function is_file() {

        if(!$this->exists())
            return false;

        return $this->backend->is_file($this->source_file);

    }

    public function parent() {

        return new File\Dir($this->dirname(), $this->backend, $this->manager);

    }

    public function type() {

        if(!$this->exists())
            return false;

        return $this->backend->filetype($this->source_file);

    }

    public function ctime() {

        if(!$this->exists())
            return false;

        return $this->backend->filectime($this->source_file);

    }

    public function mtime() {

        if(!$this->exists())
            return false;

        return $this->backend->filemtime($this->source_file);

    }

    public function touch(){

        if(!$this->exists())
            return false;

        return $this->backend->touch($this->source_file);

    }

    public function atime() {

        if(!$this->exists())
            return false;

        return $this->backend->fileatime($this->source_file);

    }

    public function has_contents() {

        if($this->contents)
            return true;

        if(!$this->exists())
            return false;

        return ($this->backend->filesize($this->source_file) > 0);

    }

    /**
     * Returns the current contents of the file.
     *
     * @param mixed $offset
     *
     * @param mixed $maxlen
     *
     * @return mixed
     */
    public function get_contents($offset = -1, $maxlen = NULL) {

        if($this->contents)
            return $this->contents;

        $this->contents = $this->backend->read($this->source_file, $offset, $maxlen);

        $this->filter_in($this->contents);

        return $this->contents;

    }

    /**
     * Put contents directly writes data to the storage backend without storing it in the file object itself
     *
     * NOTE: This function is called internally to save data that has been updated in the file object.
     *
     * @param mixed $data The data to write
     *
     * @param mixed $overwrite Overwrite data if it exists
     */
    public function put_contents($data, $overwrite = true) {

        $content_type = null;

        if(!is_resource($this->handle))
            $content_type = $this->mime_content_type();

        if(!$content_type)
            $content_type = 'text/text';

        $this->filter_out($data);

        return $this->backend->write($this->source_file, $data, $content_type, $overwrite);

    }

    /**
     * Sets the current contents of the file in memory.
     *
     * Calling this function does not directly update the content of the file "on disk".  To do that
     * you must call the File::save() method which will commit the data to storage.
     *
     * @param mixed $bytes The data to set as the content
     */
    public function set_contents($bytes) {

        if(array_key_exists(FILE_FILTER_SET, $this->filters)){

            foreach($this->filters[FILE_FILTER_SET] as $filter)
                call_user_func_array($filter, array(&$bytes));

        }

        $this->contents = $bytes;

    }

    /**
     * Set the contents from an encoded string.
     *
     * Currently this supports only data URI encoded strings.  I have made this generic in case I come
     * across other types of encodings that will work with this method.
     *
     * @param mixed $bytes The encoded data
     */
    public function set_decoded_contents($bytes){

        if(substr($bytes, 0, 5) == 'data:'){

            //Check we have a correctly encoded data URI
            if(($pos = strpos($bytes, ',', 5)) === false)
                return false;

            $info = explode(';', substr($bytes, 5, $pos - 5), 2);

            list($content_type, $encoding) = ((count($info) > 1) ? $info : array($info, null));

            if($encoding == 'base64')
                $this->contents = base64_decode(substr($bytes, $pos));
            else
                $this->contents = substr($bytes, $pos);

            if($content_type)
                $this->mime_content_type($content_type);

            return true;

        }

        return false;

    }

    /**
     * Saves the current in-memory content to the storage backend.
     *
     * Internally this calls File::put_contents() to write the data to the backend.
     *
     * @return mixed
     */
    public function save() {

        return $this->put_contents($this->get_contents(), TRUE);

    }

    /**
     * Saves this file objects content to another file name.
     *
     * @param mixed $filename The filename to save as
     *
     * @param mixed $overwrite Boolean flag to indicate that the destination should be overwritten if it exists
     *
     * @return mixed
     */
    public function saveAs($filename, $overwrite = FALSE) {

        return $this->backend->write($filename, $this->contents, $overwrite);

    }

    /**
     * Deletes the file from storage
     *
     * @return mixed
     */
    public function unlink() {

        if(! $this->exists())
            return FALSE;

        if($this->is_dir()) {

            return $this->backend->rmdir($this->source_file, TRUE);

        } else {

            return $this->backend->unlink($this->source_file);

        }

    }

    /**
     * Generate an MD5 checksum of the current file content
     *
     * @return string
     */
    public function md5() {

        //Otherwise use the md5 provided by the backend.  This is because some backend providers (such as dropbox) provide
        //a cheap method of calculating the checksum
        if(($md5 = $this->backend->md5Checksum($this->source_file)) === false)
            $md5 = md5($this->get_contents());

        return $md5;

    }

    /**
     * Return the base64 encoded content
     *
     * @return string
     */
    public function base64() {

        return base64_encode($this->get_contents());

    }

    /**
     * Returns the contents as decoded JSON.
     *
     * If the content is a JSON encoded string, this will decode the string and return it as a stdClass
     * object, or an associative array if the $assoc parameter is TRUE.
     *
     * If the content can not be decoded because it is not a valid JSON string, this method will return FALSE.
     *
     * @param mixed $assoc Return as an associative array.  Default is to use stdClass.
     *
     * @return mixed
     */
    public function parseJSON($assoc = FALSE) {

        $json = $this->get_contents();

        $bom = pack('H*','EFBBBF');

        $json = preg_replace("/^$bom/", '', $json);

        return json_decode($json, $assoc);

    }

    public function moveTo($destination, $overwrite = false, $create_dest = FALSE, $dstBackend = NULL) {

        $move = $this->exists();

        if(!$this->copyTo($destination, $overwrite, $create_dest, $dstBackend))
            return false;

        if($move){

            $this->backend->unlink($this->source_file);

            $this->source_file = $destination . '/' . $this->basename();

            if($dstBackend)
                $this->backend = $dstBackend;

        }

        return true;

    }

    /**
     * Copy the file to another folder
     *
     * @param string  $destination The destination folder to copy the file into
     * @param boolean $overwrite   Overwrite the destination file if it exists.
     * @param boolean $create_dest Flag that indicates if the destination folder should be created.  If the
     *                             destination does not exist an error will be thrown.
     * @param mixed   $dstBackend  The destination backend.  Defaults to the same backend as the source.
     *
     * @throws \Exception
     *
     * @throws File\Exception\SourceNotFound
     * @throws File\Exception\TargetNotFound
     *
     * @return mixed
     */
    public function copyTo($destination, $overwrite = false, $create_dest = FALSE, $dstBackend = NULL) {

        if(! $dstBackend)
            $dstBackend = $this->backend;

        if($this->contents){

            $this->backend = $dstBackend;

            $dir = new File\Dir($destination, $dstBackend, $this->manager);

            if(!$dir->exists()){

                if(!$create_dest)
                    throw new \Hazaar\Exception('Destination does not exist!');

                $dir->create(true);

            }

            $this->source_file = $destination . '/' . $this->basename();

            return $this->save();

        }

        if(!$this->exists())
            throw new File\Exception\SourceNotFound($this->source_file, $destination);

        if(!$dstBackend->exists($destination)) {

            if($create_dest)
                $dstBackend->mkdir($destination);

            else
                throw new File\Exception\TargetNotFound($destination, $this->source_file);

        }

        if($dstBackend === $this->backend)
            return $dstBackend->copy($this->source_file, $destination);

        return $dstBackend->write(rtrim($destination, '/') . '/' . $this->basename(), $this->get_contents(), $this->mime_content_type(), $overwrite);

    }

    public function mime_content_type() {

        if($this->mime_content_type)
            return $this->mime_content_type;

        return $this->backend->mime_content_type($this->fullpath());

    }

    public function set_mime_content_type($type) {

        $this->mime_content_type = $type;

    }

    public function thumbnail($params = array()) {

        return $this->backend->thumbnail($this->fullpath(), $params);

    }

    public function preview_uri($params = array()) {

        return $this->backend->preview_uri($this->fullpath(), $params);

    }

    public function direct_uri() {

        return $this->backend->direct_uri($this->fullpath());

    }

    public function media_uri($set_path = null){

        if($set_path !== null){

            if(!($set_path instanceof \Hazaar\Http\Uri || $set_path instanceof \Hazaar\Application\Url))
                $set_path = new \Hazaar\Http\Uri($set_path);

            $this->__media_uri = $set_path;

        }

        if($this->__media_uri)
            return $this->__media_uri;

        if(!$this->manager)
            return null;

        return $this->manager->uri($this->fullpath());

    }

    public function toArray($delimiter = "\n"){

        return explode($delimiter, $this->get_contents());

    }

    /**
     * Return the CSV content as a parsed array
     *
     * @return array
     */
    public function readCSV(){

        return array_map('str_getcsv', $this->toArray("\n"));

    }

    public function unzip($filenames = null, $target = null){

        if(!is_array($filenames))
            $filenames = array($filenames);

        if($target === null)
            $target = new File\TempDir();
        elseif(!$target instanceof \Hazaar\File\Dir)
            $target = new \Hazaar\File\Dir($target, $this->backend, $this->manager);

        $files = array();

        $zip = zip_open($this->source_file);

        if(!is_resource($zip))
            return false;

        while($zip_entry = zip_read($zip)){

            $name = zip_entry_name($zip_entry);

            if(!in_array(basename($name), $filenames))
                continue;

            $file = $target->get($name);

            $dir = new \Hazaar\File\Dir($file->dirname());

            if(!$dir->exists())
                $dir->create(true);

            $block_size = max(bytes_str(ini_get('memory_limit')) / 2, 1024000);

            $zip_entry_size = zip_entry_filesize($zip_entry);

            $file->open('w');

            for($i = 1; $i <= (ceil($zip_entry_size / $block_size)); $i++)
                $file->write(zip_entry_read($zip_entry, $block_size));

            $file->close();

            $files[] = $file;

        }

        zip_close($zip);

        return $files;

    }

    public function ziplist(){

        $list = array();

        $zip = zip_open($this->source_file);

        if(!is_resource($zip))
            return false;

        while($zip_entry = zip_read($zip))
            $list[] = zip_entry_name($zip_entry);

        return $list;

    }

    /**
     * Open a file and return it's file handle
     *
     * This is useful for using the file with standard (yet unsupported) file functions.
     *
     * @param string $mode
     *
     * @return resource
     */
    public function open($mode = 'r'){

        if($this->handle)
            return $this->handle;

        return $this->handle = fopen($this->backend->resolvePath($this->source_file), $mode);

    }

    public function get_resource(){

        return $this->handle;

    }

    /**
     * Close the file handle if it is currently open
     *
     * @return boolean
     */
    public function close(){

        if(!$this->handle)
            return false;

        fclose($this->handle);

        $this->handle = null;

        return true;

    }

    /**
     * Check if the file is currently opened for access by this class
     *
     * NOTE: This does not include checking if the file is opened by another process or even another Hazaar\File instance.
     *
     * @return boolean
     */
    public function isOpen(){

        return is_resource($this->handle);

    }

    /**
     * Returns a character from the file pointer
     *
     * @return string
     */
    public function getc(){

        if(!$this->handle)
            return null;

        return fgetc($this->handle);


    }

    /**
     * Returns a line from the file pointer
     *
     * @return string
     */
    public function gets(){

        if(!$this->handle)
            return null;

        return fgets($this->handle);

    }

    /**
     * Returns a line from the file pointer and strips HTML tags
     *
     * @return string
     */
    public function getss($allowable_tags = null){

        if(!$this->handle)
            return null;

        return strip_tags(fgets($this->handle), $allowable_tags);

    }

    /**
     * Returns a line from the file pointer and parse for CSV fields
     *
     * @param mixed $length     Must be greater than the longest line (in characters) to be found in the CSV file
     *                          (allowing for trailing line-end characters). Otherwise the line is split in chunks
     *                          of length characters, unless the split would occur inside an enclosure.
     *
     *                          Omitting this parameter (or setting it to 0 in PHP 5.1.0 and later) the maximum
     *                          line length is not limited, which is slightly slower.
     * @param mixed $delimiter  The optional delimiter parameter sets the field delimiter (one character only).
     * @param mixed $enclosure  The optional enclosure parameter sets the field enclosure character (one character only).
     * @param mixed $escape     The optional escape parameter sets the escape character (one character only).
     *
     * @return \array|null
     */
    public function getcsv($length = 0, $delimiter = ',', $enclosure = '"', $escape = '\\'){

        if($this->handle)
            return fgetcsv($this->handle, $length, $delimiter, $enclosure, $escape);

        if(!is_array($this->contents)){

            $this->contents = explode("\n", $this->contents);

            $line = reset($this->contents);

        }elseif(!($line = next($this->contents)))
            return false;

        return str_getcsv($line, $delimiter, $enclosure, $escape);

    }

    /**
     * Writes an array to the file in CSV format.
     *
     * @param mixed $fields     Must be greater than the longest line (in characters) to be found in the CSV file
     *                          (allowing for trailing line-end characters). Otherwise the line is split in chunks
     *                          of length characters, unless the split would occur inside an enclosure.
     *
     *                          Omitting this parameter (or setting it to 0 in PHP 5.1.0 and later) the maximum
     *                          line length is not limited, which is slightly slower.
     * @param mixed $delimiter  The optional delimiter parameter sets the field delimiter (one character only).
     * @param mixed $enclosure  The optional enclosure parameter sets the field enclosure character (one character only).
     * @param mixed $escape     The optional escape parameter sets the escape character (one character only).
     *
     * @return integer|null
     */
    public function putcsv($fields, $delimiter = ',', $enclosure = '"', $escape = '\\'){

        if($this->handle && is_array($fields))
            return fputcsv($this->handle, $fields, $delimiter, $enclosure, $escape);

        if(!is_array($this->contents))
            $this->contents = array();

        $this->contents[] = $line = str_putcsv($fields, $delimiter, $enclosure, $escape);

        return strlen($line);

    }

    /**
     * Seeks to a position in the file
     *
     * @param mixed $offset The offset. To move to a position before the end-of-file, you need to pass a negative value in offset and set whence to SEEK_END.
     * @param mixed $whence whence values are:
     *                      SEEK_SET - Set position equal to offset bytes.
     *                      SEEK_CUR - Set position to current location plus offset.
     *                      SEEK_END - Set position to end-of-file plus offset.
     * @return \boolean|integer
     */
    public function seek($offset, $whence = SEEK_SET){

        if(!$this->handle)
            return false;

        return fseek($this->handle, $offset, $whence);

    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return \boolean|integer
     */
    public function tell(){

        if(!$this->handle)
            return false;

        return ftell($this->handle);

    }

    /**
     * Rewind the position of a file pointer
     *
     * Sets the file position indicator for handle to the beginning of the file stream.
     *
     * @return boolean
     */
    public function rewind(){

        if(!$this->handle)
            return false;

        return rewind($this->handle);

    }

    /**
     * Tests for end-of-file on a file pointer
     *
     * @return boolean TRUE if the file pointer is at EOF or an error occurs; otherwise returns FALSE.
     */
    public function eof(){

        if(!$this->handle)
            return false;

        return feof($this->handle);

    }

    /**
     * Portable advisory file locking
     *
     * @param mixed $operation operation is one of the following:
     *                          LOCK_SH to acquire a shared lock (reader).
     *                          LOCK_EX to acquire an exclusive lock (writer).
     *                          LOCK_UN to release a lock (shared or exclusive).
     *                          It is also possible to add LOCK_NB as a bitmask to one of the above operations if you don't want flock() to block while locking.
     * @param mixed $wouldblock The optional third argument is set to 1 if the lock would block (EWOULDBLOCK errno condition).
     *
     * @return boolean
     */
    public function lock($operation, &$wouldblock = NULL){

        if(!$this->handle)
            return false;

        return flock($this->handle, $operation, $wouldblock);

    }

    /**
     * Truncates a file to a given length
     *
     * @param mixed $size The size to truncate to.
     *
     * @return boolean
     */
    public function truncate($size){

        if(!$this->handle)
            return false;

        return ftruncate($this->handle, $size);

    }

    /**
     * Binary-safe file read
     *
     * File::read() reads up to length bytes from the file pointer referenced by handle. Reading stops as soon as one of the following conditions is met:
     *
     * * length bytes have been read
     * * EOF (end of file) is reached
     * * a packet becomes available or the socket timeout occurs (for network streams)
     * * if the stream is read buffered and it does not represent a plain file, at most one read of up to a number
     *   of bytes equal to the chunk size (usually 8192) is made; depending on the previously buffered data, the
     *   size of the returned data may be larger than the chunk size.
     *
     * @param mixed $length Up to length number of bytes read.
     *
     * @return \boolean|string
     */
    public function read($length){

        if(!$this->handle)
            return false;

        return fread($this->handle, $length);

    }

    /**
     * Binary-safe file write
     *
     * File::write() writes the contents of string to the file stream pointed to by handle.
     *
     * @param mixed $string The string that is to be written.
     * @param mixed $length If the length argument is given, writing will stop after length bytes have been written or the end
     *                      of string is reached, whichever comes first.
     *
     *                      Note that if the length argument is given, then the magic_quotes_runtime configuration option
     *                      will be ignored and no slashes will be stripped from string.
     * @return \boolean|integer
     */
    public function write($string, $length = NULL){

        if(!$this->handle)
            return false;

        if($length === null)
            return fwrite($this->handle, $string);

        return fwrite($this->handle, $string, $length);

    }

    /**
     * Flushes the output to a file
     *
     * @return boolean
     */
    public function flush(){

        if(!$this->handle)
            return false;

        return fflush($this->handle);

    }

    /**
     * Renames a file or directory
     *
     * NOTE: This will not work if the file is currently opened by another process.
     *
     * @param mixed $newname The new name.
     *
     * @return boolean
     */
    public function rename($newname){

        if(!rename($this->source_file, $newname))
            return false;

        $this->source_file = $newname;

        return true;

    }

    /**
     * Check if a file is encrypted using the built-in Hazaar encryption method
     *
     * @return boolean
     */
    public function isEncrypted(){

        $r = $this->open();

        $bom = pack('H*','BADA55');  //Haha, Bad Ass!

        $encrypted = (fread($r, 3) == $bom);

        $this->close();

        return $encrypted;

    }

    private function getEncryptionKey(){

        if($key_file = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key'))
            $key = trim(file_get_contents($key_file));
        else
            $key = File::$default_key;

        return md5($key);

    }

    /**
     * Internal content filter
     *
     * Checks if the content is modified in some way using a BOM mask.  Currently this is used to determine if a file
     * is encrypted and automatically decrypts it if an encryption key is available.
     *
     * @param mixed $content
     */
    protected function filter_in(&$content){

        if(is_array($content))
            $content = implode("\n", $content);

        if(array_key_exists(FILE_FILTER_IN, $this->filters)){

            foreach($this->filters[FILE_FILTER_IN] as $filter)
                call_user_func($filter, $content);

        }

        $bom = substr($content, 0, 3);

        //Check if we are encrypted
        if($bom === pack('H*','BADA55')){  //Hazaar Encryption

            $this->encrypted = true;

            $cipher_len = openssl_cipher_iv_length(File::$default_cipher);

            $iv = substr($content, 3, $cipher_len);

            $data = openssl_decrypt(substr($content, 3 + $cipher_len), File::$default_cipher, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);

            $hash = substr($data, 0, 8);

            $content = substr($data, 8);

            if($hash !== hash('crc32', $content))
                throw new \Hazaar\Exception('Failed to decrypt file: ' . $this->source_file . '. Bad key?');

        }elseif($bom === pack('H*','EFBBBF')){  //UTF-8

            $content = substr($content, 3);  //Just strip the BOM

        }

        return true;

    }

    /**
     * Internal content filter
     *
     * Checks if the content is modified in some way using a BOM mask.  Currently this is used to determine if a file
     * is encrypted and automatically decrypts it if an encryption key is available.
     *
     * @param mixed $content
     */
    protected function filter_out(&$content){

        if(array_key_exists(FILE_FILTER_OUT, $this->filters)){

            foreach($this->filters[FILE_FILTER_OUT] as $filter)
                call_user_func_array($filter, array(&$content));

        }

        if($this->encrypted === true){

            $bom = pack('H*','BADA55');

            if(substr($content, 0, 3) === $bom)
                return false;

            $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(File::$default_cipher));

            $data = openssl_encrypt(hash('crc32', $content) . $content, File::$default_cipher, $this->getEncryptionKey(), OPENSSL_RAW_DATA, $iv);

            $content = $bom . $iv . $data;

        }

        return true;

    }

    public function encrypt($write = true){

        $this->encrypted = true;

        if($write)
            $this->save();

    }

    /**
     * Writes the decrypted file to storage
     */
    public function decrypt(){

        $this->contents = $this->get_contents();

        $this->encrypted = false;

        return $this->save();

    }

    static public function delete($path){

        $file = new File($path);

        return $file->unlink();

    }

}

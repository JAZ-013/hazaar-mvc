<?php

namespace Hazaar\File;

class GZFile extends \Hazaar\File {

    private $level = -1;

    private $encoding = FORCE_GZIP;

    function __construct($file = null, $manager = NULL){

        parent::__construct($file, $manager);

        $this->set_mime_content_type('application/gzip');

    }

    /**
     * Sets the level of compression.
     *
     * Can be given as 0 for no compression up to 9 for maximum compression.
     *
     * If -1 is used, the default compression of the zlib library is used which is 6.
     *
     * @param mixed $level Compression level from 0 to 9.
     */
    public function setCompressionLevel($level){

        $this->level = intval($level);

    }

    /**
     * Returns the current compression level setting
     *
     * Can be a value of 0 for no compression up to 9 for maximum compression.
     *
     * If the value is -1, the default compression of the zlib library is being used, which is 6.
     *
     * @return integer
     */
    public function getCompressionLevel(){

        return $this->level;

    }

    /**
     * Set the current encoding for compress
     *
     * @param mixed $encoding
     */
    public function setEncoding($encoding){

        if(!($encoding === FORCE_GZIP || $encoding === FORCE_DEFLATE))
            return false;

        $this->encoding = $encoding;

        return true;

    }

    /**
     * Returns the current gzencode encoding
     * @return mixed
     */
    public function getEncoding(){

        return $this->encoding;

    }

    /**
     * Open gz-file
     *
     * @param mixed $mode
     * @return resource
     */
    public function open($mode = 'r'){

        if($this->handle)
            return $this->handle;

        return $this->handle = gzopen($this->source_file , $mode);

    }

    /**
     * Close an open gz-file pointer
     *
     * @return boolean
     */
    public function close(){

        if(!$this->handle)
            return false;

        gzclose($this->handle);

        $this->handle = null;

        return true;

    }

    /**
     * Binary-safe gz-file read
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

        return gzread($this->handle, $length);

    }

    /**
     * Binary-safe gz-file write
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
            return gzwrite($this->handle, $string);

        return gzwrite($this->handle, $string, $length);

    }

    /**
     * Returns a character from the file pointer
     *
     * @return string
     */
    public function getc(){

        if(!$this->handle)
            return null;

        return gzgetc($this->handle);


    }

    /**
     * Returns a line from the file pointer
     *
     * @return string
     */
    public function gets(){

        if(!$this->handle)
            return null;

        return gzgets($this->handle);

    }

    /**
     * Returns a line from the file pointer and strips HTML tags
     *
     * @return string
     */
    public function getss($allowable_tags = null){

        if(!$this->handle)
            return null;

        return strip_tags(gzgets($this->handle), $allowable_tags);

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

        return gzseek($this->handle, $offset, $whence);

    }

    /**
     * Returns the current position of the file read/write pointer
     *
     * @return \boolean|integer
     */
    public function tell(){

        if(!$this->handle)
            return false;

        return gztell($this->handle);

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

        return gzrewind($this->handle);

    }

    /**
     * Tests for end-of-file on a file pointer
     *
     * @return boolean TRUE if the file pointer is at EOF or an error occurs; otherwise returns FALSE.
     */
    public function eof(){

        if(!$this->handle)
            return false;

        return gzeof($this->handle);

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

        $this->contents = gzdecode($this->manager->read($this->source_file, $offset, $maxlen));

        $this->filter_in($this->contents);

        return $this->contents;

    }

    /**
     * Put contents directly writes data to the storage manager without storing it in the file object itself
     *
     * NOTE: This function is called internally to save data that has been updated in the file object.
     *
     * @param mixed $data The data to write
     *
     * @param mixed $overwrite Overwrite data if it exists
     */
    public function put_contents($data, $overwrite = true) {

        return parent::put_contents(gzencode($data, $this->level, $this->encoding), $overwrite);

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

        return $this->manager->write($filename, gzencode($this->contents, $this->level, $this->encoding), $overwrite);

    }


}
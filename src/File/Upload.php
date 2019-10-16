<?php

namespace Hazaar\File;

/**
 * The file upload manager class
 *
 * This class makes it much simpler to work with file uploaded via a POST request to the server.  Behind the scenes it
 * works with the PHP $_FILES global that contains information about any uploaded files.  This class then provides a few
 * simple methods to make saving all files, or a single file, much simpler.
 *
 * ### Examples
 *
 * Save all uploaded files to a directory:
 *
 * <code>
 * $upload = new \Hazaar\File\Upload();
 *
 * $upload->saveAll('/home/user', true);
 * </code>
 *
 * Save a single file into a database if it has been uploaded:
 *
 * <code>
 * $upload = new \Hazaar\File\Upload();
 *
 * if($upload->has('my_new_file')){
 *
 *      $bytes = $upload->read('my_new_file');
 *
 *      $db->insert('file_contents', array(
 *          'created' => 'Now()',
 *          'filename' => $upload->my_new_file['name'],
 *          'bytes' => $bytes
 *      ));
 *
 * }
 * </code>
 */
class Upload {

    private $files = array();

    /**
     * Constructor
     */
    function __construct() {

        $this->files = $_FILES;

    }

    /**
     * Check to see if there are any files that have uploaded as part of the current request
     *
     * @param Mixed $op_keys A key, or array of keys to check for.  If any of the supplied keys do not exist then the
     *                       method will return false.  If this parameter is not supplied this method will return true
     *                       if ANY file has been uploaded.
     *
     * @return boolean
     */
    public function uploaded($op_keys = NULL) {

        if($op_keys && ! is_array($op_keys))
            $op_keys = array($op_keys);

        if($op_keys) {

            foreach($op_keys as $key) {

                if(! array_key_exists($key, $this->files) || ! $this->files[$key]['tmp_name']) {

                    return FALSE;

                }

            }

            return TRUE;

        } elseif(count($this->files) > 0) {

            foreach($this->files as $file) {

                if($file['tmp_name'])
                    return TRUE;

            }

        }

        return FALSE;

    }

    /**
     * Return an array of uploaded file keys
     *
     * The keys are the 'name' attribute for the form element that was used to upload the file.
     *
     * @return Array
     */
    public function keys() {

        return array_keys($this->files);

    }

    /**
     * Test the existence of a file upload key
     *
     * @param string $key The key to check for
     *
     * @return boolean
     */
    public function has($key) {

        return array_key_exists($key, $this->files);

    }

    /**
     * Magic method to get details about an uploaded file
     *
     * @param string $key The key of the uploaded file to return details of.
     *
     * @return Array
     */
    public function __get($key) {

        return $this->get($key);

    }

    /**
     * Get details about an uploaded file.
     *
     * @param string $key The key of the uploaded file to return details of.
     *
     * @return Array
     */
    public function get($key = null) {

        if($key === null){

            $files = array();

            foreach($this->keys() as $key)
                $files[$key] = $this->get($key);

            return $files;

        }elseif(($pos = strpos($key, '.')) > 0){

            $sub_key = substr($key, $pos + 1);

            $files = array_to_dot_notation($this->get(substr($key, 0, $pos)), '.', substr_count($sub_key, '.') + 1);

            return ake($files, $sub_key);

        }

        if($info = ake($this->files, $key)) {

            if(!is_array($info['name']))
                return $info;

            $files = array();

            foreach($info as $item => $item_info){

                foreach(array_to_dot_notation($item_info) as $name => $data)
                    $files[$name . '.' . $item] = $data;

            }

            return array_from_dot_notation($files);

        }

        return NULL;

    }

    /**
     * Save all the files that were uploaded to a single directory
     *
     * This will iterate through all uploaded files and save them to the specified
     * destination directory.  If a callback function is specified then that function
     * will be executed for each file.  Arguments to the function are the key, $name,
     * $size and $type.  If the callback function returns false,  the file is NOT copied.
     * The $name argument is also checked after the function call to give the callback
     * function a chance to alter the destination filename (see example).
     *
     * <code>
     * $files->saveAll('/home/user', false, function($key, &$name, $size, $type)){
     *
     *      if($type == 'image/jpeg'){
     *
     *          $name = uniqid() . '.jpeg';
     *
     *          return true;
     *
     *      }
     *
     *      return false;
     * });
     * </code>
     *
     * @param string  $destination The destination directory into which we copy the files
     *
     * @param Closure $callback    A callback function that will be called for each file.  This function must return
     *                             true for the file to be copied. The $name field is passed byRef so the file can be
     *                             renamed on the way through.
     *
     * @return void
     */
    public function saveAll($destination, $overwrite = FALSE, \Closure $callback = NULL) {

        foreach($this->keys() as $key) {

            $files = $this->getFile($key);

            if(!is_array($files))
                $files = array($files);

            foreach($files as $file){

                $save = TRUE;

                if($callback instanceof \Closure)
                    $save = $callback($key, $file);

                if($save === TRUE)
                    $file->copyTo($destination, $overwrite);

            }

        }

    }

    /**
     * Save an uploaded file to a destination
     *
     * @param string  $key         The index key of the file to save
     *
     * @param string  $destination The destination file or directory to save the file to
     *
     * @param boolean $overwrite   Flag to indicate if existing files should be overwritten
     *
     * @return boolean
     */
    public function save($key, $destination, $overwrite = FALSE) {

        if(!($files = $this->getFile($key)))
            return FALSE;

        if(!is_array($files))
            $files = array($files);

        foreach($files as $file)
            $file->copyTo($destination, $overwrite);

        return true;

    }

    /**
     * Read the contents of an uploaded file and return the bytes
     *
     * @param string $key The key name of the file to return.  Use \Hazaar\Upload\File::keys() to get this.
     *
     * @return string The bytes for the uploaded file.
     */
    public function read($key) {

        if(array_key_exists($key, $this->files)) {

            $file = $this->files[$key];

            if(file_exists($file['tmp_name'])) {

                return file_get_contents($file['tmp_name']);

            }

        }

        return NULL;

    }

    /**
     * Returns the uploaded file as a Hazaar\File object.
     *
     * @param mixed $key
     *
     * @return \Hazaar\File|array
     */
    public function getFile($key = null){

        if(!($file = $this->get($key)))
            return false;

        return $this->resolveFiles($file);

    }

    private function resolveFiles($array){

        if(array_key_exists('tmp_name', $array)){

            if($array['error'] > 0)
                throw new \Hazaar\Exception('Upload error processing ' . $array['name']);

            $file = new \Hazaar\File($array['name']);

            $file->set_mime_content_type($array['type']);

            $file->set_contents(file_get_contents($array['tmp_name']));

            return $file;

        }

        $files = array();

        foreach($array as $key => $info)
            $files[$key] = $this->resolveFiles($info);

        return $files;

    }

    static public function getMaxUploadSize(){

        $max_size = -1;

        if(($post_max_size = bytes_str(ini_get('post_max_size'))) > 0)
            $max_size = $post_max_size;

        $upload_max_filesize = bytes_str(ini_get('upload_max_filesize'));

        if($upload_max_filesize > 0 && $upload_max_filesize < $max_size)
            $max_size = $upload_max_filesize;

        return $max_size;

    }

}

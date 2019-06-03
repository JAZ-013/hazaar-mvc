<?php

namespace Hazaar\Controller\Response;

class File extends \Hazaar\Controller\Response\HTTP\OK {

    protected $file;

    protected $backend;

    protected $fmtime;

    /**
     * \Hazaar\File Constructor
     *
     * @param mixed $file Either a string filename to use or a \Hazaar\File object.
     *
     * @throws \Hazaar\Exception\FileNotFound
     */
    function __construct($file = NULL, $backend = NULL) {

        $this->backend = $backend;

        parent::__construct();

        $this->setHeader('Cache-Control', 'public, max-age=300');

        if($file !== NULL)
            $this->load($file, $backend);

    }

    public function load($file, $backend = NULL) {

        if(! $backend)
            $backend = $this->backend;

        $this->file = ($file instanceof \Hazaar\File) ? $file : new \Hazaar\File($file, $backend);

        if(!($this->file->exists() || $this->hasContent()))
            throw new \Hazaar\Exception\FileNotFound($file);

        $this->setContentType($this->file->mime_content_type());

        $this->setLastModified($this->file->mtime());

        return TRUE;

    }

    public function setContent($data, $content_type = null) {

        if($data instanceof \Hazaar\File){

            $this->file = $data;

        }elseif(! $this->file){

            $this->file = new \Hazaar\File(NULL);

        }

        if($content_type)
            $this->file->set_mime_content_type($this->content_type = $content_type);

        $this->file->set_contents($data);

        return $this->file;

    }

    public function getContent() {

        if($this->file)
            return $this->file->get_contents();

        return parent::getContent();

    }

    public function getContentLength() {

        if($this->file)
            return $this->file->size();

        return 0;

    }

    public function hasContent() {

        if($this->file)
            return ($this->file->size() > 0);

        return FALSE;

    }

    public function setUnmodified($ifModifiedSince) {

        if(!$this->file)
            return false;

        if(!$ifModifiedSince)
            return false;

        if(!$ifModifiedSince instanceof \Hazaar\Date)
            $ifModifiedSince = new \Hazaar\Date($ifModifiedSince);

        if(($this->fmtime ? $this->fmtime->sec() : $this->file->mtime()) > $ifModifiedSince->getTimestamp())
            return false;

        $this->setStatus(304);

        return true;

    }

    public function setLastModified($fmtime) {

        if(! $fmtime instanceof \Hazaar\Date)
            $fmtime = new \Hazaar\Date($fmtime, 'UTC');

        $this->fmtime = $fmtime;

        $this->setHeader('Last-Modified', gmdate('r', $this->fmtime->sec()));

    }

    public function getLastModified() {

        return $this->getHeader('Last-Modified');

    }

    public function match($pattern, &$matches = NULL, $flags = 0, $offset = 0) {

        return preg_match($pattern, $this->content, $matches, $flags, $offset);

    }

    public function replace($pattern, $replacement, $limit = -1, &$count = NULL) {

        return $this->content = preg_replace($pattern, $replacement, $this->content, $limit, $count);

    }

    public function setDownloadable($toggle = TRUE, $filename = NULL) {

        if(! $filename)
            $filename = $this->file->basename();

        if($toggle) {

            $this->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } else {

            $this->setHeader('Content-Disposition', 'inline; filename="' . $filename . '"');

        }

    }

    public function getContentType() {

        return $this->content_type ? $this->content_type : $this->file->mime_content_type();

    }

    public function getFile() {

        return $this->file;

    }

}
<?php

namespace Hazaar\Controller\Response;

class PDF extends \Hazaar\Controller\Response\HTTP\OK {

    private $pdf_file;

    private $downloadable = false;

    /**
     * Constructor: initialize command line and reserve temporary file.
     */
    public function __construct($file = null, $downloadable = false) {

        if(is_string($file))
            $file = new \Hazaar\File\PDF($file);
        elseif($file instanceof \Hazaar\File)
            $file = new \Hazaar\File\PDF($file->fullpath(), $file->getBackend(), $file->getManager(), $file->relativepath());

        $this->pdf_file = ($file instanceof \Hazaar\File\PDF) ? $file : new \Hazaar\File\PDF();

        $this->downloadable = $downloadable;

    }

    public function __call($method, $args) {

        if(method_exists($this->pdf_file, $method))
            return call_user_func_array(array($this->pdf_file, $method), $args);

        return false;

    }

    public function setMode($mode) {

        $this->mode = $mode;

    }

    public function setContent($content){

        return $this->pdf_file->set_contents($content);

    }

    public function __writeOutput() {

        $this->content = $this->pdf_file->get_contents();

        $this->setContentType('application/pdf');

        if($this->downloadable === true) {

            $this->setHeader('Content-Description', 'File Transfer');

            $this->setHeader('Cache-Control', 'public, must-revalidate, max-age=0');
            // HTTP/1.1

            $this->setHeader('Pragma', 'public');

            $this->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
            // Date in the past

            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');

            // force download dialog
            $this->setHeader('Content-Type', 'application/force-download');

            $this->setHeader('Content-Type', 'application/octet-stream', FALSE);

            $this->setHeader('Content-Type', 'application/download', FALSE);

            $this->setHeader('Content-Type', 'application/pdf', FALSE);

            // use the Content-Disposition header to supply a recommended filename
            $this->setHeader('Content-Disposition', 'attachment; filename="' . $this->pdf_file->basename() . '";');

            $this->setHeader('Content-Transfer-Encoding', 'binary');

        }else{

            $this->setHeader('Cache-Control', 'public, must-revalidate, max-age=0');
            // HTTP/1.1

            $this->setHeader('Pragma', 'public');

            $this->setHeader('Expires', 'Sat, 26 Jul 1997 05:00:00 GMT');
            // Date in the past

            $this->setHeader('Last-Modified', gmdate('D, d M Y H:i:s') . ' GMT');

            if($filename = $this->pdf_file->basename())
                $this->setHeader('Content-Disposition', 'inline; filename="' . $filename . '";');

        }

        return parent::__writeOutput();

    }

}
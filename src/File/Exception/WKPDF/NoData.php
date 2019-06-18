<?php
namespace Hazaar\File\Exception\WKPDF;

class NoData extends \Hazaar\Exception {

    function __construct($error) {

        parent::__construct('WKPDF didn\'t return any data. <pre>' . $error . '</pre>');

    }

}

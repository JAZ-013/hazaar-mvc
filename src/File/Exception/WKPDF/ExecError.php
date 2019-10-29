<?php
namespace Hazaar\File\Exception\WKPDF;

class ExecError extends \Hazaar\Exception {

    function __construct($code) {

        parent::__construct('WKPDF shell error, return code ' . $code . '.');

    }

}

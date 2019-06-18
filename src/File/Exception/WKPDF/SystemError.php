<?php
namespace Hazaar\File\Exception\WKPDF;

class SystemError extends \Hazaar\Exception {

    function __construct($error) {

        parent::__construct('WKPDF system error: <pre>' . $error . '</pre>');

    }

}

<?php
namespace Hazaar\File\Exception\WKPDF;

class NotExecutable extends \Hazaar\Exception {

    function __construct($cmd) {

        parent::__construct('WKPDF static executable "' . htmlspecialchars($cmd, ENT_QUOTES) . '" is not executable.');

    }

}

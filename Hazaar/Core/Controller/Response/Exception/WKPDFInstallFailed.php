<?php
namespace Hazaar\Controller\Response\Exception;

class WKPDFInstallFailed extends \Hazaar\Exception {

    function __construct($cmd, $error) {

        $msg = 'WKPDF static executable "' . htmlspecialchars($cmd, ENT_QUOTES) . '" was not found so an automated installation was attempted but failed.';

        $msg .= '<p><b>Reason:</b> ' . $error;

        parent::__construct($msg);

    }

}

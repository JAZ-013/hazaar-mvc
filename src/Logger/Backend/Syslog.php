<?php

namespace Hazaar\Logger\Backend;

class Syslog extends \Hazaar\Logger\Backend {

    public function write($tag, $msg, $level = LOG_NOTICE) {

        syslog($level, $tag . ': ' . $msg);

    }

    public function trace() {

    }

}

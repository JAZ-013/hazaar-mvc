<?php

namespace Hazaar\Logger\Backend;

interface _Interface {

    public function write($tag, $message, $level = LOG_NOTICE);

    public function trace();

    public function postRun();

}

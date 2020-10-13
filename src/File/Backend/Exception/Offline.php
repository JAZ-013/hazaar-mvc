<?php

namespace Hazaar\File\Backend\Exception;

class Offline extends \Hazaar\Exception {

    function __construct(){

        parent::__construct('Storage filesystem is currently unavailable.');

    }

}
<?php

namespace Hazaar\Console;

class System extends Module {

    public function load(){

        $this->addMenuItem('System', 'wrench');

    }

    public function index(){

        $this->view('system/phpinfo');

        $this->view->link('css/phpinfo.css');

    }

}
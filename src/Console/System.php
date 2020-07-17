<?php

namespace Hazaar\Console;

class System extends Module {

    public function load(){

        $this->addMenuGroup('System', 'wrench');

    }

    public function index(){

        $this->view('phpinfo');

        $this->view->link('css/phpinfo.css');

    }

}
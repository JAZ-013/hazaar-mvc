<?php

namespace Hazaar\Console;

class System extends Module {

    public function menu(){

        return $this->addMenuItem('System', 'wrench');

    }

    public function index(){

        $this->view('system/phpinfo');

        $this->view->link('css/phpinfo.css');

    }

}
<?php

namespace Hazaar\Console;

class System extends Module {

    public function load(){

        $this->addMenuGroup('System', 'wrench');

    }

    public function index($request){

        $this->view('system/phpinfo');

        $this->view->link('css/phpinfo.css');

    }

}
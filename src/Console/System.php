<?php

namespace Hazaar\Console;

class System extends Module {

    public function menu(){

        return $this->addMenuItem('System', 'wrench');

    }

    public function index(){

        $view = $this->view('system/phpinfo');

        $view->link('css/phpinfo.css');

        return $view;
        
    }

}
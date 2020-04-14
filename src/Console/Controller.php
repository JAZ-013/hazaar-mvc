<?php

namespace Hazaar\Console;

define('APPLICATION_CONSOLE', true);

class Controller extends \Hazaar\Controller\Action {

    private $passwd = null;

    private $handler;

    public function init(){

        $this->handler = new Handler($this->application);

        if($this->isAction('login', 'logout') === true)
            return;

        if($this->handler->authenticated() !== true)
            return $this->redirect($this->application->url('hazaar', 'console', 'login'));

        $path = LIBRARY_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'console';

        $this->handler->load(new Application('app', $path, $this->application));

        $this->handler->load(new System('sys', $path, $this->application));

        $this->handler->loadComposerModules($this->application);

    }

    public function login(){

        if($this->request->isPOST()){

            if($this->handler->authenticate($this->request->username, $this->request->password))
                return $this->redirect($this->application->url('hazaar', 'console'));

            $this->view->msg = 'Login failed';

        }

        $this->layout('@console/login');

        $this->view->link('console/css/login.css');

        $this->view->addHelper('fontawesome');

    }

    public function logout(){

        $this->handler->deauth();

        return $this->redirect($this->application->url('hazaar'));

    }


    /**
     * Launch the Hazaar MVC Management Console
     */
    public function __default($controller, $action){

        return $this->handler->exec($this, $action, $this->request);

    }

    public function menu(){

        $modules = $this->handler->getModules();

        $menuItems = [
            'url' => (string)$this->url(),
            'items' => []
        ];

        foreach($modules as $module_name => $module)
            $menuItems['items'][$module_name] = $module->menu();

        return $menuItems;

    }

    public function doc(){

        dump('yay!');

    }

}

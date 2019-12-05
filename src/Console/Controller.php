<?php

namespace Hazaar\Console;

define('APPLICATION_CONSOLE', true);

class Controller extends \Hazaar\Controller\Action {

    private $passwd = null;

    private $handler;

    public function init(){

        $this->handler = new Handler($this->application);

        if($this->getAction() === 'login')
            return;

        if(!$this->handler->authenticated())
            return $this->redirect($this->application->url('hazaar', 'console', 'login'));

    }

    public function login(){

        if($this->request->isPOST()){

            if($this->handler->authenticate($this->request->username, $this->request->password))
                $this->redirect($this->application->url('hazaar', 'console'));

            $this->view->msg = 'Login failed';

        }

        $this->layout('@console/login');

        $this->view->link('console/css/login.css');

        $this->view->addHelper('fontawesome');

    }

    public function logout(){

        $this->handler->deauth();

        $this->redirect($this->application->url('hazaar'));

    }


    /**
     * Launch the Hazaar MVC Management Console
     */
    public function __default($controller, $action){

        $path = LIBRARY_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'console';

        $this->handler->load(new Application('app', $path, $this->application));

        $this->handler->load(new System('sys', $path, $this->application));

        $this->handler->loadComposerModules($this->application);

        return $this->handler->exec($this, $action, $this->request);

    }

    public function doc(){

        dump('yay!');

    }

}

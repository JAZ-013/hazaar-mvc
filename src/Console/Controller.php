<?php

namespace Hazaar\Console;

define('APPLICATION_CONSOLE', true);

class Controller extends \Hazaar\Controller\Action {

    private $passwd = null;

    private $handler;

    public function init(){

        $this->handler = new Handler();

        if($this->getAction() === 'login')
            return;

        if(!$this->handler->authenticated())
            return $this->redirect($this->url('login'));

    }

    public function login(){

        if($this->request->isPOST()){

            if($this->handler->authenticate($this->request->username, $this->request->password))
                $this->redirect($this->url());

            $this->view->msg = 'Login failed';

        }

        $this->layout('@console/login');

        $this->view->link('console/css/layout.css');

        $this->view->link('console/css/login.css');

        $this->view->addHelper('fontawesome');

    }

    public function logout(){

        $this->handler->deauth();

        $this->redirect($this->url());

    }


    /**
     * Launch the Hazaar MVC Management Console
     */
    public function __default($controller, $action){

        $this->handler->loadModules($this->application);

        return $this->handler->exec($this, $this->request);

    }

}

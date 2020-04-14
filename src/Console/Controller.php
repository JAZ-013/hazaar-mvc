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

    public function index(){

        if(!$this->handler instanceof Handler)
            throw new \Exception('Module requires a console handler before being initialised!');

        $this->view->layout('@console/layout');

        $this->view->link($this->application->url('hazaar/file/console/css/layout.css'));

        $this->view->link($this->application->url('hazaar/file/css/bootstrap.min.css'));

        $this->view->addHelper('hazaar', array('base_url' => $this->application->url('hazaar/console')));

        $this->view->addHelper('fontawesome');

        $this->view->requires($this->application->url('hazaar/file/console/js/jquery.min.js'));

        $this->view->requires($this->application->url('hazaar/file/console/js/console.js'));

        $this->view->requires($this->application->url('hazaar/file/js/popup.js'));

        $this->view->user = array(
            'fullname' => $this->handler->getUser(),
            'group' => 'Administrator'
        );

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
            'url' => (string)$this->application->url('hazaar/console'),
            'items' => []
        ];

        foreach($modules as $module_name => $module)
            $menuItems['items'][$module_name] = $module->menu();

        return $menuItems;

    }

    public function files(){

        return [];
        
    }

    public function doc(){

        dump('yay!');

    }

}

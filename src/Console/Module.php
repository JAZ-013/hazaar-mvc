<?php

namespace Hazaar\Console;

abstract class Module extends \Hazaar\Controller\Action {

    protected $request;

    protected $handler;

    public $view_path;

    public $notices = array();

    private $module_info = array('label' => 'Module', 'icon' => 'bars');

    final function __construct($name, $path, $application){

        $this->view_path = $path;

        parent::__construct($name, $application, false);

    }

    final public function __configure(Handler $handler){

        $this->handler = $handler;

    }

    public function __initialize(\Hazaar\Application\Request $request){

        if(!$this->handler instanceof Handler)
            throw new \Exception('Module requires a console handler before being initialised!');

        $this->view->layout('@console/layout');

        $this->view->link($this->application->url('hazaar/file/css/popup.css'));

        $this->view->link($this->application->url('hazaar/file/console/css/layout.css'));

        $this->view->addHelper('hazaar', array('base_url' => $this->application->url('hazaar/console')));

        $this->view->addHelper('jQuery');

        $this->view->addHelper('fontawesome');

        $this->view->requires($this->application->url('hazaar/file/js/popup.js'));

        $this->view->requires($this->application->url('hazaar/file/console/js/console.js'));

        $this->view->navitems = $this->handler->getNavItems();

        $this->view->notices = array();

        $this->view->user = array(
            'fullname' => $this->handler->getUser(),
            'group' => 'Administrator'
        );

        parent::__initialize($request);

    }

    public function load(){

        return true;

    }

    public function init(){

        return true;

    }

    protected function addMenuItem($label, $icon = null, $suffix = null){

        return $this->handler->addMenuItem($this, $label, null, $icon, $suffix);

    }

    public function url($action = null, $params = array()){

        return $this->application->url('hazaar/console', $action, $params);

    }

    public function active(){

        return call_user_func_array(array($this->application, 'active'), array_merge(array('hazaar', 'console'), func_get_args()));

    }

    public function file(){

        $file = new \Hazaar\Controller\Response\File($this->view_path . DIRECTORY_SEPARATOR . $this->request->getPath());

        return $file;

    }

    public function notice($msg, $icon = 'bell', $class = null){

        $this->view->notices[] = array(
            'msg' => $msg,
            'class' => $class,
            'icon' => $icon
        );

    }

    public function setActiveMenu(MenuItem $item){

        $this->module_info = array('label' => $item->label, 'icon' => $item->icon);

    }

    public function  getActiveMenu(){

        return $this->view->html->div(array(
            $this->view->html->i()->class('fa fa-' . $this->module_info['icon']),
            ' ',
            $this->module_info['label']
        ));

    }

}
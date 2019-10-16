<?php

namespace Hazaar\Console;

class Application extends Module {

    public function init(){

        $this->addMenuGroup('Application', 'bars');

        $this->addMenuItem('Models', 'models', 'sitemap', 3);

        $this->addMenuItem('Views', 'views', 'binoculars', 12);

        $this->addMenuItem('Controllers', 'controllers', 'code-fork', 5);

        $this->addMenuItem('Configuration', 'configs', 'cogs');

    }

    public function index($request){

        $this->view('index');

        $this->view->requires('js/application.js');

    }

    public function models($request){

        $this->view('models');

    }

    public function views($request){

        $this->view('views');

    }

    public function controllers($request){

        $this->view('controllers');

    }

    public function configs($request){

        if($request->isXMLHttpRequest()){

            if($request->has('encrypt')){

                if(!($filename = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, $request->encrypt)))
                    throw new \Hazaar\Exception('Config file not found!', 404);

                $file = new \Hazaar\File($filename);

                if($file->isEncrypted())
                    $file->decrypt();
                else
                    $file->encrypt();

                return array('encrypt' => $file->isEncrypted());

            }

            $search_paths = $this->application->loader->getSearchPaths(FILE_PATH_CONFIG);

            $files = array();

            foreach($search_paths as $path){

                $dir = new \Hazaar\File\Dir($path);

                $config_files = $dir->find('*.json');

                foreach($config_files as $config_file){

                    $file = new \Hazaar\File($config_file);

                    $encrypted = $file->isEncrypted();

                    $files[] = array(
                        'name' => trim(str_replace($path, '', $file->fullpath()), DIRECTORY_SEPARATOR),
                        'size' => $file->size(),
                        'encrypted' => $encrypted
                    );

                }

            }

            return $files;

        }

        $this->view('configs');

        $this->view->requires('js/configs.js');

        if(!(\Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key')))
            $this->notice('There is no application .key file.  Encrypting files will use the defaut key which is definitely NOT RECOMMENDED!', 'key', 'danger');

    }

    public function system($request){

        $this->view('system');


    }

    public function phpinfo($request){

        $this->view('phpinfo');

    }

}
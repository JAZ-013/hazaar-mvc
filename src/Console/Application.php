<?php

namespace Hazaar\Console;

class Application extends Module {

    private $config;

    private $metrics;

    public function load(){

        $this->config = new \Hazaar\Application\Config('application', null, $this->application->getDefaultConfig());

        $group = $this->addMenuItem('Application', 'bars');

        $group->addMenuItem('Models', 'models', 'cubes');

        $group->addMenuItem('Views', 'views', 'eye');

        $group->addMenuItem('Controllers', 'controllers', 'gamepad');

        if($this->config->app['metrics'] === true){

            $this->metrics = new \Hazaar\File\Metric($this->application->runtimePath('metrics.dat'));

            $group->addMenuItem('Metrics', 'metrics', 'chart-line');

        }

        $group->addMenuItem('Configuration', 'config', 'cogs');

        $group->addMenuItem('File Encryption', 'encrypt', 'key');

        $group->addMenuItem('Cache Settings', 'cache', 'bolt');

    }

    public function index(){

        $this->view('index');

        $this->view->requires('js/application.js');

        $this->view->config = $this->config;

        $this->view->libraries = $this->handler->getLibraries();

        $date_start = strtotime('now - 1 month');

        if($this->metrics instanceof \Hazaar\File\Metric){

            $count_month = array_filter(ake($this->metrics->graph('hits', 'count_1year'), 'ticks'),
                function($value, $key) use($date_start){
                    return $key > $date_start;
                }, ARRAY_FILTER_USE_BOTH);

            $this->view->stats = array(
                'hour' => array_sum(ake($this->metrics->graph('hits', 'count_1hour'), 'ticks')),
                'day' => array_sum(ake($this->metrics->graph('hits', 'count_1day'), 'ticks')),
                'week' => array_sum(ake($this->metrics->graph('hits', 'count_1week'), 'ticks')),
                'month' => array_sum($count_month)
            );

        }

    }

    public function metrics(){

        if(!$this->metrics instanceof \Hazaar\File\Metric)
            throw new \Exception('Metrics are not enabled!', 501);

        $this->view('application/metrics');

        $this->view->requires('https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.7.3/Chart.min.js');

        $this->view->requires('js/metrics.js');

    }

    public function graphs() {

        if(!$this->metrics instanceof \Hazaar\File\Metric)
            throw new \Exception('Metrics are not enabled!', 501);

        $graphs = array();

        if($this->metrics->hasDataSource('hits')){

            $graphs[] = array(
                array(
                    'ds'=> 'hits',
                    'archive'=> 'count_1hour',
                    'scale'=> 'Count',
                    'bgcolor'=> 'rgba(132, 99, 255, 0.2)',
                    'color'=> 'rgba(132,99,255,1)'
                ),
                array(
                    'ds'=> 'hits',
                    'archive'=> 'avg_1day',
                    'scale'=> 'Count',
                    'bgcolor'=> 'rgba(132, 99, 255, 0.2)',
                    'color'=> 'rgba(132,99,255,1)'
                )
            );

        }

        if($this->metrics->hasDataSource('exec')){

            $graphs[] =  array(
                array(
                    'ds'=> 'exec',
                    'archive'=> 'count_1hour',
                    'scale'=> 'ms',
                    'bgcolor'=> 'rgba(255, 99, 132, 0.2)',
                    'color'=> 'rgba(255,99,132,1)' ),
                array(
                    'ds'=> 'exec',
                    'archive'=> 'avg_1day',
                    'scale'=> 'ms',
                    'bgcolor'=> 'rgba(255, 99, 132, 0.2)',
                    'color'=> 'rgba(255,99,132,1)'
                )
            );

        }

        if($this->metrics->hasDataSource('mem')){

            $graphs[] = array(
                array(
                    'ds'=> 'mem',
                    'archive'=> 'count_1hour',
                    'scale'=> 'bytes',
                    'bgcolor'=> 'rgba(132, 99, 255, 0.2)',
                    'color'=> 'rgba(132,99,255,1)'
                ),
                array(
                    'ds'=> 'mem',
                    'archive'=> 'avg_1day',
                    'scale'=> 'bytes',
                    'bgcolor'=> 'rgba(255, 99, 132, 0.2)',
                    'color'=> 'rgba(255,99,132,1)'
                )
            );

        }

        return $graphs;

    }

    public function stats(){

        if(!$this->metrics instanceof \Hazaar\File\Metric)
            throw new \Exception('Metrics are not enabled!', 501);

        if(!$this->request->isPost())
            throw new \Exception('Method not allowed!', 405);

        if(!$this->request->has('name'))
            throw new \Exception('No datasource name', 400);

        if(!$this->request->has('archive'))
            throw new \Exception('No archive name', 400);

        if(($result = $this->metrics->graph($this->request->name, $this->request->archive)) === false)
            throw new \Exception('No data!', 204);

        if($this->request->has('args'))
            $result['args'] = $this->request->args;

        $ticks = array();

        foreach($result['ticks'] as $tick => $value)
            $ticks[date('H:i', $tick)] = $value;

        $result['ticks'] = $ticks;

        return $result;

    }

    public function models(){

        $this->view('application/models');

        $models = array();

        foreach($this->application->loader->getSearchPaths(FILE_PATH_MODEL) as $path){

            $dir = new \Hazaar\File\Dir($path);

            while($file = $dir->read())
                $models[] = $file;

        }

        $this->view->models = $models;

    }

    public function views(){

        $this->view('application/views');

        $views = array();

        foreach($this->application->loader->getSearchPaths(FILE_PATH_VIEW) as $path){

            $dir = new \Hazaar\File\Dir($path);

            $views = array_merge($views, $dir->find('/.*\.phtml/', false, false));

        }

        $this->view->views = $views;

    }

    public function controllers(){

        $this->view('application/controllers');

        $controllers = array();

        foreach($this->application->loader->getSearchPaths(FILE_PATH_CONTROLLER) as $path){

            $dir = new \Hazaar\File\Dir($path);

            while($file = $dir->read())
                $controllers[] = $file;

        }

        $this->view->controllers = $controllers;

    }

    public function config(){

        \Hazaar\Application\Config::$override_paths = null;

        $config = new \Hazaar\Application\Config('application', $this->request->get('env', APPLICATION_ENV));

        if($this->request->isPost()){

            if($config->fromJSON($this->request->config)){

                if($config->write())
                    $this->redirect($this->url('app/config', array('env' => $config->getEnv())));

                $this->notice('An error ocurred writing the config file.', 'exclamation-triangle', 'danger');

            }else
                $this->notice('Invalid JSON.  Please fix and try again!', 'exclamation-triangle', 'warning');

            $data = $this->request->config;

        }else
            $data = json_encode($config->getEnvConfig(), JSON_PRETTY_PRINT);

        $this->view('application/config');

        $this->view->requires('js/config.js');

        $this->view->extend(array(
            'config' => $data,
            'env' => $config->getEnv(),
            'envs' => $config->getEnvironments(),
            'writable' => $config->isWritable()
        ));

    }

    public function encrypt(){

        if($this->request->isXMLHTTPRequest()){

            if($this->request->has('encrypt')){

                if(!($filename = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, $this->request->encrypt)))
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

        $this->view('application/encrypt');

        $this->view->requires('js/encrypt.js');

        if(!(\Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, '.key')))
            $this->notice('There is no application .key file.  Encrypting files will use the defaut key which is definitely NOT RECOMMENDED!', 'key', 'danger');

    }

    public function cache(){

        $this->view('cache/settings');
        
    }

}

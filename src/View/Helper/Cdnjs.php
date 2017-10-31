<?php
/**
 * @file        Hazaar/View/Helper/Cdnjs.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2016 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\View\Helper;

/**
 * CDNJS view helper
 *
 * This view helper handles loading any available libraries hosted by CDNJS.
 *
 * @since       2.1.2
 */
class Cdnjs extends \Hazaar\View\Helper {

    private $cache_local = false;

    private $libraries = array();

    static private $cache;

    public function import() {

        if(!self::$cache instanceof \Hazaar\Btree)
            self::$cache = new \Hazaar\Btree(\Hazaar\Application::getInstance()->runtimePath('cdnjs.db'));

    }

    public function init(\Hazaar\View\Layout $view, $args = array()) {

        $this->cache_local = ake($args, 'cache_local', false);

        if($libs = ake($args, 'libs')){

            foreach($libs as $lib){

                if(!is_array($lib))
                    $lib = array('name' => $lib);

                if(!array_key_exists('name', $lib))
                    continue;

                $this->load(ake($lib, 'name'), ake($lib, 'version'), ake($lib, 'files'), ake($lib, 'priority'));

            }

        }

    }

    public function run($view) {

        $view->setImportPriority(100);

        uasort($this->libraries, function($a, $b){
            if ($a['priority'] == $b['priority'])
                return 0;
            return ($a['priority'] > $b['priority']) ? -1 : 1;
        });

        foreach($this->libraries as $name => &$info){

            if(!array_key_exists('files', $info))
                continue;

            foreach($info['files'] as &$file){

                if($this->cache_local){

                    //Create the directory here so that we know later that it is OK to load this library
                    \Hazaar\Application::getInstance()->runtimePath('cdnjs' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $info['version'], true);

                    $url = $this->application->url('hazaar', 'view/helper/cdnjs/lib/' . $name . '/' . $info['version'] . '/' . $file)->encode();

                }else{

                    $url =  $this->url($name, $info['version'], $file);

                }

                if(strtolower(substr($file, -3)) == '.js')
                    $view->requires($url);
                else
                    $view->link($url);

            }

        }

    }

    public function load($name, $version = null, $files = null, $priority = 0){

        if(in_array($name, $this->libraries))
            return false;

        if(!($info = $this->getLibraryInfo($name)))
            return false;

        if(!array_key_exists('assets', $info))
            throw new \Exception('CDNJS: Package info for ' . $name . ' does not contain any assets!');

        $info['priority'] = $priority;

        if($version)
            $info['version'] = $version;

        $version_found = false;

        if($files && is_array($files)){

            foreach($info['assets'] as &$asset){

                if($asset['version'] != $info['version'])
                    continue;

                $version_found = true;

                foreach($files as $file){

                    if(in_array($file, $asset['files']))
                        $info['files'][] = $file;

                }

                break;

            }

        }else{

            $version_found = true;

            $info['files'] = array($info['filename']);

        }

        if($version_found === false)
            throw new \Exception('CDNJS: Version ' . $info['version'] . ' is not available in package ' . $name);

        $this->libraries[$name] = $info;

        return true;

    }

    public function getLibraryInfo($name){

        if(($info = self::$cache->get($name)) !== null)
            return $info;

        if(!($data = json_decode(file_get_contents('https://api.cdnjs.com/libraries/' . $name), true)))
            throw new \Exception('CDNJS: Error parsing package info!');

        self::$cache->set($name, $data);

        return $data;

    }

    private function url($name, $version, $path){

        return 'https://cdnjs.cloudflare.com/ajax/libs/' . $name . '/' . $version . '/' . ltrim($path, '/');

    }

    public function lib($request){

        $app = \Hazaar\Application::getInstance();

        $app_url = (string)$app->url();

        if(!substr($request->referer(), 0, strlen($app_url)) == $app_url)
            throw new \Exception('You are not allowed to access this resource!', 403);

        list($name, $version, $file) = explode('/', $request->getPath(), 3);

        $path = $app->runtimePath('cdnjs' . DIRECTORY_SEPARATOR . $name . DIRECTORY_SEPARATOR . $version);

        if(!file_exists($path))
            throw new \Exception('This library is not currently accessible!', 404);

        $cacheFile = new \Hazaar\File($path . DIRECTORY_SEPARATOR . $file);

        if(!$cacheFile->exists()){

            $filePath = $cacheFile->dirname();

            if(!file_exists($filePath))
                mkdir($filePath, 0775, TRUE);

            $url = $this->url($name, $version, $file);

            $cacheFile->set_contents(file_get_contents($url));

            $cacheFile->save();

        }

        $response = new \Hazaar\Controller\Response\File($cacheFile);

        $response->setUnmodified($request->getHeader('If-Modified-Since'));

        return $response;

    }

}

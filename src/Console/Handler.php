<?php

namespace Hazaar\Console;

class Handler {

    private $passwd;

    private $session_key = 'hazaar-console-user';

    private $modules = array();

    private $menus = array();

    private $application;

    public function __construct(){

        $this->passwd = CONFIG_PATH . DIRECTORY_SEPARATOR . '.passwd';

        if(!file_exists($this->passwd))
            die('Hazaar admin console is currently disabled!');

        session_start();

    }

    public function authenticated(){

        if(ake($_SESSION, $this->session_key))
            return true;

        $headers = hazaar_request_headers();

        if(!($authorization = ake($headers, 'Authorization')))
            return false;

        list($method, $code) = explode(' ', $authorization);

        if(strtolower($method) != 'basic')
            throw new \Hazaar\Exception('Unsupported authorization method: ' . $method);

        list($identity, $credential) = explode(':', base64_decode($code));

        return $this->authenticate($identity, $credential);

    }

    public function authenticate($username, $password){

        $users = array();

        $lines = explode("\n", trim(file_get_contents($this->passwd)));

        foreach($lines as $line){

            if(!$line)
                continue;

            list($identity, $userhash) = explode(':', $line);

            $users[$identity] = $userhash;

        }

        $credential = trim(ake($users, $username));

        if(strlen($credential) > 0){

            $hash = '';

            if(substr($credential, 0, 6) == '$apr1$'){                      //APR1-MD5

                $BASE64_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

                $APRMD5_ALPHABET = './0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz';

                $parts = explode('$', $credential);

                $salt = substr($parts[2], 0, 8);

                $max = strlen($password);

                $context = $password . '$apr1$' . $salt;

                $binary = pack('H32', md5($password . $salt . $password));

                for($i=$max; $i>0; $i-=16)
                    $context .= substr($binary, 0, min(16, $i));

                for($i=$max; $i>0; $i>>=1)
                    $context .= ($i & 1) ? chr(0) : $password[0];

                $binary = pack('H32', md5($context));

                for($i=0; $i<1000; $i++) {

                    $new = ($i & 1) ? $password : $binary;

                    if($i % 3) $new .= $salt;

                    if($i % 7) $new .= $password;

                    $new .= ($i & 1) ? $binary : $password;

                    $binary = pack('H32', md5($new));

                }

                $hash = '';

                for ($i = 0; $i < 5; $i++) {

                    $k = $i + 6;

                    $j = $i + 12;

                    if($j == 16) $j = 5;

                    $hash = $binary[$i] . $binary[$k] . $binary[$j] . $hash;

                }

                $hash = chr(0) . chr(0) . $binary[11] . $hash;

                $hash = strtr(strrev(substr(base64_encode($hash), 2)), $BASE64_ALPHABET, $APRMD5_ALPHABET);

                $hash = '$apr1$' . $salt . '$' . $hash;

            }elseif(substr($credential, 0, 5) == '{SHA}'){                  //SHA1

                $hash = '{SHA}' . base64_encode(sha1($password, TRUE));

            }elseif(substr($credential, 0, 4) == '$2y$'){                   //Blowfish

                $hash = crypt($password, substr($credential, 0, 29));       //Hash is $2y$ + two digit cost + $ + 22 character salt from stored credentail

            }else{

                throw new \Hazaar\Exception('Unsupported password encryption algorithm.');

            }

            if($hash == $credential){

                $_SESSION[$this->session_key] = $username;

                return true;

            }

        }

        return false;

    }

    public function deauth(){

        session_unset();

    }

    public function loadModules($application){

        $path = LIBRARY_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs' . DIRECTORY_SEPARATOR . 'console';

        $this->modules['app'] = new Application('app', $path, $application, $this);

        $this->modules['sys'] = new System('sys', $path, $application, $this);

        $installed = ROOT_PATH
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'composer'
            . DIRECTORY_SEPARATOR . 'installed.json';

        if(file_exists($installed)){

            $libraries = json_decode(file_get_contents($installed), true);

            usort($libraries, function($a, $b){
                if ($a['name'] == $b['name'])
                    return 0;
                return ($a['name'] < $b['name']) ? -1 : 1;
            });

            foreach($libraries as $library){

                if(!(($name = substr(ake($library, 'name'), 18))
                    && ake($library, 'type') == 'library'
                    && $consoleClass = ake(ake($library, 'extra'), 'hazaar-console-class')))
                    continue;

                if(!class_exists($consoleClass))
                    continue;

                if(!($path = $this->getSupportPath($consoleClass)))
                    continue;

                $this->modules[$name] = new $consoleClass($name, $path . DIRECTORY_SEPARATOR . 'console', $application, $this);

            }

        }

        foreach($this->modules as $module)
            if(\method_exists($module, 'load'))
                $module->load();

        $this->application = $application;

        return;

    }

    private function getSupportPath($className = null){

        if(!$className)
            $className = $this->className;

        $reflect = new \ReflectionClass($className);

        $path = dirname($reflect->getFileName());

        while(!file_exists($path . DIRECTORY_SEPARATOR . 'composer.json'))
            $path = dirname($path);

        $libs_path = $path . DIRECTORY_SEPARATOR . 'libs';

        if(file_exists($libs_path))
            return $libs_path;

        return false;

    }

    public function moduleExists($name){

        return array_key_exists($name, $this->modules);

    }

    public function exec(\Hazaar\Controller $controller, $module_name, \Hazaar\Application\Request $request){

        if(!$this->moduleExists($module_name))
            throw new \Hazaar\Exception("Console module '$module_name' does not exist!", 404);

        //if(!($action = $request->popPath()))
        //    $action = 'index';

        $module = $this->modules[$module_name];

        //if(!method_exists($module, $action))
        //    throw new \Hazaar\Exception("Method '$action' not found on module '$module_name'", 404);

        if($module->view_path)
            $this->application->loader->setSearchPath(FILE_PATH_VIEW, $module->view_path);

        $module->setBasePath('hazaar/console');

        $module->__initialize($request);

        $response = $module->__run();

        if(!$response instanceof \Hazaar\Controller\Response){

            if(is_array($response)){

                $response = new \Hazaar\Controller\Response\Json($response);

            }else{

                $response = new \Hazaar\Controller\Response\Html();

                $module->_helper->execAllHelpers($module, $response);

            }

        }

        return $response;

    }

    public function getNavItems(){

        return $this->menus;

    }

    public function addMenuGroup($module, $label, $icon = null, $url = null){

        $name = $module->getName();

        if(array_key_exists($name, $this->menus))
            return false;

        $this->menus[$name] = array(
            'label' => $label,
            'icon' => $icon,
            'target' => $module->getName() . ($url? '/' . $url:null),
            'items' => array()
        );

        return true;

    }

    public function addMenuItem($module, $label, $url = null, $icon = null, $suffix = null){

        $group = $module->getName();

        if(!array_key_exists($group, $this->menus))
            return false;

        $this->menus[$group]['items'][] = array(
            'label' => $label,
            'target' => $module->getName() . ($url? '/' . $url:null),
            'icon' => $icon,
            'suffix' => (is_array($suffix)?$suffix:array($suffix))
        );

        return true;

    }


}
<?php

/**
 * @file        Hazaar/Loader.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright(c)2012 Jamie Carl(http://www.hazaarlabs.com)
 */
namespace Hazaar;

/**
 * @brief Constant to indicate a path contains config files
 */
define('FILE_PATH_ROOT', 'root');

/**
 * @brief Constant to indicate a path contains config files
 */
define('FILE_PATH_CONFIG', 'config');

/**
 * @brief Constant to indicate a path contains model classes
 */
define('FILE_PATH_MODEL', 'model');

/**
 * @brief Constant to indicate a path contains view files
 */
define('FILE_PATH_VIEW', 'view');

/**
 * @brief Constant to indicate a path contains controller classes
 */
define('FILE_PATH_CONTROLLER', 'controller');

/**
 * @brief Constant to indicate a path contains service classes
 */
define('FILE_PATH_SERVICE', 'service');

/**
 * @brief Constant to indicate a path contains Support files
 */
define('FILE_PATH_SUPPORT', 'support');

/**
 * @brief Constant to indicate a path in the library path
 */
define('FILE_PATH_LIB', 'library');

/**
 * @brief Constant to indicate a path in the public path
 */
define('FILE_PATH_PUBLIC', 'public');

define('LINE_BREAK', ((substr(PHP_OS, 0, 3) == 'WIN')?"\r\n":"\n"));

/**
 * @brief Constant containing the absolute filesystem path that contains the whole project.
 */
define('ROOT_PATH', realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..'));

/**
 * @brief Constant containing the absolute filesystem path to the default configuration directory.
 */
define('CONFIG_PATH', realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . 'configs'));

/**
 * @brief Constant containing the absolute filesystem path to the application public directory.
 */
define('PUBLIC_PATH', realpath(APPLICATION_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'public'));

/**
 * @brief Constant containing the absolute filesystem path to the HazaarMVC library
 */
define('LIBRARY_PATH', realpath(dirname(__FILE__)));

/**
 * @brief Constant containing the absolute filesystem path to the HazaarMVC support library
 */
define('SUPPORT_PATH', realpath(LIBRARY_PATH . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'libs'));

/**
 * @brief Global class file loader
 *
 * @detail This class contains methods for auto-loading classes from files in the Hazaar library path. Ordinarily
 * there will be no need for developers to use this class directly but it does contain a few methods for
 * working with paths and library files.
 *
 * This class is not meant to be instantiated directly and instances should be retrieved using the
 * Loader::getInstance()method.
 *
 * ### Example
 *
 * <code class="php">
 * $loader = Hazaar\Loader::getInstance();
 * $loader->loadController('index');
 * </code>
 *
 * p(notice notice-info) . The loader class is loaded automatically when starting the application.
 * There should be no need to use the Loader instance directly and static methods have been provided for
 * some extra functionality.</div>
 *
 * p(notice notice-warning) . Instantiating this class directly can have undefined results.</div>
 *
 * @since 1.0.0
 */
class Loader {

	private $application;

	public $paths = array();

	private static $instance;

	/**
     * @brief Initialise a new loader
     *
     * @detail p(notice notice-warning) . Do NOT instantiate this class directly. See Loader::getInstance()
     * on how to get a new Loader instance.</div>
     */
	function __construct($application){

		$this->application = $application;

		if(! Loader::$instance instanceof Loader)
			Loader::$instance = $this;

		/*
         * Add some default search paths
         */
        $this->addSearchPath(FILE_PATH_ROOT, ROOT_PATH);

		$this->addSearchPath(FILE_PATH_CONFIG, CONFIG_PATH);

		$this->addSearchPath(FILE_PATH_LIB, LIBRARY_PATH);

		$this->addSearchPath(FILE_PATH_PUBLIC, PUBLIC_PATH);

        $this->addSearchPath(FILE_PATH_SUPPORT, SUPPORT_PATH);

	}

    static public function fixDirectorySeparator($path){

        return str_replace(((DIRECTORY_SEPARATOR == '/')? '\\' : '/'), DIRECTORY_SEPARATOR, $path);

    }

	/**
     * @detail Return the current instance of the Loader object.
     *
     * @since 1.0.0
     *
     * @param Application $application
     *        	The current application instance
     */
	static function getInstance($application = NULL){

		if(! Loader::$instance instanceof Loader)
			Loader::$instance = new Loader($application);

		elseif($application)
			Loader::$instance->setApplication($application);

		return Loader::$instance;

	}

	public function setApplication($application){

		$this->application = $application;

	}

	/**
     * @detail Register this loader instance as a class autoloader
     *
     * @since 1.0.0
     */
	public function register(){

		spl_autoload_register(array($this,'loadClassFromFile'));

	}

	/**
     * @detail Unregister this loader instance as a class autoloader
     *
     * @since 1.0.0
     */
	public function unregister(){

		spl_autoload_unregister(array($this,'loadClassFromFile'));

	}

	public function addIncludePath($path){

		set_include_path(get_include_path() . PATH_SEPARATOR . $path);

	}

	/**
     * @detail Add a new search path for loading classes from library files
     *
     * The path type can be anything if you are using the loader to load your own library files. There are
     * built in path types for loading Hazaar library files.
     *
     * * FILE_PATH_ROOT - Path that contains the whole project
     * * FILE_PATH_MODEL - Path contains model classes
     * * FILE_PATH_VIEW - Path contains view files.
     * * FILE_PATH_CONTROLLER - Path contains controller classes.
     * * FILE_PATH_SUPPORT - Path contains support files. Used by the Application::runDirect()method.
     * * FILE_PATH_CONFIG - Configuration files
     *
     * @since 1.0.0
     * @param string $type The path type to add.
     * @param string $path The path to add.
     *
     */
	public function addSearchPath($type, $path){

        $is_win = (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN');

        if(($is_win && $path[1] != ':' && $path[2] != DIRECTORY_SEPARATOR)
            || (!$is_win && $path[0] != DIRECTORY_SEPARATOR)){

            $path = ROOT_PATH . DIRECTORY_SEPARATOR . $path;

        }

		if($path = realpath($path)){

            if(! array_key_exists($type, $this->paths)|| ! in_array($path, $this->paths[$type]))
                $this->paths[$type][] = $path;

			return TRUE;

		}

		return FALSE;
	}

    /**
     * Sets the search path for a file type
     *
     * This is the same as addSearchPath except that it overwrites any existing paths.
     *
     * @since 2.3.3
     * @param mixed $type The path type to add.
     * @param mixed $path The path to add.
     * @return boolean
     */
    public function setSearchPath($type, $path){

        $this->paths[$type] = array();

        return $this->addSearchPath($type, $path);

    }

	/**
     * @detail Add multiple search paths from an array
     *
     * @since 1.0.0
     *
     * @param Array $array
     *        	Array containing type/path pairs.
     */
	public function addSearchPaths($array){

		if(is_array($array)|| $array instanceof Map){

			foreach($array as $type => $path)
				$this->addSearchPath($type, APPLICATION_PATH . DIRECTORY_SEPARATOR . $path);

		}

	}

	/**
     * @detail Return an array of search paths for this loader instance
     *
     * @since 1.0.0
     *
     * @return Array Array of search paths
     */
	public function getSearchPaths($type = NULL){

		if($type){

			if(array_key_exists($type, $this->paths))
				return $this->paths[$type];

        } else {

			return $this->paths;

		}

		return NULL;

	}

	static private function resolveRealPath($filename, $case_insensitive = FALSE){

		if(file_exists($filename)){

			return realpath($filename);

		} elseif($case_insensitive){

			$dirname = dirname($filename);

			$filename = strtolower(basename($filename));

			if(! file_exists($dirname))
				return NULL;

			$dir = dir($dirname);

			while(($file = $dir->read()) !== FALSE){

				if(substr($file, 0, 1) == '.')
					continue;

				if(strtolower($file) == $filename)
					return realpath($dirname . DIRECTORY_SEPARATOR . $file);

			}

		}

		return NULL;
	}

    static public function isAbsolutePath($path){

        return(substr($path, 1, 1) == ':' || substr($path, 0, 1) == DIRECTORY_SEPARATOR);

    }

	/**
     * @detail Return the absolute filesystem path to a file.
     * By default this method uses the application
     * path as the base path.
     *
     * This method also checks that the file exists. If the file does not exist then null will be
     * returned.
     *
     * @since 1.0.0
     *
     * @param string $type
     *        	The path type to search. See Loader::addSearchPath()
     *
     * @param string $filename
     *        	The name of the file to check and return the path to.
     *
     * @param string $base_path
     *        	The path to use as a search base if there are no paths of the requested
     *        	type.
     *
     * @param boolean $case_insensitive
     *        	By default paths are case sensitive. In some circumstances this might
     *        	not
     *        	be desirable so set this TRUE to perform a(slower)case insensitive
     *        	search.
     *
     * @return string The absolute path to the file if it exists. NULL otherwise.
     *
     */
	static public function getFilePath($type, $search_file = NULL, $base_path = APPLICATION_PATH, $case_insensitive = FALSE){

		if(! $base_path)
			$base_path = APPLICATION_PATH;

		$loader = Loader::getInstance();

        $search_file = Loader::fixDirectorySeparator($search_file);

        //If the search file is an absolute path just return it if it exists.
        if(Loader::isAbsolutePath($search_file)){

            return Loader::resolveRealPath($search_file);

        }elseif($paths = $loader->getSearchPaths($type)){

			foreach($paths as $path){

				$filename = $path . DIRECTORY_SEPARATOR . $search_file;

				if($realpath = Loader::resolveRealPath($filename, $case_insensitive))
					return $realpath;

			}

		} else {

			$absolute_path = $base_path . DIRECTORY_SEPARATOR . $type . DIRECTORY_SEPARATOR . $search_file;

			if(file_exists($absolute_path))
				return realpath($absolute_path);

		}

		return NULL;

	}

    static public function getModuleFilePath($search_file = null, $module = null, $case_insensitive = false){

        $match_path = ROOT_PATH
            . DIRECTORY_SEPARATOR . 'vendor'
            . DIRECTORY_SEPARATOR . 'hazaarlabs' . DIRECTORY_SEPARATOR;

        if($module === null){

            $calling_file = debug_backtrace()[1]['file'];

            if(substr($calling_file, 0, strlen($match_path)) !== $match_path)
                return false;

            if(!preg_match('/^hazaar\-(\w+)/', substr($calling_file, strlen($match_path)), $matches))
                return false;

            $module = $matches[1];

        }

        $path = $match_path . 'hazaar-' . $module . DIRECTORY_SEPARATOR . 'libs';

        return Loader::resolveRealPath($path . DIRECTORY_SEPARATOR . $search_file);

    }

	/**
     * @detail Resolve a filename within any of the search paths
     *
     * @since 1.0.0
     *
     * @return string Absolute path to the file
     */
	static public function resolve($filename){

		$paths = explode(PATH_SEPARATOR, get_include_path());

		foreach($paths as $path){

			$target = $path . DIRECTORY_SEPARATOR . 'Hazaar' . DIRECTORY_SEPARATOR . $filename;

			if(file_exists($target))
				return $target;

		}

		return NULL;

	}

	/**
     * @detail This method is used to load a new instance of a controller class.
     * There are some
     * built-in 'magic controllers' that this method will automatically load upon request.
     *
     * These controllers are:
     *
     * * style - Returns a[[Hazaar\Controller\Style]] object to handle output for CSS stylesheets.
     * * script - Returns a[[Hazaar\Controller\Script]] object to handle output of JavaScript files.
     *
     * If no controller can be found the default site controller will be loaded.
     *
     * @since 1.0.0
     *
     * @param string $controller
     *        	The name of the controller to load. This can be _style_ or _script_ to
     *        	load Style and Script controllers.
     *
     * @return mixed A controller instance (\Hazaar\Application\Controller) or FALSE.
     */
	public function loadController($controller){

		$newController = NULL;

        try {

            /*
             * Check for magic controllers
             *
             * Magic controllers are are controllers that are handled internally. These can be
             * either 'style', or 'script' to serve up compressed CSS or JS files, the hazaar
             * controller, etc.
             */
            if(array_key_exists($controller, \Hazaar\Application\Router::$internal)){

                $newController = new \Hazaar\Application\Router::$internal[$controller]($controller, $this->application);

            }else{

                //Build a list of controllers to search for
                $controller_class_search = array();

                if($controller){

                    $parts = explode('/', $controller);

                    $controller_class_search[] = 'Application\\Controller\\' . implode('\\', array_map('ucfirst', $parts));

                    //Legacy controller name search
                    if(count($parts) === 1)
                        $controller_class_search[] = ucfirst($controller) . 'Controller';

                }

                //If the default controller is active, search for that too.
                if(boolify($this->application->config->app->useDefaultController)){

                    $parts = explode('/', $this->application->config->app->defaultController);

                    $controller_class_search[] = 'Application\\Controller\\' . implode('\\', array_map('ucfirst', $parts));

                    //Legacy controller name search
                    if(count($parts) === 1)
                        $controller_class_search[] = ucfirst($this->application->config->app->defaultController) . 'Controller';

                }

                /*
                 * This call to class_exists() will actually load the class with the __autoload magic method. Then
                 * we test if the class exists and if it doesn't we try a legacy load which includes the default controller . If that
                 * failes we return FALSE so a nice error can be sent instead of a nasty fatal error
                 */
                foreach($controller_class_search as $controller_class){

                    if(!class_exists($controller_class))
                        continue;

                    $newController = new $controller_class($controller, $this->application);

                    break;

                }

            }

        }
        catch(\Hazaar\Exception\ClassNotFound $e){

            return NULL;

        }

        return $newController;

	}

	/**
     * @detail Loads a class from a source file.
     * This is the main class loader used by the __autoload()PHP
     * trigger. It is responsible for loading the files that hold class source definitions by determining
     * the correct file to load based on the class name.
     *
     * First check if the class name is a single word that ends with 'Controller', designating it as a
     * controller class. If that matches then the class is loaded from the controller path.
     *
     * Otherwise we check if the class starts with Application and load from the application path.
     *
     * Lastly we do a 2 stage search of the library paths. Stage 1 looks for a correlating path while
     * stage
     * 2 looks for the class in a sub-directory of the module name.
     *
     * We do 2 stage class path checking.
     *
     * * _Stage 1:_ Look for the class in a correlating path. eg:[[Hazaar\Application]] in path
     * Hazaar/Application.php
     * * _Stage 2:_ If stage 1 fails, look in a module sub-directory. eg:[[Hazaar\Application]] in path
     * Hazaar/Application/Application.php
     *
     * If they both fail, the class is not found and we throw a pretty exception.
     *
     * @param string $class_name
     *        	The name of the class to load.
     *
     */
	static public function loadClassFromFile($class_name){

		if(preg_match('/^(\w*)Controller$/', $class_name, $matches)){

			$controllerClassFile = ucfirst($matches[1]) . '.php';

			if($filename = Loader::getFilePath(FILE_PATH_CONTROLLER, $controllerClassFile)){

				require_once($filename);

				return NULL;
			}

		} elseif(preg_match('/^(\w*)Service$/', $class_name, $matches)){

			$serviceClassFile = $matches[1] . '.php';

			if($filename = Loader::getFilePath(FILE_PATH_SERVICE, $serviceClassFile)){

				require_once($filename);

				return NULL;

			}

		} else {

			$namepath = preg_split('/(\W|_)/', $class_name, -1, PREG_SPLIT_NO_EMPTY);

			/*
             * Check that the prefix is 'Application'. This is sort of a namespace 'key' if you will
             * to restrict the loadable path to that of the application itself.
             */
			if($namepath[0] == 'Application'){

				$filename = implode(DIRECTORY_SEPARATOR, array_slice($namepath, 2)) . '.php';

				if($full_path = Loader::getFilePath(strtolower($namepath[1]), $filename, NULL, TRUE)){

					require_once($full_path);

					return NULL;

				}

			}

		}

	}

	/**
     * @detail Check the library paths to make sure the file exists somewhere
     *
     * @since 1.0.0
     */
	static private function getClassSource($path){

		foreach(explode(PATH_SEPARATOR, get_include_path())as $lib){

			$full_path = $lib . DIRECTORY_SEPARATOR . $path;

			if(file_exists($full_path))
				return $full_path;

		}

		return FALSE;

	}

}

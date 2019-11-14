<?php
/**
 * @file        Hazaar/Application/Config.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief       The main application entry point
 */
namespace Hazaar\Application;

/**
 * @brief       Application Configuration Class
 *
 * @detail      The config class loads settings from a configuration file and configures the system ready
 *              for the application to run.  By default the config file used is application.ini and is stored
 *              in the config folder of the application path.
 *
 * @since       1.0.0
 */
class Config extends \Hazaar\Map {

    static public $override_paths = array();

    private $env;

    private $source;

    private $loaded = false;

    /**
     * @detail      The application configuration constructor loads the settings from the configuration file specified
     *              in the first parameter.  It will use the second parameter as the starting point and is intended to
     *              allow different operating environments to be configured from a single configuration file.
     *
     * @since       1.0.0
     *
     * @param       string  $source_file    The absolute path to the config file
     *
     * @param       string  $env            The application environment to read settings for.  Usually `development`
     *                                      or `production`.
     *
     * @param       mixed   $defaults       Initial defaut values.
     *
     * @param       mixed   $path_type      The search path type to look for configuration files.
     *
     * @param       mixed   $override_paths An array of subdirectory names to look for overrides.
     */
    function __construct($source_file = null, $env = NULL, $defaults = array(), $path_type = FILE_PATH_CONFIG, $override_namespaces = false) {

        $config = null;

        if(! $env)
            $env = APPLICATION_ENV;

        $this->env = $env;

        if($this->source = trim($source_file)){

            if($config = $this->load($this->source, $defaults, $path_type, Config::$override_paths, $override_namespaces))
                $this->loaded = ($config->count() > 0);

        }

        $filters = array(
            'out' => array(
                array(
                    'field' => null,
                    'callback' => array($this, 'parseString')
                )
            )
        );

        parent::__construct($config, null, $filters);

    }

    public function load($source, $defaults = array(), $path_type = FILE_PATH_CONFIG, $override_paths = null, $override_namespaces = false) {

        $options = array();

        $sources = array(array('name' => $source, 'ns' => true));

        if($override_paths){

            if(!is_array($override_paths))
                $override_paths = array($override_paths);

            foreach($override_paths as $override)
                $sources[] = array('name' => $override . DIRECTORY_SEPARATOR . $source, 'ns' => $override_namespaces);


        }

        foreach($sources as &$source_info){

            $source_file = null;

            //If we have an extension, just use that file.
            if(strrpos($source_info['name'], '.') !== false){

                $source_file = \Hazaar\Loader::getFilePath($path_type, $source_info['name']);

            }else{ //Otherwise, search for files with supported extensions

                $extensions = array('json', 'ini'); //Ordered by preference

                foreach($extensions as $ext){

                    $filename = $source_info['name'] . '.' . $ext;

                    if($source_file = \Hazaar\Loader::getFilePath($path_type, $filename))
                        break;

                }

            }

            //If the file doesn't exist, then skip it.
            if(!$source_file) continue;

            $source_data = $this->loadSourceFile($source_file);

            $options[] = ($source_info['ns'] === true) ? $source_data : array($this->env => $this->loadSourceFile($source_file));

        }

        if(!count($options) > 0) return false;

        $combined = array();

        foreach($options as $o){

            if(ake($combined, 'final') === true)
                break;

            $combined = array_replace_recursive($combined, $o);

        }

        $config = new \Hazaar\Map($defaults);

        if(!$this->loadConfigOptions($combined, $config))
            return false;

        return $config;

    }

    private function loadSourceFile($source){

        $config = array();

        $cache_key = null;

        //Check if APCu is available for caching and load the config file from cache if it exists.
        if(in_array('apcu', get_loaded_extensions())){

            $cache_key = md5(gethostname() . ':' . $source);

            if(apcu_exists($cache_key)) {

                $cache_info = apcu_cache_info();

                $mtime = 0;

                foreach($cache_info['cache_list'] as $cache) {

                    if(array_key_exists('info', $cache) && $cache['info'] == $cache_key) {

                        $mtime = ake($cache, 'mtime');

                        break;

                    }

                }

                if($mtime > filemtime($source))
                    $source = apcu_fetch($cache_key);

            }

        }

        //If we have loaded this config file, continue on to the next
        if(!is_string($source))
            return $source;

        $file = new \Hazaar\File($source);

        $extention = $file->extension();

        if($extention == 'json'){

            if(!$config = $file->parseJSON(true))
                throw new \Hazaar\Exception('Failed to parse JSON config file: ' . $source);

        }elseif($extention == 'ini'){

            if(!$config = parse_ini_string($file->get_contents(), true, INI_SCANNER_TYPED))
                throw new \Hazaar\Exception('Failed to parse INI config file: ' . $source);

            foreach($config as &$array)
                $array = array_from_dot_notation($array);

        }else{

            throw new \Hazaar\Exception('Unknown file format: ' . $source);

        }

        //Store the config file in cache
        if($cache_key !== null) apcu_store($cache_key, $config);

        return $config;

    }

    private function loadConfigOptions($options, \Hazaar\Map $config, $env = null){

        if(!$env)
            $env = $this->env;

        if(!(\Hazaar\Map::is_array($options) && array_key_exists($env, $options)))
            return false;

        foreach($options[$env] as $key => $values) {

            if($key === 'include') {

                if(!\Hazaar\Map::is_array($values))
                    $values = array($values);

                foreach($values as $include_environment)
                    $this->loadConfigOptions($options, $config, $include_environment);

            } elseif($key === 'import') {

                if(!\Hazaar\Map::is_array($values))
                    $values = array($values);

                foreach($values as $import_file){

                    if(!($file = \Hazaar\Loader::getFilePath(FILE_PATH_CONFIG, $import_file)))
                        continue;

                    if($options = $this->loadSourceFile($file))
                        $config->extend($options);

                }

            } else {

                $config->set($key, $values, true);

            }

        }

        return $config;

    }


    /**
     * Check whether the config was loaded from the source file.
     */
    public function loaded() {

        return $this->loaded;

    }

    /**
     * @brief       Get the config environment that was loaded
     *
     * @since       2.0.0
     */
    public function getEnv() {

        return $this->env;

    }

    /**
     * @brief       Get the source file content from which the settings originated
     *
     * @since       1.0.0
     */
    public function getSource() {

        if(file_exists($this->source))
            return file_get_contents($this->source);

        return 'config file not found';

    }

    public function getSourceFilename() {

        return $this->source;

    }

    /**
     * @brief       Output the configuration in a human readable format.
     *
     * @detail      This method is useful for logging, debugging or for using in application administration interfaces
     *              to check the current running configuration.
     *
     *              ### Example Output
     *
     *              <pre>
     *              app.name = Example Application
     *              app.version = 0.0.1
     *              app.layout = application
     *              app.theme.name = test
     *              app.defaultController = Index
     *              app.compress = false
     *              app.debug = 1
     *              paths.model = models
     *              paths.view = views
     *              paths.controller = controllers
     *              php.date.timezone = Australia/ACT
     *              php.display_startup_errors = 1
     *              php.display_errors = 1
     *              module.require[] = pgsql
     *              </pre>
     *
     * @since       1.0.0
     *
     * @return      string Config as a multi-line string
     */
    public function __tostring() {

        return $this->tostring();

    }

    public function tostring() {

        $config = $this->todotnotation();

        $output = "[{$this->env}]\n";

        foreach($config as $key => $value) {

            $output .= "{$key}={$value}\n";

        }

        return $output;

    }

    public function write($target = null) {

        if(!$target)
            $target = $this->source;

        if(!$target)
            return FALSE;

        $info = pathinfo($target);

        $type = ake($info, 'extension', 'json');

        $options = new \Hazaar\Map();

        //Grab the original file so we can merge into it
        if($this->source && file_exists($this->source)){

            $info = pathinfo($this->source);

            if($info['extension'] == 'json')
                $options->fromJSON(file_get_contents($this->source));

            elseif($info['extension'] == 'ini')
                $options->fromDotNotation(parse_ini_file($this->source, TRUE, INI_SCANNER_RAW));

        }

        $options->set($this->env, $this->toArray());

        $output = '';

        if($type == 'ini'){

            foreach($options as $env => $option) {

                $output .= "[$env]" . LINE_BREAK;

                $output .= $option->todotnotation()
                                  ->flatten(' = ', LINE_BREAK) . LINE_BREAK;

            }

        }else{

            $output = json_encode($this->toArray());

        }

        $result = file_put_contents($target, $output);

        if($result === FALSE)
            return FALSE;

        return TRUE;

    }

    public function parseString($elem, $key){

        $allowed_values = array(
            'GLOBALS' => &$GLOBALS,
            '_SERVER' => &$_SERVER,
            '_GET' => &$_GET,
            '_POST' => &$_POST,
            '_FILES' => &$_FILES,
            '_COOKIE' => &$_COOKIE,
            '_SESSION' => &$_SESSION,
            '_REQUEST' => &$_REQUEST,
            '_ENV' => &$_ENV,
            '_APP' => &\Hazaar\Application::getInstance()->GLOBALS,
        );

        if(is_string($elem) && preg_match_all('/%([\w\[\]]*)%/', $elem, $matches)){

            foreach($matches[0] as $index => $match){

                if(strpos($matches[1][$index], '[') !== false){

                    parse_str($matches[1][$index], $result);

                    $parts = explode('.', key(array_to_dot_notation($result)));

                    if(!array_key_exists($parts[0], $allowed_values))
                        return '';

                    $value =& $allowed_values;

                    foreach($parts as $part){

                        if(!($value && array_key_exists($part, $value)))
                            return '';

                        $value =& $value[$part];

                    }

                }else{

                    $value = defined($matches[1][$index]) ? constant($matches[1][$index]) : '';

                }

                $elem = preg_replace('/' . preg_quote($match) . '/', $value, $elem, 1);

            }

        }

        return $elem;

    }

}
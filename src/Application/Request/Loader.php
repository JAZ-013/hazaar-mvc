<?php
/**
 * @file        Hazaar/Application/Request/Loader.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar\Application\Request;

/**
 * Application Request Loader
 *
 * This class determines from where the application execution was initiated.  Normally this will result in a
 * [[Hazaar\Application\Request\Http]] object being returned as Hazaar applications are most often used as web-based
 * applications.  However it is possible to build and execute command line Hazaar applications.  When executing an
 * application of this type this method will return a [[Hazaar\Application\Request\Cli]] object.
 *
 * @since 1.0.0
 */
class Loader {

    /**
     * Loads the appropriate request object for the current application execution.
     *
     * @since 1.0.0
     *
     * @return \Hazaar\Application\Request Either a [[Hazaar\Application\Request\Http]] or
     * [[Hazaar\Application\Request\Cli]]
     * object.
     */
    static public function load() {

        switch(strtolower(php_sapi_name())) {

            case 'cli' :

                global $argv;

                $request = new Cli($argv);

                break;

            default :

                $request = new Http($_REQUEST, true);

                break;
        }

        return $request;

    }

}

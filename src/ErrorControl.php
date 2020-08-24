<?php
/**
 * @file        Hazaar/ErrorControl.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

set_error_handler('error_handler', E_ERROR);

set_exception_handler('exception_handler');

register_shutdown_function('shutdown_handler');

/**
 * @brief       Error and die
 *
 * @since       1.0.0
 */
function errorAndDie() {

    $app = \Hazaar\Application::getInstance();

    $headers = array_unflatten(headers_list(), ':', "\n");

    $args = func_get_args();

    if(ake($headers, 'X-Response-Type') == 'stream'){

        $stream = new \Hazaar\Controller\Response\Stream($args[0]);

        $stream->__writeOutput();

    }elseif($app instanceof Hazaar\Application && $app->config) {

        $controller = null;

        if($error_controller = $app->config->app->get('errorController')) {

            $loader = \Hazaar\Loader::getInstance();

            $controller = $loader->loadController($error_controller);

        }

        if(!$controller instanceof \Hazaar\Controller\Error)
            $controller = new \Hazaar\Controller\Error('Error', $app);

        $controller->__initialize($app->request);

        call_user_func_array(array($controller, 'setError'), $args);

        $controller->clean_output_buffer();

        $app->run($controller);

    } else {

        $error = array(10500, 'An unknown error occurred!', __FILE__, __LINE__, null, array());

        if(count($args) > 0){

            if($args[0] instanceof \Exception
                || $args[0] instanceof \Error){

                $error = array(
                    $args[0]->getCode(),
                    $args[0]->getMessage(),
                    $args[0]->getFile(),
                    $args[0]->getLine(),
                    null,
                    $args[0]->getTrace()
                );

            }elseif(isset($arg[0]) && is_array($arg[0]) && array_key_exists('type', $arg[0])){

                $error = array(
                    $arg[0]['type'],
                    $arg[0]['message'],
                    $arg[0]['file'],
                    $arg[0]['line'],
                    null,
                    (isset($arg[1]) ? $arg[1] : null)
                );

            }else{

                $error = $args;

            }

            if(php_sapi_name() === 'cli'){

                $die = "##############################\n# Hazaar MVC - Console Error #\n##############################\n\n";
                
                $die .= "$error[1]\n\n";

                if(!is_array($error[5]))
                    $error[5] = array();

                $error[5][] = array('file' => $error[2], 'line' => $error[3], 'class' => '', 'function' => '');

                $die .= "Call stack:\n\n";

                for($x = count($error[5]) - 1; $x >= 0; $x--)
                    $die .= count($error[5]) - $x . ". {$error[5][$x]['class']}->{$error[5][$x]['function']} {$error[5][$x]['file']}:{$error[5][$x]['line']}\n";

                die($die);

            }

        }

        include(realpath(__DIR__
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'libs'
            . DIRECTORY_SEPARATOR . 'error'
            . DIRECTORY_SEPARATOR . 'fatal.php'));

    }

    exit;

}

function error_handler($errno, $errstr, $errfile = NULL, $errline = NULL, $errcontext = NULL) {

    \Hazaar\Logger\Frontend::e('CORE', implode(' | ', array('Error #' . $errno, $errfile, 'Line #' . $errline, $errstr)));

    errorAndDie($errno, $errstr, $errfile, $errline, $errcontext, debug_backtrace());

}

function exception_handler($e) {

    \Hazaar\Logger\Frontend::e('CORE', implode(' | ', array('Error #' . $e->getCode(), $e->getFile(), 'Line #' . $e->getLine(), $e->getMessage())));

    errorAndDie($e);

}

function shutdown_handler() {

    if(headers_sent())
        return;

    if($error = error_get_last()){

        $ignored_errors = array(
            E_CORE_WARNING,
            E_COMPILE_WARNING,
            E_USER_WARNING,
            E_RECOVERABLE_ERROR
        );

        if(is_array($error) && !in_array($error['type'], $ignored_errors))
            errorAndDie($error, debug_backtrace());

    }

}

function basic_handler($errno, $errstr, $errfile = NULL, $errline = NULL, $errcontext = NULL) {

    echo "PHP Error #$errno: $errstr in file $errfile on line $errline";

    debug_print_backtrace();

    die();

}

if(function_exists('apache_get_modules')) {

    if(! in_array('mod_rewrite', apache_get_modules())) {

        throw new \Hazaar\Exception('mod_rewrite MUST be enabled to use Hazaar!');

    }

}

function traceAndDie(){

	$trace = debug_backtrace();

    include(realpath(__DIR__
            . DIRECTORY_SEPARATOR . '..'
            . DIRECTORY_SEPARATOR . 'libs'
            . DIRECTORY_SEPARATOR . 'error'
            . DIRECTORY_SEPARATOR . 'trace.php'));

	exit;

}

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

    if(ake($headers, 'X-Response-Type') == 'stream'){

        $stream = new \Hazaar\Controller\Response\Stream(func_get_arg(0));

        $stream->__writeOutput();

    }elseif($app instanceof Hazaar\Application && $app->config) {

        if($app->config->app->has('errorController')) {

            $loader = \Hazaar\Loader::getInstance();

            try {

                $controller = $loader->loadController($app->config->app['errorController']);

                if(! $controller instanceof \Hazaar\Controller\Error)
                    throw new Exception('Error controller does not extent Hazaar\Controller\Error');

            }
            catch(Exception $e) {

                $controller = new \Hazaar\Controller\Error('Error', $app, !defined('APPLICATION_CONSOLE'));

            }

        } else {

            $controller = new \Hazaar\Controller\Error('Error', $app);

        }

        $controller->__initialize($app->request);

        call_user_func_array(array($controller, 'setError'), func_get_args());

        $controller->clean_output_buffer();

        $app->run($controller);

    } else {

        $arg = func_get_args();

        $error = array(10500, 'An unknown error occurred!', __FILE__, __LINE__, null, array());

        if(count($arg) > 0){

            if($arg[0] instanceof \Exception
                || $arg[0] instanceof \Error){

                $error = array(
                    $arg[0]->getCode(),
                    $arg[0]->getMessage(),
                    $arg[0]->getFile(),
                    $arg[0]->getLine(),
                    null,
                    $arg[0]->getTrace()
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

                $error = $arg;

            }

        } ?>
<html>
<head>

    <title>Hazaar MVC - Fatal Error</title>

    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            padding: 0;
            margin: 0;
            color: #333;
        }

        .container {
            max-width: 1170px;
            margin: auto;
        }

        .topbar {
            background-color: #554D7C;
            color: #fff;
            padding: 15px;
        }

        h1 {
            margin-bottom: 0;
        }

        h3 {
            margin-top: 0;
            font-weight: normal;
            font-style: italic;
        }

        table {
            font-size: 18px;
            width: 100%;
            border-collapse: collapse;
        }

            table th {
                color: #554D7C;
            }

            table td, table th {
                padding: 15px 15px 15px 0;
            }

            table.trace {
                background: #eee;
            }

                table.trace th {
                    border-bottom: 1px solid #554D7C;
                }

                table.trace th, table.trace td {
                    padding: 15px;
                }

                table.trace tbody tr:nth-child(2) {
                }

        th {
            text-align: left;
            vertical-align: top;
            width: 50px;
        }
    </style>
</head>
<body>

    <div class="topbar">

        <div class="container">

            <h1>FATAL ERROR</h1>

            <h3>An error occurred without an application context...</h3>

        </div>

    </div>


    <div class="container">

        <h2>
            <?=$error[1]?>
        </h2>

        <table>
            <tr>
                <th>File:</th>
                <td>
                    <?=$error[2];?>
                </td>
            </tr>
            <tr>
                <th>Line:</th>
                <td>
                    <?=$error[3];?>
                </td>
            </tr>
            <tr>
                <th>Trace:</th>
                <td>
                    <table class="trace">

                        <thead>

                            <tr>
                                <th>Step</th>
                                <th>File</th>
                                <th>Line</th>
                                <th>Function</th>
                            </tr>

                        </thead>

                        <tbody>

                            <?php foreach($error[5] as $id => $trace) { ?>

                            <tr>
                                <td>
                                    <?=$id;?>
                                </td>
                                <td>
                                    <?=ake($trace, 'file');?>
                                </td>
                                <td>
                                    <?=ake($trace, 'line');?>
                                </td>
                                <td>
                                    <?php
                                      echo (isset($trace['class']) ? $trace['class'] . '::' : NULL) . ake($trace, 'function') . '(';
                                      if(isset($trace['args']) && is_array($trace['args'])){
                                          array_walk($trace['args'], function(&$item){
                                              if(is_array($item))
                                                  $item = 'Array(' . count($item) . ')';
                                              elseif( is_string($item))
                                                  $item = "'$item'";
                                          });
                                          echo implode(', ', $trace['args']);
                                      }
                                      echo ')';?>
                                </td>
                            </tr>

                            <?php } ?>

                        </tbody>
                    </table>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
<?php

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

?>
<html>
<head>
    <style>
        table {
            border-collapse: collapse;
            margin: auto;
        }

            table th {
                text-align: left;
                border-bottom: 1px solid #ddd;
                background: #eee;
            }

            table th, table td {
                padding: 5px;
            }
    </style>
</head>
<table>
    <tr>
        <th>File</th>
        <th>Line</th>
        <th>Function</th>
        <th>Args</th>
    </tr>

    <?php

	foreach($trace as $t){

		echo "<tr><td>" . ake($t, 'file', 'unknown') . "</td><td>" . ake($t, 'line') . "</td><td>" . $t['function'] . "</td>";

		if($args = ake($t, 'args')){

			$arglist = array();

			foreach($args as $a){

				if(is_object($a)){

					$arglist[] = "Object(" . get_class($a) . ")";

				}else{

					$arglist[] = gettype($a) . "($a)";

				}

			}

			echo "<td>" . implode(", ", $arglist) . "</td>";

		}

		echo "</tr>";

	}

    ?>
</table>

</html>

<?php

	exit;

}

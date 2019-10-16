<?php
/**
 * @file        Hazaar/Closure.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * @brief       Closure Class
 *
 * @since       1.0.0
 */
class Closure {

    protected $closure;

    protected $reflection;

    function __construct($function = NULL) {

        if($function instanceof \Closure) {

            $this->closure = $function;

            $this->reflection = new \ReflectionFunction($function);

            $this->code = $this->_fetchCode();

        }

    }

    public function __invoke() {

        $args = func_get_args();

        return $this->reflection->invokeArgs($args);

    }

    public function getClosure() {

        return $this->closure;

    }

    protected function _fetchCode() {

        $file = new \SplFileObject($this->reflection->getFileName());

        $file->seek($this->reflection->getStartLine() - 1);

        $code = '';

        while($file->key() < $this->reflection->getEndLine()) {

            $code .= $file->current();

            $file->next();

        }

        // Only keep the code defining that closure
        $begin = strpos($code, 'function');

        $end = strrpos($code, '}');

        return substr($code, $begin, $end - $begin + 1);

    }

    public function getCode() {

        return $this->code;

    }

    public function loadCodeFromString($string) {

        $this->code = $string;

        $this->__wakeup();

    }

    public function __toString() {

        return $this->getCode();

    }

    public function getParameters() {

        return $this->reflection->getParameters();

    }

    public function __sleep() {

        return array('code');

    }

    public function __wakeup() {

        eval('$_function = ' . $this->code . ';');

        if(isset($_function) && $_function instanceof \Closure) {

            $this->closure = $_function;

            $this->reflection = new \ReflectionFunction($_function);

        } else {

            throw new \Hazaar\Exception('Bad code: ' . $this->code);

        }

    }

}

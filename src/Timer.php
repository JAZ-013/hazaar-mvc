<?php

/**
 * @file        Hazaar/Timer.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * Timer class for measuring how long things take
 *
 * The timer class can be used to time one or more events and returns how long it took in milliseconds.
 *
 * ## Application Timer
 * 
 * The Hazaar\Application class has a built-in timer for measuring the performance of your application automatically.  By default
 * this timer is disabled.  See the `app.timer` setting in [Config Directives](http://scroly.io/hazaarmvc/latest/reference/configs.md).
 *
 * @since       1.0.0
 */
class Timer {

    /**
     * Array of timers
     */
    private $timers = array();

    /**
     * The name of the last timer.  Used in checkpointing.
     * 
     * @var mixed
     */
    private $last;

    /**
     * Timer Precision
     *
     * This sets the precision of the output.  By default it is 2 which means output is 1/10th of a millisecond.
     */
    private $precision;

    /**
     * Timer class constructor
     *
     * The timer class has an implicit timer that is always active called the 'global' timer.  This is simply
     * used to record how long the timer class itself has been active.
     *
     * @param mixed $precision The precision to use when returning timer values. Defaults to 2.
     */
    function __construct($precision = 2) {

        $this->precision = $precision;

        $this->start('global', HAZAAR_EXEC_START);

    }

    /**
     * Start a new timer.
     *
     * A timer is nothing more than a named point in time.  When a timer starts, no long running code is executed
     * or anything like that and simply the current system time in microseconds is recorded against the timer name.
     * This allows us to later query that timer name and return the difference which will give you the number
     * of milliseconds between two points in time.
     *
     * @param mixed $name A name for the timer.
     * @param mixed $when Optionally allow the start time to be overriden.  Defaults to PHP's microtime(true) value.
     */
    public function start($name = 'default', $when = null) {

        if(!$when)
            $when = microtime(true);

        $this->timers[$name] = array('start' => $when);

        $this->last = $name;

    }

    /**
     * Stop a currently running timer and return it's value
     *
     * @param mixed $name The name of the timer to stop.  If the timer does not exist an exception is thrown.
     *
     * @throws Exception
     *
     * @return float The difference, in milliseconds, between when the timer was started and when it was stopped.
     */
    public function stop($name = 'default') {

        if(!array_key_exists($name, $this->timers))
            throw new Exception("Error trying to stop non-existent timer '$name'.");

        if($name == 'global')
            throw new Exception('You can not stop the global timer!');

        if(!array_key_exists('stop', $this->timers[$name]))
            $this->timers[$name]['stop'] = microtime(true);

        return $this->get($name);

    }

    /**
     * Create a timer checkpoint.
     * 
     * Simply put, this will automatically stop the last timer and start a new one in one function call.
     * 
     * @param mixed $name The name of the new timer.
     * 
     * @return boolean
     */
    public function checkpoint($name){

        if(!$this->last)
            return false;

        $this->stop($this->last);

        return $this->start($name);
        
    }

    /**
     * Magic toString method
     *
     * This will return a string representation of the current state of the default timer object.
     *
     * @return string
     */
    public function __tostring() {

        return (string)$this->get();

    }

    /**
     * Get the current state of a timer.
     *
     * If a timer is currently running, then it's value will be the difference between when it started
     * and 'now'.  If a timer has stopped, it's value will be the difference between when it was started and when
     * it stopped.
     *
     * @param mixed $name The name of the timer to stop.  If the timer does not exist an exception is thrown.
     *
     * @param mixed $precision The precision of the returned value.  If not specified the precision used in the constructor is used.
     *
     * @throws Exception
     *
     * @return float
     */
    public function get($name = 'default', $precision = null) {

        if(!array_key_exists($name, $this->timers))
            throw new Exception("Unable to return current value of non-existent timer '$name'.");

        $timer = $this->timers[$name];

        $start = $timer['start'];

        $stop = (array_key_exists('stop', $timer) ? $timer['stop'] : microtime(true));

        return round(($stop - $start) * 1000, ($precision !== null) ? $precision : $this->precision);

    }

    /**
     * Get an array of all timers and their current state
     *
     * @param mixed $precision The precision of the returned values.  If not specified the precision used in the constructor is used.
     *
     * @return float[]
     */
    public function all($precision = null) {

        $results = array();

        foreach(array_keys($this->timers) as $name)
            $results[$name] = $this->get($name, $precision);

        return $results;

    }

}

<?php

/**
 * @file        Hazaar/Date.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar;

if (!ini_get('date.timezone'))
    ini_set('date.timezone', 'UTC');

/**
 * @brief The date/time class
 *
 * @detail This class can be used to manage date and time values and perform date arithmetic.
 *
 * ### Example
 *
 * <code class="php">
 * $date = new Hazaar\Date('next tuesday');
 * echo $date; //Echo's a timestamp such as '2013-01-15 11:00:00.0'
 * </code>
 *
 * ## Timezones
 *
 * It is possible to set the timezone globally for all date/time functions and classes.
 *
 * For example, to set your timezone to 'Australia/ACT', in your application.ini file add the line:
 *
 * pre. php.date.timezone = Australia/ACT
 *
 * See the PHP Manual for a "list of valid timezones":http://php.net/manual/en/timezones.php.
 *
 * If a timezone is not set in the application.ini file, nor is one set in the global PHP configuration then
 * as a last ditch effort the Date class will default to UTC. This is because not having an ini setting in
 * date.timzone will cause a PHP runtime error.
 */
class Date extends \Datetime {

    public static $calendar = CAL_JULIAN;

    public $usec;

    /**
     * Global format for date part
     */
    public static $date_format = 'Y-m-d';

    /**
     * Global format for time part
     */
    public static $time_format = 'H:i:s';

    public static $default_format = 'l, F jS Y \a\t g:ia';

    private $instance_format;

    /**
     * @detail The Date class constructor takes two values.
     * A datetime declaration and a timezone. Both are
     * optional. If now datetime is specified then the current datetime is used. If no timezone is
     * specified then the default timezone will be used.
     *
     * @param mixed $datetime
     *            The datetime value you want to work with. This can be either an integer representing
     *            the datetime Epoch value (seconds since 1970-01-01) or a string datetime description supported by the
     *            PHP strtotime() function. This means that textual datetime descriptions such as 'now' and 'next
     *            monday' will work. See the PHP documentation on
     *            [[http://au1.php.net/manual/en/function.strtotime.php|strtotime()]] for more information on valid
     *            formats. If the datetime value is not set, the current date and time will be used.
     *
     * @param string $timezone
     *            The timezone for this datetime value. If no timezone is specified then the default
     *            timezone is used.
     */
    public function __construct($datetime = NULL, $timezone = NULL) {

        if(is_object($datetime))
            $datetime = (array)$datetime;

        if (is_null($datetime)) {

            $datetime = '@' . microtime(TRUE);

        } elseif (is_numeric($datetime)) {

            $datetime = '@' . $datetime . '.0';

        } elseif (is_array($datetime)){
            
            if(array_key_exists('sec', $datetime)) { // Common array date object

                $ndatetime = '@' . $datetime['sec'];

                if (array_key_exists('usec', $datetime))
                    $ndatetime .= '.' . $datetime['usec'];

                $datetime = $ndatetime;

            } elseif (array_key_exists('usec', $datetime) && array_key_exists('date', $datetime)) { // Array version of Hazaar\Date

                if (!$timezone && array_key_exists('timezone', $datetime))
                    $timezone = $datetime['timezone'];

                $datetime = '@' . strtotime($datetime['date']) . '.' . $datetime['usec'];
            }else{
                
                $datetime = null;

            }

        } elseif ($datetime instanceof Date) {

            $datetime = '@' . $datetime->getTimestamp();

        } elseif (is_object($datetime)) {

            $datetime = (string) $datetime;

            if (is_numeric($datetime))
                $datetime = '@' . $datetime;

        }

        if (preg_match('/@(\d+)\.(\d+)/', $datetime, $matches)) {

            $this->usec = $matches[2];

            $datetime = '@' . $matches[1];

        } else {

            $this->usec = 0;

            $year = 2000;

            $month = 5;

            $day = 4;

            $time = date_parse(strftime('%x', mktime(0, 0, 0, $month, $day, $year)));

            if ($time['month'] !== $month && preg_match('/\d+\/\d+\/\d+/', $datetime))
                $datetime = str_replace('/', '-', $datetime);

        }

        if (substr($datetime, 0, 1) == '@') {

            if (!$timezone)
                $timezone = new \DateTimeZone(date_default_timezone_get());

            parent::__construct($datetime);

            $this->setTimezone($timezone);

        } else {

            if (!$timezone)
                $timezone = date_default_timezone_get();
            elseif (is_numeric($timezone))
                $timezone = timezone_identifiers_list()[(int) $timezone];

            if (!$timezone instanceof \DateTimeZone) {

                if ($timezone == '+00:00')
                    $timezone = 'UTC';

                $timezone = new \DateTimeZone($timezone);

            }

            parent::__construct($datetime, $timezone);

        }

    }

    public function setFormat($format) {

        $this->instance_format = $format;

    }

    /**
     * Set the timezone for the date object
     *
     * Setting this will cause the date and time to be output using the specified timezone. If a timezone is not given
     * then the default system timezone will be used. This is a good way to convert a date of another timezone into the
     * current default system timezone.
     *
     * @param Mixed $timezone
     *            The timezone to set. This can be either a [[DateTimeZone]] object, a string
     *            representation of a timezone, an offset in the format hh:mm, or a numeric value
     *            of the timezone returned by timezone_identifiers_list(). If it is left null, then
     *            the default system timezone is used.
     *
     * @return boolean Returns the result of the parent [[DateTime::setTimezone]] call.
     */
    public function setTimezone($timezone = NULL) {

        if ($timezone === NULL)
            $timezone = date_default_timezone_get();

        if (is_numeric($timezone))
            $timezone = timezone_identifiers_list()[(int) $timezone];

        if (!$timezone instanceof \Datetimezone) {

            if (is_numeric($timezone)) {

                $timezone = timezone_name_from_abbr('', $timezone, FALSE);
            } elseif (preg_match('/([+-])?(\d+):(\d+)/', $timezone, $matches)) {

                if (!$matches[1])
                    $matches[1] = '+';

                $timezone = timezone_name_from_abbr('', ((int) ($matches[1] . (($matches[2] * 3600) + $matches[3]))), FALSE);
            }

            $timezone = new \Datetimezone($timezone);
        }

        return parent::setTimezone($timezone);

    }

    /**
     * Return the date time value using a format string for a specified timezone
     *
     * @param string $format
     *            A standard date format string
     *
     * @param string $timezone
     *            The timezone to use to convert the date time value.
     *
     * @return string The date time string in the target timezone and format.
     */
    public function formatTZ($format, $timezone = 'UTC') {

        $timezoneBackup = date_default_timezone_get();

        date_default_timezone_set($timezone);

        $date = date($format, $this->getTimestamp());

        date_default_timezone_set($timezoneBackup);

        return $date;

    }

    /**
     * Magic method to output the datetime value as a string.
     * Uses the Date::timestamp() method.
     *
     * @return string String representation of the datetime value.
     */
    public function __tostring() {

        return $this->format(($this->instance_format ? $this->instance_format : Date::$default_format));

    }

    /**
     * Get the current datetime value as an SQL compliant formatted string.
     *
     * @return string The SQL compliant datetime string.
     */
    public function getSQLDate() {

        return $this->format('Y-m-d G:i:s') . '.' . $this->usec;

    }

    /**
     * Returns the current datetime value as epoch (seconds passed since 1970-01-01).
     *
     * @return int Epoch value of datetime.
     */
    public function sec() {

        return (int) parent::getTimestamp();

    }

    /**
     * Return the microsecond part of the datetime value.
     *
     * This will more than likely always be 0 (zero) unless specified in the constructor.
     *
     * @return int Microsecond part of datetime value.
     */
    public function usec() {

        return $this->usec;

    }

    /**
     * Return the date part formatted by the global date format.
     *
     * @return string The date part of the datetime value
     */
    public function date() {

        $format = Date::$date_format;

        return parent::format($format);

    }

    /**
     * Return the time part formatted by the global time format.
     *
     * @return string The time part of the datetime value
     */
    public function time() {

        $format = Date::$time_format;

        return parent::format($format);

    }

    /**
     * Return the date and time.
     *
     * This will use the global date and time formats and concatenate them together, separated by a space.
     *
     * @return string The date and time of the datetime value
     */
    public function datetime() {

        $format = Date::$date_format . ' ' . Date::$time_format;

        return parent::format($format);

    }

    /**
     * Get the timestamp formatted as a string.
     *
     * This will use the global date and time formats and concatenate them together, separated by a space.
     *
     * @return string The timestamp as string
     */
    public function timestamp() {

        return parent::format('c');

    }

    /**
     * Returns the age in years of the date with optional precision
     *
     * @param integer $precision
     *            The number of digits to round an age to. Default: 0
     *
     * @return integer The number of years passed since the date value.
     */
    public function age($precision = 0) {

        return round(years(time() - $this->sec()), $precision);

    }

    /**
     * Returns the difference between the current date/time value and the value supplied as the first argument.
     *
     * Normally this method will return a \DateInterval object which is the default for the \DateTime class. However
     * this functionality has been extended with the $return_seconds parameter which will instead return an integer
     * value indicating the difference in whole seconds.
     *
     * @param \DateTime $timestamp
     *            The timestamp to compare the current date/time to.
     *
     * @param bool $return_seconds
     *            If TRUE then the return value will be an integer indicating the difference in whole seconds.
     *
     * @return \DateInterval|int
     */
    public function diff($timestamp, $return_seconds = FALSE) {

        if ($return_seconds) {

            $diff = parent::diff($timestamp);

            $seconds = 0;

            if ($diff->days > 0)
                $seconds += $diff->days * 86400;

            if ($diff->h > 0)
                $seconds += $diff->h * 3600;

            if ($diff->i > 0)
                $seconds += $diff->i * 60;

            if ($diff->s > 0)
                $seconds += $diff->s;

            if ($diff->invert == 0)
                $seconds = -($seconds);

            return $seconds;
        }

        return parent::diff($timestamp);

    }

    /**
     * Returns the objects current year
     */
    public function year() {

        return (int) $this->format('Y');

    }

    /**
     * Returns the objects current month
     */
    public function month() {

        return (int) $this->format('m');

    }

    /**
     * Returns the objects current day
     */
    public function day() {

        return (int) $this->format('d');

    }

    /**
     * Returns the objects current hour(
     */
    public function hour() {

        return (int) $this->format('H');

    }

    /**
     * Returns the objects current minute
     */
    public function minute() {

        return (int) $this->format('i');

    }

    /**
     * Returns the objects current second
     */
    public function second() {

        return (int) $this->format('s');

    }

    /**
     * Add a date/time interval to the current date/time.
     *
     * See the PHP documentation on how to use the "DateInterval":http://au2.php.net/manual/en/class.dateinterval.php object.
     *
     * @param mixed $interval
     *            Can be either a \DateInterval object or a string representing an interval, such as P1H to specify 1 hour.
     *
     * @param bool $return_new
     *            Doesn't update the current \Hazaar\Date object and instead returns a new object with the interval applied.
     *
     * @return Date
     */
    public function add($interval, $return_new = FALSE) {

        if (!$interval instanceof \DateInterval)
            $interval = new \DateInterval($interval);

        if ($return_new) {

            $new = new Date($this, $this->getTimezone());

            return $new->add($interval);
        }

        if (parent::add($interval))
            return $this;

        return FALSE;

    }

    /**
     * Subtract a date/time interval from the current date/time.
     *
     * See the PHP documentation on how to use the "DateInterval":http://au2.php.net/manual/en/class.dateinterval.php object.
     *
     * @param mixed $interval
     *            Can be either a \DateInterval object or a string representing an interval, such as P1H to specify 1 hour.
     *
     * @param bool $return_new
     *            Doesn't update the current \Hazaar\Date object and instead returns a new object with the interval applied.
     *
     * @return Date
     */
    public function sub($interval, $return_new = FALSE) {

        if (!$interval instanceof \DateInterval)
            $interval = new \DateInterval($interval);

        if ($return_new) {

            $new = new Date($this, $this->getTimezone());

            return $new->sub($interval);
        }

        if (parent::sub($interval))
            return $this;

        return FALSE;

    }

    /**
     * Compare the current date with the argument
     */
    public function compare($date, $include_time = FALSE) {

        $format = 'Y-m-d' . ($include_time ? ' H:i:s' : NULL);

        if (!$date instanceof Date)
            $date = new Date($date, $this->getTimezone());

        return ($this->format($format) == $date->format($format));

    }

    /**
     * Returns the number of days in the current month.
     *
     * @return int
     */
    public function daysInMonth() {

        return cal_days_in_month(Date::$calendar, $this->month(), $this->year());

    }

    /**
     * Returns the start time of the current date as a Date object.
     *
     * @return Date
     */
    public function start() {

        return new Date($this->format('Y-m-d 00:00:00'), $this->getTimezone());

    }

    /**
     * Returns the end time of the current date as a Date object.
     *
     * @return Date
     */
    public function end() {

        return new Date($this->format('Y-m-d 23:59:59'), $this->getTimezone());

    }

    /**
     * Returns the first day of the current week as a Date object.
     *
     * @return Date
     */
    public function firstOfWeek() {

        return $this->sub('P' . ($this->format('N') - 1) . 'D', TRUE)->setTime(0, 0, 0);

    }

    /**
     * Returns the last day of the current week as a Date object.
     *
     * @return Date
     */
    public function lastOfWeek() {

        return $this->add('P' . (7 - $this->format('N')) . 'D', TRUE)->setTime(23, 59, 59);

    }

    /**
     * Returns the first day of the current month as a Date object.
     *
     * @return Date
     */
    public function firstOfMonth() {

        return new Date($this->format('Y-m-01 00:00:00'), $this->getTimezone());

    }

    /**
     * Returns the last day of the current month as a Date object.
     *
     * @return Date
     */
    public function lastOfMonth() {

        return new Date($this->format('Y-m-' . $this->daysInMonth() . ' 23:59:59'), $this->getTimezone());

    }

    /**
     * Returns the first day of the current year as a Date object.
     *
     * @return Date
     */
    public function firstOfYear() {

        return new Date($this->format('Y-01-01 00:00:00'), $this->getTimezone());

    }

    /**
     * Returns the last day of the current year as a Date object.
     *
     * @return Date
     */
    public function lastOfYear() {

        return new Date($this->format('Y-12-31 23:59:59'), $this->getTimezone());

    }

    public function __export() {

        return $this->sec();

    }

    /**
     * Return a fuzzy diff between the current time and the Date value.
     *
     * @param bool $precise
     *
     * @return string
     */
    public function fuzzy($precise = FALSE) {

        $diff = $this->diff(new Date());

        if ($diff->days == 0) {

            if ($diff->h > 0) {

                $msg = $diff->h . ' hour' . (($diff->h > 1) ? 's' : NULL);
            } elseif ($diff->i > 0) {

                $msg = $diff->i . ' minute' . (($diff->i > 1) ? 's' : NULL);
            } elseif ($precise == FALSE && $diff->s < 30) {

                $msg = 'A few seconds';
            } else {

                $msg = $diff->s . ' seconds';
            }

            $msg .= ' ago';
        } elseif ($diff->days == 1) {

            $msg = 'Yesterday at ' . $this->format('g:ia');
        } else {

            $msg = $this->format('j F \a\t g:ia');
        }

        return $msg;

    }

    /**
     * Get locale data for a specified locale
     *
     * Data included is what is returned by PHP's localeconv() function.
     *
     * @param mixed $locale The locale to get data for.
     *
     * @return \array|boolean If the locale is invalid then FALSE is returned.  Otherwise the result of
     * localeconv() for the specified locale.
     */
    static public function getLocaleData($locale){

        $local_locale = setlocale(LC_ALL, 0);

        if(!setlocale(LC_ALL, $locale))
            return false;

        $data = localeconv();

        setlocale(LC_ALL, $local_locale);

        return $data;

    }

    /**
     * Retrieve a date format for a specific locale
     *
     * This will return something like DMY to indicate that the locale format is date, month followed by year.
     *
     * These formats are not meant to be used directly in date functions as different date functions use different
     * format specifiers and we won't even attempt to support all of them here.
     *
     * @param mixed $locale
     * @return \boolean|null|string
     */
    static public function getLocaleDateFormat($locale){

        $local_locale = setlocale(LC_ALL, 0);

        if(!setlocale(LC_ALL, $locale))
            return false;

        $format = null;

        if(preg_match('/(\d+)(\W)(\d+)(\W)(\d+)/', strftime('%c', mktime(0,0,0,12,1,2000)), $matches)){

            $matrix = array(1 => 'D', 12 => 'M', 2000 => 'Y');

            $format = $matrix[intval($matches[1])] . $matrix[intval($matches[3])] . $matrix[intval($matches[5])];

        }

        setlocale(LC_ALL, $local_locale);

        return $format;

    }

}

<?php
/**
 * @file        Hazaar/Cron.php
 *
 * @author      Christian Land http://tagdocs.de
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 */

namespace Hazaar;

define('IDX_MINUTE', 0);
define('IDX_HOUR', 1);
define('IDX_DAY', 2);
define('IDX_MONTH', 3);
define('IDX_WEEKDAY', 4);
define('IDX_YEAR', 5);

/**
 * This class can be used to parse cron strings and compute schedules.
 *
 * It can parse a given string with a schedule specification in cron format.  The class can compute the last and the
 * next schedule times relative to a given time.
 *
 * ### Example
 *
 * <code>
 * $cron = new \Hazaar\Cron('0,30 9-17 \* \* 1-5');
 * $next = $cron->getNextOccurrence();
 * </code>
 *
 * This will get the next occurrence from the schedule which should return dates and times for every 0th and 30th minute
 * between 9am and 5pm, Monday to Friday.
 *
 * @note    This code was originally added to Hazaar MVC in August of 2016 from code found randomly on the internet.  Probably
 *          StackOverflow?  It was never intended to be incorporated into the framework, but became a depdendency for the
 *          Hazaar Warlock library.  By this point and at that time I was unable to remember, or find, the original author
 *          and where I came across this code.
 *
 *          I have now (May 2019) done another search and found the original author is Christian Land who has since released
 *          this code under the MIT licence as the tdCron library.  He has made this library freely available on Github
 *          at https://github.com/chland/tdCron.
 *
 *          I would like to thank Christian for his initial work on this library as, after a few tweaks, it has become the
 *          integral scheduling code for Hazaar MVC/Warlock.
 *
 * @license https://github.com/chland/tdCron/blob/master/LICENSE.md MIT License
 */
class Cron {

    /**
     * Ranges.
     *
     * @var mixed
     */
    private $ranges = array(
        IDX_MINUTE  => array('min' => 0,
                             'max' => 59,
                             'name' => 'i'),    // Minutes
        IDX_HOUR    => array('min' => 0,
                             'max' => 23,
                             'name' => 'G'),    // Hours
        IDX_DAY     => array('min' => 1,
                             'max' => 31,
                             'name' => 'd'),    // Days
        IDX_MONTH   => array('min' => 1,
                             'max' => 12,
                             'name' => 'm'),    // Months
        IDX_WEEKDAY => array('min' => 0,
                             'max' => 7,
                             'name' => 'w')    // Weekdays
    );

    /**
     * Named intervals.
     *
     * @var mixed
     */
    private $intervals = array(
        '@yearly'   => '0 0 1 1 *',
        '@annualy'  => '0 0 1 1 *',
        '@monthly'  => '0 0 1 * *',
        '@weekly'   => '0 0 * * 0',
        '@midnight' => '0 0 * * *',
        '@daily'    => '0 0 * * *',
        '@hourly'   => '0 * * * *',
        '@reboot'   => 'now'
    );

    /**
     * Possible keywords for months/weekdays.
     *
     * @var mixed
     */
    private $keywords = array(
        IDX_MONTH   => array(
            '/(january|januar|jan)/i'           => 1,
            '/(february|februar|feb)/i'         => 2,
            '/(march|maerz|m�rz|mar|mae|m�r)/i' => 3,
            '/(april|apr)/i'                    => 4,
            '/(may|mai)/i'                      => 5,
            '/(june|juni|jun)/i'                => 6,
            '/(july|juli|jul)/i'                => 7,
            '/(august|aug)/i'                   => 8,
            '/(september|sep)/i'                => 9,
            '/(october|oktober|okt|oct)/i'      => 10,
            '/(november|nov)/i'                 => 11,
            '/(december|dezember|dec|dez)/i'    => 12
        ),
        IDX_WEEKDAY => array(
            '/(sunday|sonntag|sun|son|su|so)/i'      => 0,
            '/(monday|montag|mon|mo)/i'              => 1,
            '/(tuesday|dienstag|die|tue|tu|di)/i'    => 2,
            '/(wednesdays|mittwoch|mit|wed|we|mi)/i' => 3,
            '/(thursday|donnerstag|don|thu|th|do)/i' => 4,
            '/(friday|freitag|fre|fri|fr)/i'         => 5,
            '/(saturday|samstag|sam|sat|sa)/i'       => 6
        )
    );

    /**
     * @var string The parsed CRON expression
     */
    private $pcron = NULL;

    function __construct($expression) {

        $this->pcron = is_int($expression) ? $expression : $this->parse($expression);

        if($this->pcron === false)
            throw new \Hazaar\Exception('Invalid CRON time expression');

    }

    /**
     * Calculates the next time and date based on the supplied expression.
     *
     * If a reference-time is passed, the next time and date after that time is calculated.
     *
     * @param    int $timestamp optional reference-time
     *
     * @return    int|null
     */
    public function getNextOccurrence($timestamp = NULL) {

        if(!$this->pcron)
            return null;

        $next = $this->getTimestamp($timestamp);

        $next[IDX_MINUTE]++;

        $next_time = $this->calculateDateTime($next);

        return $next_time;

    }

    /**
     * Calculates the last time and date before the supplied expression.
     *
     * If a reference-time is passed, the last time and date before that time is calculated.
     *
     * @param    int $timestamp optional reference-time
     *
     * @return    int|null
     */
    public function getLastOccurrence($timestamp = NULL) {

        if(!$this->pcron)
            return null;

        // Convert timestamp to array
        $last = $this->getTimestamp($timestamp);

        // Calculate date/time
        $last_time = $this->calculateDateTime($last, FALSE);

        // return calculated time
        return $last_time;

    }

    /**
     * Calculates the time and date at which the next/last call of a cronjob is/was due.
     *
     * @param    mixed $rtime reference-time
     *
     * @param    bool  $next  true = nextOccurence, false = lastOccurence
     *
     * @return   int|null
     */
    private function calculateDateTime($rtime, $next = TRUE) {

        if(is_int($this->pcron)){

            $timestamp = mktime($rtime[1], $rtime[0], 0, $rtime[3], $rtime[2], $rtime[5]);

            return ($next === true) ? (($this->pcron >= $timestamp) ? $this->pcron : null) : (($this->pcron < $timestamp) ? $this->pcron : null);

        }

        // Initialize vars
        $calc_date = TRUE;

        $cron = ($next ? $this->pcron : $this->arrayReverse($this->pcron));

        if(! $cron)
            return null;

        // OK, lets see if the day/month/weekday of the reference-date exist in our
        // $cron-array.
        if(! in_array($rtime[IDX_DAY], $cron[IDX_DAY]) ||
            ! in_array($rtime[IDX_MONTH], $cron[IDX_MONTH]) ||
            ! in_array($rtime[IDX_WEEKDAY], $cron[IDX_WEEKDAY])
        ) {

            // OK, things are easy. The day/month/weekday of the reference time
            // can't be found in the $cron-array. This means that no matter what
            // happens, we WILL end up at at a different date than that of our
            // reference-time. And in this case, the lastOccurrence will ALWAYS
            // happen at the latest possible time of the day and the nextOccurrence
            // at the earliest possible time.
            //
            // In both cases, the time can be found in the first elements of the
            // hour/minute cron-arrays.
            $rtime[IDX_HOUR] = reset($cron[IDX_HOUR]);

            $rtime[IDX_MINUTE] = reset($cron[IDX_MINUTE]);

        } else {

            // OK, things are getting a little bit more complicated...
            $nhour = $this->findValue($rtime[IDX_HOUR], $cron[IDX_HOUR], $next);

            // Meh. Such a cruel world. Something has gone awry. Lets see HOW awry it went.

            if($nhour === FALSE) {

                // Ah, the hour-part went wrong. Thats easy. Wrong hour means that no
                // matter what we do we'll end up at a different date. Thus we can use
                // some simple operations to make things look pretty ;-)
                //
                // As alreasy mentioned before -> different date means earliest/latest
                // time:
                $rtime[IDX_HOUR] = reset($cron[IDX_HOUR]);

                $rtime[IDX_MINUTE] = reset($cron[IDX_MINUTE]);

                // Now all we have to do is add/subtract a day to get a new reference time
                // to use later to find the right date. The following line probably looks
                // a little odd but thats the easiest way of adding/substracting a day without
                // screwing up the date. Just trust me on that one ;-)
                $rtime = explode(',', strftime('%M,%H,%d,%m,%w,%Y', mktime($rtime[IDX_HOUR], $rtime[IDX_MINUTE], 0, $rtime[IDX_MONTH], $rtime[IDX_DAY], $rtime[IDX_YEAR]) + ((($next) ? 1 : -1) * 86400)));

            } else {

                // OK, there is a higher/lower hour available. Check the minutes-part.
                $nminute = $this->findValue($rtime[IDX_MINUTE], $cron[IDX_MINUTE], $next);

                if($nminute === FALSE) {

                    // No matching minute-value found... lets see what happens if we substract/add an hour
                    $nhour = $this->findValue($rtime[IDX_HOUR] + (($next) ? 1 : -1), $cron[IDX_HOUR], $next);

                    if($nhour === FALSE) {

                        // No more hours available... add/substract a day... you know what happens ;-)
                        $nminute = reset($cron[IDX_MINUTE]);

                        $nhour = reset($cron[IDX_HOUR]);

                        $rtime = explode(',', strftime('%M,%H,%d,%m,%w,%Y', mktime($nhour, $nminute, 0, $rtime[IDX_MONTH], $rtime[IDX_DAY], $rtime[IDX_YEAR]) + ((($next) ? 1 : -1) * 86400)));

                    } else {

                        // OK, there was another hour. Set the right minutes-value
                        $rtime[IDX_HOUR] = $nhour;

                        $rtime[IDX_MINUTE] = (($next) ? reset($cron[IDX_MINUTE]) : end($cron[IDX_MINUTE]));

                        $calc_date = FALSE;

                    }

                } else {

                    // OK, there is a matching minute... reset minutes if hour has changed

                    if($nhour <> $rtime[IDX_HOUR]) {

                        $nminute = reset($cron[IDX_MINUTE]);

                    }

                    // Set time
                    $rtime[IDX_HOUR] = $nhour;

                    $rtime[IDX_MINUTE] = $nminute;

                    $calc_date = FALSE;

                }

            }

        }

        // If we have to calculate the date... we'll do so

        if($calc_date) {

            if(in_array($rtime[IDX_DAY], $cron[IDX_DAY]) && in_array($rtime[IDX_MONTH], $cron[IDX_MONTH]) && in_array($rtime[IDX_WEEKDAY], $cron[IDX_WEEKDAY])) {

                return mktime($rtime[1], $rtime[0], 0, $rtime[3], $rtime[2], $rtime[5]);

            } else {

                // OK, some searching necessary...
                $cdate = mktime(0, 0, 0, $rtime[IDX_MONTH], $rtime[IDX_DAY], $rtime[IDX_YEAR]);

                // OK, these three nested loops are responsible for finding the date...
                //
                // The class has 2 limitations/bugs right now:
                //
                //	-> it doesn't work for dates in 2036 or later!
                //	-> it will most likely fail if you search for a Feburary, 29th with a given weekday
                //	   (this does happen because the class only searches in the next/last 10 years! And
                //	   while it usually takes less than 10 years for a "normal" date to iterate through
                //	   all weekdays, it can take 20+ years for Feb, 29th to iterate through all weekdays!
                for($nyear = $rtime[IDX_YEAR]; (($next) ? ($nyear <= $rtime[IDX_YEAR] + 10) : ($nyear >= $rtime[IDX_YEAR] - 10)); $nyear = $nyear + (($next) ? 1 : -1)) {

                    foreach($cron[IDX_MONTH] as $nmonth) {

                        foreach($cron[IDX_DAY] as $nday) {

                            if(checkdate($nmonth, $nday, $nyear)) {

                                $ndate = mktime(0, 0, 1, $nmonth, $nday, $nyear);

                                if(($next) ? ($ndate >= $cdate) : ($ndate <= $cdate)) {

                                    $dow = date('w', $ndate);

                                    // The date is "OK" - lets see if the weekday matches, too...
                                    if(in_array($dow, $cron[IDX_WEEKDAY])) {

                                        // WIN! :-) We found a valid date...
                                        $rtime = explode(',', strftime('%M,%H,%d,%m,%w,%Y', mktime($rtime[IDX_HOUR], $rtime[IDX_MINUTE], 0, $nmonth, $nday, $nyear)));

                                        return mktime($rtime[1], $rtime[0], 0, $rtime[3], $rtime[2], $rtime[5]);

                                    }

                                }

                            }

                        }

                    }

                }

            }

            return null;

        }

        return mktime($rtime[1], $rtime[0], 0, $rtime[3], $rtime[2], $rtime[5]);

    }

    /**
     * Converts an unix-timestamp to an array.
     *
     * The returned array contains the following values:
     *
     *    [0]    -> minute
     *    [1]    -> hour
     *    [2]    -> day
     *    [3]    -> month
     *    [4]    -> weekday
     *    [5]    -> year
     *
     * The array is used by various functions.
     *
     * @param    int $timestamp If none is given, the current time is used
     *
     * @return    Array
     */
    private function getTimestamp($timestamp = NULL) {

        if(is_null($timestamp))
            $timestamp = time();

        $arr = explode(',', strftime('%M,%H,%d,%m,%w,%Y', $timestamp));

        // Remove leading zeros (or we'll get in trouble ;-)
        array_walk($arr, function(&$value){ $value = intval($value); });

        return $arr;

    }

    /**
     * Checks if the given value exists in an array.
     *
     * If it does not exist, the next higher/lower value is returned (depending on $next). If no higher/lower value
     * exists, false is returned.
     *
     * @param    int   $value
     *
     * @param    mixed $data
     *
     * @param    bool  $next
     *
     * @return    mixed The next value or false if there isn't one.
     */
    private function findValue($value, $data, $next = TRUE) {

        if(in_array($value, $data)) {

            return (int)$value;

        } else {

            if(($next) ? ($value <= end($data)) : ($value >= end($data))) {

                foreach($data as $curval) {

                    if(($next) ? ($value <= (int)$curval) : ($curval <= $value)) {

                        return (int)$curval;

                    }

                }

            }

        }

        return FALSE;

    }

    /**
     * Reverses all sub-arrays of our cron array.
     *
     * The reversed values are used for calculations that are run when getLastOccurence() is called.
     *
     * @param  mixed $cron
     *
     * @return Array
     */
    private function arrayReverse($cron) {

        foreach($cron as $key => $value) {

            $cron[$key] = array_reverse($value);

        }

        return $cron;

    }

    /**
     * Analyses crontab-expressions like "* * 1,2,3 * mon,tue" and returns an array containing all values.
     *
     * If it can not be parsed then it returns FALSE
     *
     * @param        string $expression The cron-expression to parse.
     *
     * @return       mixed
     */
    private function parse($expression) {

        // First of all we cleanup the expression and remove all duplicate tabs/spaces/etc.
        $expression = preg_replace('/(\s+)/', ' ', strtolower(trim($expression)));

        // Convert named expressions if neccessary
        if(substr($expression, 0, 1) == '@') {

            $expression = strtr($expression, $this->intervals);

            if(substr($expression, 0, 1) == '@')
                return FALSE;

        }

        if($expression === 'now')
            return intval(floor(time() / 60) * 60);

        // Next basic check... do we have 5 segments?
        $cron = explode(' ', $expression);

        if(count($cron) !== 5)
            return FALSE;

        $dummy = array();

        // Yup, 5 segments... lets see if we can work with them
        foreach($cron as $idx => $segment){

            if(($value = $this->expandSegment($idx, $segment)) === false)
                return false;

            $dummy[$idx] = $value;

        }

        return $dummy;

    }

    /**
     * Analyses a single segment
     *
     * @param   int    $idx
     * @param   string $segment
     *
     * @return  mixed
     */
    private function expandSegment($idx, $segment) {

        // Replace months/weekdays like "January", "February", etc. with numbers
        if(isset($this->keywords[$idx])) {

            $segment = preg_replace(
                array_keys($this->keywords[$idx]),
                array_values($this->keywords[$idx]),
                $segment
            );

        }

        // Replace wildcards
        $token = substr($segment, 0, 1);

        if($token === '*')
            $segment = preg_replace('/^\*(\/\d+)?$/i', $this->ranges[$idx]['min'] . '-' . $this->ranges[$idx]['max'] . '$1', $segment);
        elseif($token === '?')
            $segment = preg_replace('/^\?(\/\d+)?$/i', date($this->ranges[$idx]['name']) . '$1', $segment);

        // Make sure that nothing unparsed is left :)
        $dummy = preg_replace('/[0-9\-\/\,]/', '', $segment);

        if(! empty($dummy))
            return FALSE;

        // At this point our string should be OK - lets convert it to an array
        $result = array();

        $atoms = explode(',', $segment);

        foreach($atoms as $curatom)
            $result = array_merge($result, $this->parseAtom($curatom));

        // Get rid of duplicates and sort the array
        $result = array_unique($result);

        sort($result);

        // Check for invalid values
        if($idx == IDX_WEEKDAY) {

            if(end($result) == 7) {

                if(reset($result) <> 0) {

                    array_unshift($result, 0);

                }

                array_pop($result);

            }

        }

        foreach($result as $key => $value) {

            if(($value < $this->ranges[$idx]['min']) || ($value > $this->ranges[$idx]['max']))
                return FALSE;

        }

        return $result;

    }

    /**
     * Analyses a single segment
     *
     * @param        string $atom The segment to parse
     *
     * @return       array
     */
    private function parseAtom($atom) {

        $expanded = array();

        if(preg_match('/^(\d+)-(\d+)(\/(\d+))?/i', $atom, $matches)) {

            $low = $matches[1];

            $high = $matches[2];

            if($low > $high) {

                list($low, $high) = array($high, $low);

            }

            $step = isset($matches[4]) ? $matches[4] : 1;

            for($i = $low; $i <= $high; $i += $step) {

                $expanded[] = (int)$i;

            }

        } else {

            $expanded[] = (int)$atom;

        }

        return $expanded;

    }

}
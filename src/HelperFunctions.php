<?php

/**
 * @file        Hazaar/HelperFunctions.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

/**
 * @brief Array/Object value normalizer
 *
 * @detail Returns a value from an arraym or a property from an object, if it exists. If it doesn't exist a default
 * value can be specified.  Otherwise null is returned.
 *
 * This helps prevent array key not found errors in the PHP interpreter.
 *
 * Keys may be specified using dot-notation.  This allows ake to called only once instead of for each
 * element in a reference chain.  For example, you can call `ake($myarray, 'object.child.other');` and each
 * reference will be recursed into if it exists.  If at any step the child does not exist (or is empty if
 * `$non_empty === TRUE`) then execution will stop and return the default value.  This will also handle things
 * if the child is not an array or object.
 * 
 * The `$key` parameter can also be an array of keys.  In this case, the array will be searched for each key
 * and the first value found will be returned.  This is handy if you need a value that could be stored under
 * multiple possible key names.
 *
 * @since 1.0.0
 *
 * @param mixed   $array     The array to search.  Objects with public properties are also supported.
 * @param mixed   $key       The array key or object property name to look for.  This can now also be an array of keys and the first one found will be returned.
 * @param mixed   $default   An optional default value to return if the key or property does not exist.
 * @param boolean $non_empty Indicates that empty values, such as empty arrays and strings should be treated as NULL, even if they exist as elements in the array/object.
 *
 * @return mixed The value if it exists in the array. Returns the default if it does not. Default is null if no other default is specified.
 */
function ake($array, $key, $default = NULL, $non_empty = FALSE) {

    if(is_string($key) || is_int($key)){

        if ((is_array($array) || $array instanceof \ArrayAccess)
            && isset($array[$key])
            && $array[$key] !== NULL
            && (!$non_empty || ($non_empty && (is_string($array[$key]) ? trim($array[$key]) : $array[$key]))))
            return $array[$key];

        if ($array instanceof \Hazaar\Model\Strict)
            return $array->ake($key, $default, $non_empty);

        if(is_object($array)){

            if(property_exists($array, $key) && ($non_empty === false || is_string($array->$key) === false || ($non_empty === true && trim($array->$key) !== NULL)))
                return $array->$key;
            elseif($array instanceof \ArrayAccess && isset($array[$key]))
                return $array[$key];

        }

        if(strpos($key, '.') !== false){

            $parts = explode('.', $key);

            foreach($parts as $part) if(($array = ake($array, $part, $default, $non_empty)) === $default) break;

            return $array;

        }

    }elseif(is_array($key)){

        foreach($key as $k){

            if($value = ake($array, $k, null, $non_empty))
                return $value;

        }

    }

    return $default;

}

/**
 * Array Key Rename
 *
 * Rename a key in an array to something else.
 *
 * @param array  $array     The array to work on.
 * @param string $key_from  The key name to rename.
 * @param string $key_to    The key name to change to.
 */
function akr(&$array, $key_from, $key_to){

    if(is_array($array) && array_key_exists($key_from, $array)){

        $array[$key_to] = $array[$key_from];

        unset($array[$key_from]);

    }elseif(is_object($array) && property_exists($array, $key_from)){

        $array->$key_to = $array->$key_from;

        unset($array->$key_from);

    }

    return $array;

}

/**
 * @brief Normalize boolean values
 *
 * @detail This helper function will take a string representation of a boolean such as 't', 'true', 'yes', 'ok' and
 * return a boolean type value.
 *
 * @since 1.0.0
 *
 * @param string $value The string representation of the boolean
 *
 * @return boolean
 */
function boolify($value) {

    $value = strbool($value);

    if ($value == 'true')
        return TRUE;

    return FALSE;

}

/**
 * @brief Retrieve string value of boolean
 *
 * @detail Normalise boolean string to 'true' or 'false' based on various boolean representations
 *
 * @since 1.0.0
 *
 * @param string $value The string representation of the boolean
 *
 * @return string
 */
function strbool($value) {

    if (is_array($value))
        return FALSE;

    $value = strtolower(trim($value));

    if ($value == 't' || $value == 'true' || $value == 'on' || $value == 'yes' || $value == 'y' || $value == 'ok')
        return 'true';

    elseif (preg_match('/(\!|not)\s*null/', $value))
        return 'true';

    elseif ((int) $value != 0)
        return 'true';

    return 'false';

}

/**
 * @brief Test whether a value is a boolean
 *
 * @detail Checks for various representations of a boolean, including strings of 'true/false' and 'yes/no'.
 *
 * @since 2.0.0
 *
 * @param string $value The string representation of the boolean
 *
 * @return boolean
 */
function is_boolean($value) {

    if (!is_string($value))
        return is_bool($value);

    $accepted = array(
        't',
        'true',
        'f',
        'false',
        'y',
        'yes',
        'n',
        'no',
        'on',
        'off'
    );

    return in_array(strtolower(trim($value)), $accepted);

}

/**
 * The Yes/No function
 *
 * Simply returns Yes or No based on a boolean value.
 *
 * @param boolean A boolean type value.  Can be an actual boolean or 1/0 yes/no on/off, etc.
 *
 * @return string
 */
function yn($value){

    return boolify($value) ? 'Yes' : 'No';

}

/**
 * @brief Retreive first non-null value from parameter list
 *
 * @detail Takes a variable list of arguments and returns the first non-null value.
 *
 * @since 1.0.0
 * 
 * @return mixed The first non-NULL argument value, or NULL if all values are NULL.
 */
function coalesce() {

    foreach(func_get_args() as $arg) {

        if ($arg !== NULL)
            return $arg;
    }

    return NULL;

}

/**
 * @brief Test of array is multi-dimensional
 *
 * @detail Test an array to see if it's a multidimensional array and returns TRUE or FALSE.
 *
 * @since 1.0.0
 *
 * @param array $array The array to test
 *
 * @return boolean
 */
function is_multi_array(array $array) {

    foreach($array as $a)
        if (is_array($a))
            return TRUE;

    return FALSE;

}

/**
 * @brief Test of array is an associative array
 *
 * @detail Test an array to see if it is associative or numerically keyed. Returns TRUE for associative or FALSE
 * for numeric.
 *
 * @since 1.0.0
 *
 * @param array $array The array to test
 *
 * @return boolean
 */
function is_assoc(array $array) {

    return (bool) count(array_filter(array_keys($array), 'is_string'));

}

function array_flatten($array, $delim = '=', $section_delim = ';') {

    if (!is_array($array))
        return NULL;

    $items = array();

    foreach($array as $key => $value) {

        $items[] = $key . $delim . $value;
    }

    return implode($section_delim, $items);

}

function array_unflatten($items, $delim = '=', $section_delim = ';') {

    if (!is_array($items))
        $items = preg_split("/\s*\\$section_delim\s*/", trim($items));

    $result = array();

    foreach($items as $item) {

        $parts = preg_split("/\s*\\$delim\s*/", $item, 2);

        if (count($parts) > 1) {

            list($key, $value) = $parts;

            $result[$key] = trim($value);
        } else {

            $result[] = trim($parts[0]);
        }
    }

    return $result;

}

/**
 * Collate a multi-dimensional array into an associative array where $key_item is the key and $value_item is the value.
 *
 * * If the key value does not exist in the array, the element is skipped.
 * * If the value item does not exist, the value will be NULL.
 *
 * @param mixed $array The array to collate.
 * @param mixed $key_item The value to use as the key.
 * @param mixed $value_item The value to use as the value.  If not supplied, the whole element will be the value.  Allows re-keying a mult-dimensional array by an array element.
 * @param mixed $group_item Optional value to group items by.
 * @return array
 */
function array_collate($array, $key_item, $value_item = null, $group_item = null){

    $result = array();

    foreach($array as $item){

        if(!isset($item[$key_item]))
            continue;

        if($group_item !== null)
            $result[ake($item, $group_item)][$item[$key_item]] = ake($item, $value_item);
        else
            $result[$item[$key_item]] = ($value_item === null) ? $item : ake($item, $value_item);

    }

    return $result;

}

/**
 * Converts a multi dimensional array into key[key][key] => value syntax that can be used in html INPUT field names.
 *
 * @param mixed $array
 *
 *@return array
 */
function array_build_html($array, $root = true){

    if(!is_array($array))
        return null;

    $result = array();

    foreach($array as $key => $value){

        if(is_array($value)){

            $value = array_build_html($value, false);

            foreach($value as $skey => $svalue){

                $newkey = $key . ( $root ? '[' . $skey . ']' : '][' . $skey);

                $result[$newkey] = $svalue;

            }

        }else{

            $result[$key] = $value;

        }

    }

    return $result;

}

/**
 * @brief       Convert to dot notation
 *
 * @detail      Converts/reduces a multidimensional array into a single dimensional array with keys in dot-notation.
 *
 * @param array   $array The array to convert.
 * @param string  $separator The separater to use between keys.  Defaults to '.', hence the name of the functions.
 * @param integer $depth Limit to the specified depth. Starting at 1, this is the number of levels to return.
 *                Essentially, this is the number of dots, plus one.
 * @param string  $numeric_array_separators This parameter is used to display numeric arrays. It defaults to '[]' which
 *                means that numeric arrays will appear as "item[index].key".  This argument must be at least two
 *                characters.  The first character is the left side and the second character is the right side.  Any
 *                non-string values or string values less than 2 characters long will be ignored and numeric arrays
 *                will not be used.  To disable numeric arrays and cause elements with a numeric key to be output
 *                the same as other string key elements, simply set this to NULL.
 *
 * @since       2.0.0
 *
 * @return array|boolean
 */
function array_to_dot_notation($array, $separator = '.', $depth = null, $numeric_array_separators = '[]') {

    if(!is_array($array))
        return false;

    if(!($depth === null || $depth > 1))
        return $array;

    $rows = array();

    $numeric_array = (is_string($numeric_array_separators) && strlen($numeric_array_separators) >= 2);

    foreach($array as $key => $value) {

        if(is_array($value)){

            $children = array_to_dot_notation($value, $separator, (is_null($depth) ? $depth : ($depth - 1)), $numeric_array_separators);

            foreach($children as $childkey => $child) {

                if($numeric_array && is_numeric($key))
                    $new_key = $numeric_array_separators[0] . $key . $numeric_array_separators[1];
                else
                    $new_key = $key;

                if($numeric_array && is_numeric($childkey)){

                    $new_key .= $numeric_array_separators[0] . $childkey . $numeric_array_separators[1];

                }elseif($numeric_array && $childkey[0] === $numeric_array_separators[0]){

                    $new_key .= $childkey;

                }else{

                    $new_key .= $separator . $childkey;

                }

                $rows[$new_key] = $child;

            }

        } else {

            $rows[$key] = $value;

        }

    }

    return $rows;

}

/**
 * @brief           Convert a single dimension array in dot notation into a multi-dimensional array.
 *
 * @detail          Inverse to the above function, array_to_dot_notation().
 *
 * @param           mixed $array
 *
 * @since           2.3.27
 *
 * @return          array
 */
function array_from_dot_notation($array) {

    if(!is_array($array))
        return array();

    $new = array();

    foreach($array as $idx => $value) {

        $parts = explode('.', $idx);

        $cur =& $new;

        foreach($parts as $part) {

            if(substr($part, -1) === ']' && ($pos = strpos($part, '[')) > 0){

                if(!preg_match_all('/\[([\w\d]+)\]/', substr($part, $pos), $matches))
                    continue;

                $key = substr($part, 0, $pos);

                if(!isset($cur[$key]))
                    $cur[$key] = array();

                $cur =& $cur[$key];

                foreach($matches[1] as $match){

                    if(is_numeric($match)) settype($match, 'int');

                    if(!isset($cur[$match]))
                        $cur[$match] = array();

                    if(is_array($cur))
                        $cur =& $cur[$match];

                }

            }else{

                if(!isset($cur[$part]))
                    $cur[$part] = array();

                if(is_array($cur))
                    $cur =& $cur[$part];

            }

        }

        $cur = $value;

    }

    return $new;

}

function base64url_encode($data) {

    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');

}

function base64url_decode($data) {

    return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) + (4 - (strlen($data) % 4) % 4), '=', STR_PAD_RIGHT));

}

/**
 * Seek the array cursor forward $count number of elements
 *
 * @param $array array The array to seek
 *
 * @param $count integer The number of elements to seek forward
 */
function array_seek(&$array, $count) {

    if (!is_array($array) || !$count > 0)
        return;

    for($i = 0; $i < $count; $i++)
        next($array);

}

/**
 * @brief Build a correctly formatted URL from argument list
 *
 * @detail This function will build a correctly formatted HTTP compliant URL using a list of parameters. If any
 * of the parameters are null then they will be omitted from the formatted output, including any extra values.
 *
 * For example, you can specify a username and a password which will be formatted as username:password\@.  However
 * if you omit the password you will simply get username\@.
 *
 * @param string  $scheme    The protocol to use. Usually http or https.
 * @param string  $host      Hostname
 * @param integer $port      (optional) Port
 * @param string  $user      (optional) Username
 * @param string  $pass      (optional) User password. If set, a username is required.
 * @param string  $path      (optional) Path suffix
 * @param array   $query     (optional) Array of parameters to send. ie: the stuff after the '?'. Uses http_build_query to generate string.
 * @param string  $fragment  (optional) Anything to go after the '#'.
 */
function build_url($scheme = 'http', $host = 'localhost', $port = NULL, $user = NULL, $pass = NULL, $path = NULL, $query = array(), $fragment = NULL) {

    $url = strtolower(trim($scheme)) . '://';

    if ($user = trim($user) || ($user && $pass = trim($pass))) {

        $url .= $user . ($pass ? ':' . $pass : NULL) . '@';
    }

    $url .= trim($host);

    if (is_numeric($port = trim($port)) && $port != 80)
        $url .= ':' . $port;

    if ($path = trim($path))
        $url .= $path;

    if (is_array($query) && count($query) > 0)
        $url .= '?' . http_build_query($query);

    if ($fragment = trim($fragment))
        $url .= '#' . $fragment;

    return $url;

}

/**
 * @brief Byte to string formatter
 *
 * @detail Formats an integer representing a size in bytes to a human readable string representation.
 *
 * @since 1.0.0
 *
 * @param int $bytes The byte value to convert to a string.
 * @param string $type The type to convert to. Type can be:
 *            * B (bytes)
 *            * K (kilobytes)
 *            * M (megabytes)
 *            * G (giabytes)
 *            * T (terrabytes)
 * @param int $precision The number of decimal places to show.
 *
 * @return string The human readable byte string. eg: '100 MB'.
 */
function str_bytes($bytes, $type = NULL, $precision = NULL, $exclude_suffix = FALSE) {

    if ($type === NULL) {

        if ($bytes < pow(2, 10)) {

            $type = 'B';
        } elseif ($bytes < pow(2, 20)) {

            $type = 'K';
        } elseif ($bytes < pow(2, 30)) {

            $type = 'M';
        } else {

            $type = 'G';
        }
    }

    $type = strtoupper($type);

    $value = $bytes;

    $suffix = 'bytes';

    $prec = 0;

    switch ($type) {

        case 'K' :
            $value = $bytes / pow(2, 10);

            $suffix = 'KB';

            break;

        case 'M' :
            $value = $bytes / pow(2, 20);

            $suffix = 'MB';

            $prec = 2;

            break;

        case 'G' :
            $value = $bytes / pow(2, 30);

            $suffix = 'GB';

            $prec = 2;

            break;

        case 'T' :
            $value = $bytes / pow(2, 40);

            $suffix = 'TB';

            $prec = 2;

            break;
    }

    if ($precision !== NULL)
        $prec = $precision;

    return number_format($value, $prec) . ($exclude_suffix ? '' : ' ' . $suffix);

}

/**
 * @brief String to bytes formatter
 *
 * @detail Returns an integer value representing a number of bytes from the standard bytes size string supplied.
 *
 * @since 1.0.0
 *
 * @param string $string The byte string value to convert to an integer. eg: '100MB'
 *
 * @return int|boolean The number of bytes or false on failure
 */
function bytes_str($string) {

    if (preg_match('/([\d\.]+)\s*(\w*)/', $string, $matches)) {

        $size = floatval($matches[1]);

        switch (strtoupper($matches[2])) {
            case 'K' :
            case 'KB' :
                $size = round($size * pow(2, 10));
                break;
            case 'M' :
            case 'MB' :
                $size = round($size * pow(2, 20));
                break;
            case 'G' :
            case 'GB' :

                $size = round($size * pow(2, 30));
                break;
            case 'T' :
            case 'TB' :
                $size = round($size * pow(2, 40));
                break;
        }

        return $size;
    }

    return false;

}

/**
 * @brief Convert a string interval to seconds
 *
 * @detail This function can be used to convert a string interval such as '1 week' into seconds. Currently
 * supported intervals are seconds, minutes, hours, days and weeks. Months are not supported because
 * some crazy crackpot decided to make them all different lengths, so without knowing which month we're
 * talking about, converting them to seconds is impossible.
 *
 * Multiple interval support is also possible. Intervals can be separated with a comma (,) or the word
 * 'and', for example:
 *
 * ```php
 * $foo = seconds('1 week, 2 days');
 * $bar = seconds('1 week and 2 days');
 * ```
 *
 * Both of these function calls will yeild the same result.
 *
 * @since 1.0.0
 *
 * @param string $interval The string interval to convert to seconds
 *
 * @return int Number of seconds in the interval
 */
function seconds($interval) {

    if (is_numeric($interval))
        return $interval;

    $intervals = preg_split('/(\s+and|\s*,)\s+/', $interval);

    $value = 0;

    foreach($intervals as $interval) {

        if (!preg_match('/(\d+)\s+(\w+)/', $interval, $matches))
            return 0;

        $val = $matches[1];

        switch (strtolower($matches[2])) {

            case 'second' :
            case 'seconds' :
                $value += $val;

                break;

            case 'minutes' :
                $value += ($val * 60);

                break;

            case 'hour' :
            case 'hours' :
                $value += ($val * 60 * 60);
                break;

            case 'day' :
            case 'days' :
                $value += ($val * 60 * 60 * 24);

                break;

            case 'week' :
            case 'weeks' :
                $value += ($val * 60 * 60 * 24 * 7);

                break;

            case 'year' :
            case 'years' :
                $value = ($val * 60 * 60 * 24 * 365.25);

                break;
        }
    }

    return $value;

}

/**
 * @brief Convert interval to minutes
 *
 * @detail Same as the seconds function except returns the number of minutes.
 *
 * @since 1.0.0
 *
 * @return int Minutes in interval
 */
function minutes($interval) {

    return seconds($interval) / 60;

}

/**
 * @brief Convert interval to hours
 *
 * @detail Same as the seconds function except returns the number of hours.
 *
 * @since 1.0.0
 *
 * @return int Hours in interval
 */
function hours($interval) {

    return minutes($interval) / 60;

}

/**
 * @brief Convert interval to days
 *
 * @detail Same as the seconds function except returns the number of days.
 *
 * @since 1.0.0
 *
 * @return int Days in interval
 */
function days($interval) {

    return hours($interval) / 24;

}

/**
 * @brief Convert interval to weeks
 *
 * @detail Same as the seconds function except returns the number of weeks.
 *
 * @since 1.0.0
 *
 * @return int Weeks in interval
 */
function weeks($interval) {

    return days($interval) / 7;

}

/**
 * @brief Convert interval to years
 *
 * @detail Same as the seconds function except returns the number of years.
 *
 * @since 1.0.0
 *
 * @return int Years in interval
 */
function years($interval) {

    return days($interval) / 365.25;

}

/**
 * @brief Get the age of a date.
 *
 * @detail This helper function will return the number of years between a specified date and now. Useful for
 * getting an age.
 *
 * @since 1.0.0
 *
 * @return int|boolean Number of years from the specified date to now, or FALSE on error.
 */
function age($date) {

    if($date instanceof \DateTime)
        $time = $date->getTimestamp();

    elseif(is_string($date))
        $time = strtotime($date);

    else
        return false;

    return years(time() - $time);

}

/**
 * @brief Convert interval to uptime string
 *
 * @detail This function will convert an integer of seconds into an uptime string similar to what is returned by
 * the unix uptime command. ie: '9 days 3:32:02'
 *
 * @since 1.0.0
 *
 * @return string Minutes in interval
 */
function uptime($interval) {

    $d = floor(days($interval));

    $h = floor(hours($interval)) - ($d * 24);

    $m = floor(minutes($interval)) - (($h + ($d * 24)) * 60);

    $s = floor(seconds($interval)) - ((($m + ($h + ($d * 24)) * 60)) * 60);

    $o = '';

    if ($d == 1)
        $o .= "$d day ";
    elseif ($d > 1)
        $o .= "$d days ";

    $o .= $h . ':' . str_pad($m, 2, '0', STR_PAD_LEFT) . ':' . str_pad($s, 2, '0', STR_PAD_LEFT);

    return $o;

}

/**
 * Return a string interval in a nice readable format.
 *
 * Similar to uptime() this extends the format into a complete string in a nice, friendly readable format.
 *
 * @param mixed $seconds The interval to convert in seconds.
 *
 * @return string A friendly string.
 */
function interval($seconds){

    if($seconds < 1)
        return 'No time at all';

    $o = array();

    if(($d = floor(days($seconds))) > 0)
        $o[] = $d . ' day' . (($d > 1) ? 's' : '');

    if(($h = floor(hours($seconds)) - ($d * 24)) > 0)
        $o[] = $h . ' hour' . (($h > 1) ? 's' : '');

    if(($m = floor(minutes($seconds)) - (($h + ($d * 24)) * 60)) > 0)
        $o[] = $m . ' minute' . (($m > 1) ? 's' : '');

    $o = implode(', ', $o);

    if(($s = floor(seconds($seconds)) - ((($m + ($h + ($d * 24)) * 60)) * 60)) > 0)
        $o .= ($o ? ' and ' : '') . $s . ' second' . (($s > 1) ? 's' : '');

    return $o;

}

/**
 * @brief Fix a numeric string
 *
 * @detail Sometimes a numeric (int or float) will be stored as a string variable. This can cause
 * issues with functions that check the variable type to determine what to do with it. This
 * function allows you to simply pass a variable through it and it will 'fix' it. If it is
 * a string and is a numeric value, it will be converted to the appropriate variable type.
 *
 * If the value is a string and is meant to be a string, it will be left alone.
 *
 * @since 1.0.0
 *
 * @param mixed $value The variable to type check and possibly fix.
 *
 * @return mixed The fixed variable.
 */
function str_fixtype(&$value) {

    if (is_numeric($value)) {

        /*
         * NOTE: We can't use settype() here as it is unable to distinguish between a float and an int. So we
         * use this trick to force PHP to figure it out for itself. Adding zero will not affect the value
         * but will cause the type to be converted to int or float.
         */

        $value = $value + 0;
    }

    return $value;

}

/**
 * Helper function to get the status text for an HTTP response code
 *
 * @param integer $code The response code.
 *
 * @return mixed A string containing the response text if the code is valid. False otherwise.
 */
function http_response_text($code) {

    $text = FALSE;

    if ($file = \Hazaar\Loader::getFilePath(FILE_PATH_SUPPORT, 'Http_Status.dat')) {

        if (preg_match('/^' . $code . '\s(.*)$/m', file_get_contents($file), $matches)) {

            $text = $matches[1];

        }

    }

    return $text;

}

function hazaar_request_headers() {

    if (!is_array($_SERVER))
        return array();

    $headers = array();

    foreach($_SERVER as $name => $value) {

        if (substr($name, 0, 5) == 'HTTP_')
            $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;

    }

    //Fix a missing Content-Type header
    if(isset($_SERVER['CONTENT_TYPE'])) $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];

    //Fix a missing Content-Length header
    if(isset($_SERVER['CONTENT_LENGTH'])) $headers['Content-Length'] = intval($_SERVER['CONTENT_LENGTH']);

    return $headers;

}

if(!function_exists('getallheaders')){

    function getallheaders(){

        return hazaar_request_headers();

    }

}

// apache_request_headers replicement for nginx
if(!function_exists('apache_request_headers')){

    function apache_request_headers() {

        return hazaar_request_headers();

    }

}

if(!function_exists('http_response_code')){

    function http_response_code($code = NULL) {

        if ($code) {

            if ($text = http_response_text($code)) {

                header($_SERVER['SERVER_PROTOCOL'] . ' ' . $code . ' ' . $text);

                $_SERVER['HTTP_RESPONSE_CODE'] = $code;

                return true;

            } else {

                die('Missing Http_Status.dat file!');

            }

        } else {

            return (array_key_exists('HTTP_RESPONSE_CODE', $_SERVER) ? $_SERVER['HTTP_RESPONSE_CODE'] : 200);

        }

        return false;

    }
}

function guid() {

    $format = 'xxxxxxxx-yxxx-yxxx-yxxx-xxxxxxxxxxxx';

    $value = preg_replace_callback('/[xy]/', function ($c) {

        $num = rand(0, 15);
        $value = (($c[0] == 'x') ? $num : ($num & 0x3 | 0x8));

        return dechex($value);
    }, $format);

    return $value;

}

function dump($data = NULL) {

    $response = null;

    if(PHP_SAPI == 'cli'){

        $response = 'hazaar';

    }else{

        $app = Hazaar\Application::getInstance();

        if($app && $app->request && !($response = $app->getResponseType())){

            if (function_exists('apache_request_headers')) {

                $h = apache_request_headers();

                if (ake($h, 'X-Requested-With') == 'XMLHttpRequest')
                    $response = 'json';

            }

        }elseif (getenv('HAZAAR_SID')) {

            $response = 'hazaar';

        }

    }

    $exec_time = round((microtime(true) - HAZAAR_EXEC_START) * 1000, 2);

    if($response == 'json'){

        $dump = array(
            'data' => $data,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            'exec' => $exec_time
        );

        header('Content-Type: application/json');

        echo json_encode($dump);

        exit();

    }elseif($response == 'hazaar'){

        echo "Hazaar Dump:\n\n";

        var_dump($data);

        echo "\n\n";

        debug_print_backtrace();

    } else {

        $style = "<style>
body { padding: 0; margin: 0; font-family: Arial, Helvetica, sans-serif; }
.exec_time, .endtime { float: right; padding: 0 15px; font-size: 22px; line-height: 50px; color: #fff; }
.exec_time.good { background-color: #33bb33; }
.exec_time.ok { background-color: gold; color: #333; }
.exec_time.bad { background-color: #ea4040; }
h2 { background: #554D7C; color: #fff; padding: 0 15px; line-height: 50px; }
pre { margin: 30px; }
</style>";

        echo "<html>\n\n<head>\n\t<title>Hazaar Dump</title>\n$style</head>\n\n<body>\n\n";

        $speed_class = ($exec_time > 250) ? (($exec_time > 500) ? 'bad' : 'ok') : 'good';

        echo "<div class=\"exec_time $speed_class\">{$exec_time}ms</div>";

        echo "<div class=\"endtime\">" . date('c') . "</div>";

        echo "<h2>Dump</h2>\n\n";

        var_dump($data);

        echo "\n\n<h2>Backtrace</h2>\n\n<pre>\n";

        $e = new Exception();

        print_r(str_replace('/path/to/code/', '', $e->getTraceAsString()));

        echo "\n</pre>\n\n</body>\n\n</html>\n\n";

    }

    exit();

}

/**
 * Perform a regular expression match on a string using multiple possible regular expressions.
 *
 * This is the same as calling preg_match() except that $patterns is an array of regular expressions.
 * Execution returns on the first match.
 *
 * @param array   $patterns   An array of patterns to search for, as a string.
 * @param string  $subject    The input string.
 * @param array   $matches    If matches is provided, then it is filled with the results of search. $matches[0] will contain the text that matched the full pattern, $matches[1] will have the text that matched the first captured parenthesized subpattern, and so on.
 * @param integer $flags      For details on available flags, see the [preg_match()](http://php.net/manual/en/function.preg-match.php) documentation.
 * @param integer $offset     Normally, the search starts from the beginning of the subject string. The optional parameter offset can be used to specify the alternate place from which to start the search (in bytes).
 *
 * @return boolean|integer
 */
function preg_match_array($patterns, $subject, &$matches = NULL, $flags = 0, $offset = 0) {

    if (!is_array($patterns))
        return FALSE;

    foreach($patterns as $pattern) {

        if (preg_match($pattern, $subject, $matches, $flags, $offset))
            return 1;

    }

    return 0;

}

if(!function_exists('money_format')){

    /**
     * Replacement for the "built-in" PHP function money_format(), which isn't always built-in.
     *
     * Some systems, particularly Windows (also possible BSD) the built-in money_format() function
     * will not be defined.  This is because it's just a wrapper for a C function called strfmon()
     * which isn't available on all platforms (such as Windows).
     *
     * @param mixed $format The format specification, see
     *
     * @param mixed $number The number to be formatted.
     *
     * @return string The formatted number.
     */
    function money_format($format, $number) {

        $regex  = '/%((?:[\^!\-]|\+|\(|\=.)*)([0-9]+)?(?:#([0-9]+))?(?:\.([0-9]+))?([in%])/';

        if (setlocale(LC_MONETARY, 0) == 'C')
            setlocale(LC_MONETARY, '');

        $locale = localeconv();

        preg_match_all($regex, $format, $matches, PREG_SET_ORDER);

        foreach ($matches as $fmatch) {

            $value = floatval($number);

            $flags = array(
                'fillchar'  => preg_match('/\=(.)/', $fmatch[1], $match) ?
                               $match[1] : ' ',
                'nogroup'   => preg_match('/\^/', $fmatch[1]) > 0,
                'usesignal' => preg_match('/\+|\(/', $fmatch[1], $match) ?
                               $match[0] : '+',
                'nosimbol'  => preg_match('/\!/', $fmatch[1]) > 0,
                'isleft'    => preg_match('/\-/', $fmatch[1]) > 0
            );

            $width      = trim($fmatch[2]) ? (int)$fmatch[2] : 0;

            $left       = trim($fmatch[3]) ? (int)$fmatch[3] : 0;

            $right      = trim($fmatch[4]) ? (int)$fmatch[4] : $locale['int_frac_digits'];

            $conversion = $fmatch[5];

            $positive = true;

            if ($value < 0) {

                $positive = false;

                $value  *= -1;

            }

            $letter = $positive ? 'p' : 'n';

            $prefix = $suffix = $cprefix = $csuffix = $signal = '';

            $signal = $positive ? $locale['positive_sign'] : $locale['negative_sign'];

            switch (true) {
                case $locale["{$letter}_sign_posn"] == 1 && $flags['usesignal'] == '+':
                    $prefix = $signal;
                    break;

                case $locale["{$letter}_sign_posn"] == 2 && $flags['usesignal'] == '+':
                    $suffix = $signal;
                    break;

                case $locale["{$letter}_sign_posn"] == 3 && $flags['usesignal'] == '+':
                    $cprefix = $signal;
                    break;

                case $locale["{$letter}_sign_posn"] == 4 && $flags['usesignal'] == '+':
                    $csuffix = $signal;
                    break;

                case $flags['usesignal'] == '(':
                case $locale["{$letter}_sign_posn"] == 0:
                    $prefix = '(';
                    $suffix = ')';
                    break;

            }

            if (!$flags['nosimbol']) {

                $currency = $cprefix . ($conversion == 'i' ? $locale['int_curr_symbol'] : $locale['currency_symbol']) . $csuffix;

            } else {

                $currency = '';

            }

            $space  = $locale["{$letter}_sep_by_space"] ? ' ' : '';

            $value = number_format($value, $right, $locale['mon_decimal_point'], $flags['nogroup'] ? '' : $locale['mon_thousands_sep']);

            $value = @explode($locale['mon_decimal_point'], $value);

            $n = strlen($prefix) + strlen($currency) + strlen($value[0]);

            if ($left > 0 && $left > $n)
                $value[0] = str_repeat($flags['fillchar'], $left - $n) . $value[0];

            $value = implode($locale['mon_decimal_point'], $value);

            if ($locale["{$letter}_cs_precedes"])
                $value = $prefix . $currency . $space . $value . $suffix;
            else
                $value = $prefix . $value . $space . $currency . $suffix;

            if ($width > 0)
                $value = str_pad($value, $width, $flags['fillchar'], $flags['isleft'] ? STR_PAD_RIGHT : STR_PAD_LEFT);

            $format = str_replace($fmatch[0], $value, $format);

        }

        return $format;

    }

}

if (!function_exists('str_putcsv')) {

    /**
     * Convert an array into a CSV line.
     *
     * @param array $input
     *
     * @param string $delimiter Defaults to comma (,)
     *
     * @param string $enclosure Defaults to double quote (")
     *
     * @return string
     */
    function str_putcsv($input, $delimiter = ',', $enclosure = '"', $escape_char = "\\") {

        $fp = fopen('php://temp', 'r+b');

        fputcsv($fp, $input, $delimiter, $enclosure, $escape_char);

        rewind($fp);

        $data = rtrim(stream_get_contents($fp), "\n");

        fclose($fp);

        return $data;

    }

}

/**
 * Replaces elements from passed arrays or objects into the first array or object recursively
 *
 * NOTE: This function is almost identical to the PHP function array_replace_recursive() except that it
 * also works with stdClass objects.
 *
 * replace_recursive() replaces the values of item1 with the same values from all the following
 * items. If a key from the first item exists in the second item, its value will be replaced by
 * the value from the second item. If the key exists in the second item, and not the first, it will
 * be created in the first item. If a key only exists in the first item, it will be left as is. If
 * several items are passed for replacement, they will be processed in order, the later item overwriting
 * the previous values.
 *
 * replace_recursive() is recursive : it will recurse into item and apply the same process to the inner value.
 *
 * When the value in item1 is scalar, it will be replaced by the value in item2, may it be scalar, array
 * or stdClass. When the value in item1 and item2 are both arrays or objects, replace_recursive() will replace
 * their respective value recursively.
 *
 * @since 2.3.41
 * @return mixed
 */
function replace_recursive(){

    $items = func_get_args();

    $target = array_shift($items);

    foreach($items as $item){

        if(!((is_array($item) && count($item) > 0)
            || ($item instanceof stdClass && count(get_object_vars($item))) > 0))
            continue;

        foreach($item as $key => $value){

            if(is_array($target)){

                if(array_key_exists($key, $target)
                    && gettype($target[$key]) == gettype($value)
                    && (is_array($target[$key]) || $target[$key] instanceof stdClass))
                    $target[$key] = replace_recursive(ake($target, $key), $value);
                else
                    $target[$key] = $value;

            }elseif($target instanceof stdClass){

                if(property_exists($target, $key)
                    && gettype($target->$key) == gettype($value)
                    && (is_array(ake($target, $key)) || ake($target, $key) instanceof stdClass))
                    $target->$key = replace_recursive($target->$key, $value);
                else
                    $target->$key = $value;

            }

        }

    }

    return $target;

}

/**
 * Recrusively convert a traversable object into a normal array
 *
 * This is the same as the built-in PHP iterator_to_array() function except it will recurse into any \Traversable objects it contains.
 *
 * @param Traversable $it The object to convert to an array.
 *
 * @return array
 *
 * @since 2.3.60
 */
function recursive_iterator_to_array(\Traversable $it) {

    $result = array();

    foreach($it as $key => $value) {

        if($value instanceof \Traversable)
            $result[$key] = recursive_iterator_to_array($value);
        else
            $result[$key] = $value;

    }

    return $result;

}

/**
 * Recursivly computes the difference of arrays with additional index check
 *
 * Compares `array1` against `array2` and returns the difference. Unlike array_diff() the array keys are also used
 * in the comparison.  Also, unlike the PHP array_diff_assoc() function, this function recurse into child arrays.
 *
 * @param array $array1 The array to compare from.
 *
 * @param array $array2 The array to compare against.
 *
 * @param array ... More arrays to compare against.
 *
 * @return array
 *
 * @author Diego Dias <diego.dias@apir.com.au>
 *
 * @since 2.4.1
 */
function array_diff_assoc_recursive() {

    $arrays = func_get_args();

    $array1 = array_shift($arrays);

    $diff = array();

    foreach($array1 as $key => $value) {

        foreach($arrays as $array_compare){

            //Check if the value exists in the compare array and if not, check the next array
            if((is_array($array_compare) && !array_key_exists($key, $array_compare))
                || ($array_compare instanceof \stdClass && !property_exists($array_compare, $key)))
                continue;

            if(!(is_array($value) || $value instanceof \stdClass) && $value !== ake($array_compare, $key))
                continue;

            if(is_array($value) || $value instanceof \stdClass){

                $compare_value = ake($array_compare, $key);

                if(!(is_array($compare_value) || $compare_value instanceof \stdClass))
                    break;

                $child_diff = array_diff_assoc_recursive($value, $compare_value);

                if(!empty($child_diff)){

                    $value = $child_diff;

                    break;

                }

            }

            continue 2;

        }

        $diff[$key] = $value;

    }

    return $diff;

}

/**
 * Recursively convert an object into an array.
 *
 * This is basically a recursive version of PHP's get_object_vars().
 *
 * @param object $object The object to convert.
 * @return array|boolean Returns the converted object as an array or false on failure.
 */
function object_to_array($object){

    if(!is_object($object))
        return false;

    $array = get_object_vars($object);

    foreach($array as &$value) {

        if(is_object($value))
            $value = object_to_array($value);

    }

    return $array;

}

/**
 * Recursively convert an array into an object.
 *
 * This is the inverse of object_to_array().
 *
 * @param array $object The array to convert.
 * @return object|boolean Returns the converted array as a \stdClass object or false on failure.
 */
function array_to_object($array){

    if(!is_array($array))
        return false;

    $object = new \stdClass;

    foreach($array as $key => $value)
        $object->{$key} = is_array($value) ? array_to_object($value) : $value;

    return $object;

}

/**
 * Searches the array using a callback function and returns the first corresponding key if successful.
 *
 * @param mixed $haystack The array.
 * @param callable $callback The callback function to use.  This function should return true if the value matches.
 * @return mixed
 */
function array_usearch($haystack, callable $callback){

    foreach($haystack as $key => $value){

        if($callback($value, $key) === true)
            return $key;

    }

    return false;
}

/**
 * Checks if a value exists in an array using a callback function.
 *
 * @param mixed $haystack The array.
 * @param callable $callback The callback function to use.  This function should return true if the value matches.
 * @return boolean True if the value is found in the array, false otherwise.
 */
function in_uarray($haystack, callable $callback){

    return array_usearch($haystack, $callback) !== false;

}

/**
 * Recursively remove all empty values from an array
 *
 * Removes all values from an array that are considered empty.  This includes null values, empty strings and empty arrays.
 *
 * Unlike PHP's `empty()` function, this DOES NOT include 0, 0.0, "0" or false.
 *
 * @param mixed $array
 * @return mixed
 */
function array_remove_empty(&$array){

    foreach ($array as $key => &$value) {

        if (is_array($value))
            array_remove_empty($value);

        if ($value === null
            || (is_string($value) && trim($value) === '')
            || (is_array($value) && count($value) === 0))
            unset($array[$key]);

    }

    return $array;

}

/**
 * Generate a truly random string of characters.
 *
 * @param mixed $length             The length of the random string being created.
 * @param mixed $include_special    Whether or not special characters.  Normally only Aa-Zz, 0-9 are used.  If TRUE will include
 *                                  characters such as #, $, etc.  This can also be a string of characters to use.  
 *
 * @return string A totally random string of characters.
 */
function str_random($length, $include_special = false){

    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';

    if($include_special === true)
        $characters .= ' ~!@#$%^&*()-_=+[{]}\|;:\'",<.>/?';
    elseif(is_string($include_special))
        $characters .= $include_special;

    $count = strlen($characters);

    $randomString = '';

    for ($i = 0; $i < $length; $i++)
        $randomString .= $characters[rand(0, $count - 1)];

    return $randomString;

}

/**
 * Pull an item out of an array by is key
 *
 * This function is similar to array_pop() and array_shift(), except that instead of popping the last/first element off the
 * array, it pops an element with the specified key.
 *
 * @param array $array The array to pull the element from.
 * @param int|string $key The key of the element.
 *
 * @return mixed The element returned from the array.
 */
function array_pull(&$array, $key){

    if(!array_key_exists($key, $array))
        return null;

    $item = $array[$key];

    unset($array[$key]);

    return $item;

}

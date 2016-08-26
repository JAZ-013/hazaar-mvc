<?php
/**
 * @file        Hazaar/Money.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */

namespace Hazaar;

/**
 * @brief       Money class
 *
 * @detail      This class is used to extend a normal integer value by adding currency related features such as the
 *              currency type (AUD, USD, JPY, etc) and realtime currency conversion using Yahoo Quotes.
 *
 *              h3. Example
 *
 *              <code class="php">
 *              $aud = new Money(500, 'AUD');
 *              $usd = new Money(200, 'USD');
 *              $total = $aud->add($usd);
 *              </code>
 *
 *              The default money format is '%.2n' which will format the value to whole dollar with 2 decimal places. ie:
 *              $123.45. You can specify the format when retrieving the amount (see Money::format()) or you can set the
 *              default format at any time.
 *
 *              You can also set the default currency code to use when none is specified.
 *
 *              It is recommended that these be set in your bootstrap file so that they are consistent across the whole
 *              application.
 *
 *              h3. Example bootstrap.php
 *
 *              <code class="php">
 *              Hazaar\Money::$money_format = '%.0n';
 *              Hazaar\Money::$default_currency = 'AUD';
 *              </code>
 */
class Money {

    /**
     * Default money format
     */
    static public $money_format = '%!.2n';

    /**
     * Default currency code
     */
    static public $default_currency = NULL;

    /**
     * @private
     */
    static private $currency_info  = array();

    private        $value;

    private        $local_currency = NULL;

    private        $cache;

    /**
     * @detail      The money class constructors takes two parameters.  The value of the currency and the type of
     *              currency the value is representative of.
     *
     *              Currency info is loaded from a built-in support file named countryInfo.txt.  This file contains
     *              country information including country codes, currency names, etc.  The first time a currency is
     *              used this information is loaded into memory only once and is shared between all currency objects.
     *
     *              A cache object is also set up for use by the exchange conversion methods.  It will attempt to use
     *              the APC cache backend but if that is not available it will fall back to the file backend.
     *
     * @param       float $value The currency value amount
     *
     * @param       string $currency The name of the currency or country of origin.  Ie: 'USD' and 'US' will both resolve
     *              to US dollars.
     */
    function __construct($value, $currency = NULL) {

        $this->value = $value;

        $backend = (in_array('apc', get_loaded_extensions()) ? 'apc' : 'file');

        $this->cache = new Cache($backend);

        if(! Money::$currency_info && (Money::$currency_info = $this->cache->get('currency_info')) == FALSE) {

            if($info = Loader::getFilePath(FILE_PATH_SUPPORT, 'currencyInfo.csv')) {

                $h = fopen($info, 'r');

                if($h) {

                    $lines = array();

                    $headers = FALSE;

                    while($line = fgetcsv($h, 0, ',')) {

                        if($headers == FALSE) {

                            $headers = $line;

                            continue;

                        }

                        if(substr($line[0], 0, 1) == '#')
                            continue;

                        $infoline = array();

                        foreach($headers as $index => $key) {

                            $infoline[$key] = $line[$index];

                        };

                        $lines[$line[3]] = $infoline;

                    }

                    fclose($h);

                    $this->cache->set('currency_info', $lines);

                    Money::$currency_info = $lines;

                }

            }

        }

        $this->local_currency = $this->getCurrencyCode($currency);

    }

    /**
     * @detail      Get either the default currency code, or get a currency code for a country.  Use this instead of
     *              accessing Money::default_currency directly because if Money::$default_currency is not set, this will
     *              try and determine the default currency and set it automatically which will occur when a new Money
     *              object is created.
     *
     * @since       1.2
     *
     * @param       string $currency Either the country code or null to get the default currency.  Can also be a valid
     *              currency which will simply be passed through.
     *
     * @return      string A valid currency code such as AUD, USD, JPY, etc.
     */
    public function getCurrencyCode($code = NULL) {

        /**
         * If there is no currency set, get the default currency
         */
        if(! $code) {

            $code = Money::$default_currency;

        }

        /**
         * If there is no default currency, look it up and set it now
         */
        if(! $code) {

            if(preg_match('/^\w\w_(\w\w)/', setlocale(LC_MONETARY, '0'), $matches)) {

                $code = $matches[1];

            } else {

                /**
                 * The absolute fallback default is AUD
                 */
                $code = 'AUD';

            }

            Money::$default_currency = $code;

        }

        if(strlen($code) == 2)
            $code = $this->getCode($code);

        return strtoupper($code);

    }

    /**
     * @detail      Get the currency code for the current currency object or look up the currency code for a country.
     *              This value is normalised during object instantiation.  This means that if you specify a country upon
     *              instantiation, this will still return the correct currency code.
     *
     *              Optionally, if a country parameter is specified, this method can be used to look up the currency code
     *              for that country.
     *
     *              h3. Example
     *
     *              <code class="php">
     *              echo $currency->getCode('au');  //This will echo the string 'AUD'.
     *              </code>
     *
     * @param       string $country Optional country code to look up a currency code for.
     *
     * @return      string The currency code requested.
     */
    public function getCode($country = NULL) {

        if($country) {

            foreach(Money::$currency_info as $info) {

                if($info['iso'] == $country)
                    return $info['currencycode'];

            }

        }

        return $this->local_currency;

    }

    /**
     * @detail      Get the symbol for the current currency.  The currency symbol is usually prefixed to the currency
     *              amount.  This method doesn't actually return the currency symbol as such, but will return the HTML
     *              entity name of the currency symbol, for example 'dollar', 'pound', 'yen', etc.
     *
     * @return      string The HTML entity name of the currency symbol.
     */
    public function getCurrencySymbol() {

        $local = $this->getCode();

        if(! ($info = Money::$currency_info[$local])) {

            return '$';

        }

        return $info['symbol'];

    }

    /**
     * @detail      Get the current exchange rate for the currency value against a foreign currency.  This method uses
     *              the Yahoo Quotes service to get the current exchange rate.  For this method to work your host needs
     *              to have web access (ie: port 80).  This should 'just work' for all but a small number of cases.
     *
     *              Because this method contacts another web service the response can be a little slow.  Because of this
     *              results are cached so that subsequent requests for the same conversion will be faster.
     *
     * @param       string $foreign_currency The foreign currency to get an exchange rate for.
     *
     * @return      float The current currency exchange rate.
     */
    public function getExchangeRate($foreign_currency) {

        if(strlen($foreign_currency) == 2)
            $foreign_currency = $this->getCode($foreign_currency);

        if(strcasecmp($this->local_currency, $foreign_currency) == 0) {

            return 1;

        }

        $code = strtoupper(trim($this->local_currency) . trim($foreign_currency));

        if(($rate = $this->cache->get('exchange_rate_' . $code)) == FALSE) {

            $url = 'http://finance.yahoo.com/d/quotes.csv?f=l1&s=' . $code . '=X';

            $handle = fopen($url, 'r');

            if($handle) {

                $result = fgetcsv($handle);

                fclose($handle);

            }

            $rate = (float)$result[0];

            $this->cache->get('exchange_rate_' . $code, $rate);

        }

        return $rate;

    }

    /**
     * @detail      Convert the currency object to another currency and return a new Money object.
     *
     * @param       string $foreign_currency The currency to convert to.  Can be country or currency code.
     *
     * @return      Money A new currency object with the value of the foreign currency amount.
     */
    public function convertTo($foreign_currency) {

        $foreign = new Money($this->value * $this->getExchangeRate($foreign_currency), $foreign_currency);

        return $foreign;

    }

    /**
     * @detail      The format method will format the currency value amount to an international standard format of
     *              {symbol}{amount}{code}.  For example, US dollars will be expressed as $100USD.  Australian
     *              dollar as $105AUD and so on.
     *
     * @param       string $format An optional format passed to the money_format function.  If not specified the global
     *              default format will be used.
     *
     * @return      string The currency value as a formatted string
     */
    public function format($format = NULL) {

        if(! $format)
            $format = Money::$money_format;

        $symbol = $this->getCurrencySymbol();

        return $symbol . money_format($format, $this->value) . $this->getCode();

    }

    /**
     * @detail      This magic function is called when PHP tries to automatically convert the currency to a string.
     *              Simply calls the Money::toString() method.
     *
     * @return      string eg: $100USD
     */
    public function __tostring() {

        return $this->toString();

    }

    /**
     * @detail      Convert currency to a string.  Outputs the same as the Money::format() method using the default
     *              format.
     *
     * @return      string eg: $100USD
     */
    public function toString() {

        return $this->format();

    }

    /**
     * @detail      Get the currency as a float value formatted using the money_format PHP function with optional
     *              precision to specify the number of decimal places.
     *
     * @param       int $precision The number of decimal places to round to.  Defaults to 2.
     *
     * @return      float The currency value as a float
     */
    public function toFloat($precision = 2) {

        return (float)money_format('%.' . $precision . 'n', $this->value);

    }

    /**
     * @detail      Get the currency value represented as an integer in cents.  1 dollar = 100 cents.
     *
     * @return      int The currency value in whole cents.
     */
    public function toCents() {

        return (int)round($this->value * 100);

    }

    /**
     * @detail      Add one or more amounts or Money objects to the current currency.  Parameters here can be either
     *              a numeric value or another Money object.  If the parameter is a Money object then the value
     *              will be automatically converted using the current exchange rate before it is added.
     *
     *              h3. Parameters
     *
     *              Any number of numeric or Money objects parameters.
     *
     * @return      Money The sum of all values as a new Money object.
     */
    public function add() {

        $total = $this->value;

        foreach(func_get_args() as $arg) {

            if(is_numeric($arg)) {

                $total += $arg;

            } elseif($arg instanceof Money) {

                $total += $arg->convertTo($this->local_currency)->toFloat();

            }

        }

        return new Money($total, $this->local_currency);

    }

    /**
     * @detail      Subtract one or more values or Money objects from the current object.  Parameters can be either
     *              a numeric value or another Money object.  If the parameter is a Money object then the value
     *              will be automatically converted using the current exchange rate before it is subtracted.
     *
     * @return      Money The result of subtraction as a new Money object.
     */
    public function subtract() {

        $total = $this->value;

        foreach(func_get_args() as $arg) {

            if(is_numeric($arg)) {

                $total -= $arg;

            } elseif($arg instanceof Money) {

                $total -= $arg->convertTo($this->local_currency)->toFloat();

            }

        }

        return new Money($total, $this->local_currency);

    }

}

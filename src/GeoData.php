<?php

namespace Hazaar;

/**
 * The GeoData class for accessing geographic information on countries.
 *
 * This method allows access to useful country information such as codes, names, continents,
 * states, cities and various other data.
 *
 * Data is obtained via the publicly available GeoLite2 databases provided by MaxMind.
 *
 * See the [MaxMind GeoLite2 Pages](https://dev.maxmind.com/geoip/geoip2/geolite2/) for more information.
 *
 * <p class="notice">Currently, IP information is not stored and only country/state/city level data is
 * searchable.</p>
 *
 * <p class="notice warning">The first time the \Hazaar\GeoData class is used it needs to download some files
 * and construct an internal B-Tree database.  This requires HTTP access to the internet and depending
 * on the speed of the connection can take some time (usually around 10-15 seconds).  Once the B-Tree
 * database is constructed then data access speeds are extremely fast.</p>
 *
 * @since 2.3.44
 *
 * @author Jamie Carl <jamie@hazaarlabs.com>
 */
class GeoData {

    /**
     * The publicly available GeoData database data sources.
     * @var array
     */
    static private $sources = array(
        'url' => 'https://api.hazaarmvc.com/databases/geodata.zip',
        'md5' => 'https://api.hazaarmvc.com/databases/geodata.zip.md5'
    );

    /**
     * The current GeoData database format version.
     *
     * Changing this triggers a re-initialisation of the internal database.
     *
     * @var int
     */
    static private $version = 2;

    /**
     * The internal B-Tree database adapter
     *
     * @var \Hazaar\Btree
     */
    static private $db;

    function __construct($re_intialise = false){

        $file = new \Hazaar\File(\Hazaar\Application::getInstance()->runtimePath('geodata.db'));

        GeoData::$db = new \Hazaar\Btree($file);

        if($re_intialise === true || GeoData::$db->get('__version__') !== GeoData::$version)
            $this->__initialise();

    }

    /**
     * Initialises the internal B-Tree database with all available data
     * @throws \Exception
     * @return boolean
     */
    private function __initialise(){

        $data = array();

        GeoData::$db->reset_btree_file();

        $tmpdir = new \Hazaar\File\Dir(Application::getInstance()->runtimePath());

        $geodata_file = $tmpdir->get(basename(GeoData::$sources['url']));

        /*
         * Download the Hazaar GeoData file and check it's MD5 signature
         */
        $geodata_file->put_contents(file_get_contents(GeoData::$sources['url']));

        if(!$geodata_file->size() > 0)
            throw new \Hazaar\Exception('Unable to download GeoData source file!');

        $md5 = trim(file_get_contents(GeoData::$sources['md5']));

        if($geodata_file->md5() !== $md5)
            throw new \Hazaar\Exception('GeoData source file MD5 signature does not match!');

        $files = $geodata_file->unzip('geodata.db', $tmpdir);

        $geodata_file->unlink(); //Cleanup now

        return count($files) === 1;
    }

    /**
     * Obtains a list of all countries indexed by code
     *
     * @param mixed $db
     * @param mixed $field
     * @return array
     */
    private function __list(\Hazaar\Btree $db, $field = null){

        $list = array();

        $codes = $db->range("\x00", "\xff");

        foreach($codes as $code => $info){

            if(substr($code, 0, 2) == '__')
                continue;

            $list[$code] = $field ? ake($info, $field) : $info;

        }

        asort($list);

        return $list;

    }

    /**
     * Retrieve a list of countries
     *
     * This method will return an associative array containing a list of all countries organised by their
     * two character ISO code.  The ISO code is the key and the country name is the value.
     *
     * @return array
     */
    public function countries(){

        return $this->__list(GeoData::$db, 'name');

    }

    /**
     * Retrieve information about a country by it's ISO code.
     *
     * This method will return an array that contains:
     * - id := The GeoNamesID
     * - code := Two character ISO country code
     * - name := Country name
     * - continent := Continent info containing the continent ISO code and name.
     * - phone_code := Two digit telephone dialing code (E164)
     * - languages := Array of languages used in this country
     * - capital := Name of the capital city
     * - capital_timezone := The timezone in the capital city
     * - area := Physical area in KM/2
     * - hosts := Estimated number of active internet hosts
     * - users := Estimated number of active internet users
     *
     * @param string $code The two character ISO country code to get information for.
     *
     * @return array An array of available country information.
     */
    public function country_info($code){

        $info = GeoData::$db->get(strtoupper($code));

        unset($info['states']);

        unset($info['cities']);

        return $info;

    }

    public function country_info_all(){

        return $this->__list(GeoData::$db);

    }

    /**
     * Retrieve a list of states for a country
     *
     * Using a two character ISO country code, this method will return a list of states for that country.
     *
     * @param string $country_code Two character ISO country code
     *
     * @return array An array of states with their state code as the key and full name as the value.
     */
    public function states($country_code){

        $list = array();

        if($country = GeoData::$db->get(strtoupper($country_code))){

            foreach(ake($country, 'states') as $code => $state)
                $list[$code] = $state['name'];

            asort($list);

        }

        return $list;

    }

    /**
     * Retrieve a list of cities for the requested country and state
     *
     * Using a two character ISO country code and the state code this method will return an
     * array of cities in that state.
     *
     * @param string $country_code Two character ISO country code.
     * @param string $state_code State code.
     * @return array An array of city names.
     */
    public function cities($country_code, $state_code){

        $list = array();

        if($country = GeoData::$db->get(strtoupper($country_code))){

            $cities = ake(ake(ake($country, 'states'), $state_code), 'cities', array());

            foreach($cities as $id){

                if(!($city = $country['cities'][$id]))
                    continue;

                $list[] = $city['name'];

            }

            sort($list);

        }

        return $list;

    }

    /**
     * Quick access method to retrieve a country name.
     *
     * @param string $country_code A two character ISO country code.
     * @return string
     */
    public function country_name($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'name');

    }

    /**
     * Quick access method to find a country code using the name of the country.
     *
     * @param string $name The full name of the country to get the ISO code for.
     * @return string
     */
    public function country_code($name){

        $info = GeoData::$db->range("\x00", "\xff");

        foreach($info as $country){

            if(strcasecmp($country['name'], $name) == 0)
                return $country['code'];

        }

        return false;

    }

    /**
     * Quick access method to retrieve country continent info.
     *
     * @param string $country_code Two character ISO country code.
     * @return array
     */
    public function country_continent($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'continent');

    }

    /**
     * Quick access method to retrieve country language info.
     *
     * @param string $country_code Two character ISO country code.
     * @return array
     */
    public function country_languages($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'languages');

    }

    /**
     * Quick access method to retrieve country phone dialling code.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_phone_code($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'phone_code');

    }

    /**
     * Quick access method to retrieve country capital name.
     *
     * @param string $country_code Two character ISO country code.
     * @return string
     */
    public function country_capital($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'capital');

    }

    /**
     * Quick access method to retrieve country capital timezone.
     *
     * @param string $country_code Two character ISO country code.
     * @return string
     */
    public function country_capital_timezone($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'capital_timezone');

    }

    /**
     * Quick access method to retrieve country area in square kilometers.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_area($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'area');

    }

    /**
     * Quick access method to retrieve country estimated internet hosts.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_hosts($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'hosts');

    }

    /**
     * Quick access method to retrieve country estimated internet users.
     *
     * @param string $country_code Two character ISO country code.
     * @return int
     */
    public function country_users($country_code){

        $info = $this->country_info($country_code);

        return ake($info, 'users');

    }

}

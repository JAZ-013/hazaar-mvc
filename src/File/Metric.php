<?php

namespace Hazaar\File;

define('METRIC_FLOAT_LEN', strlen(pack('f', 0)));

define('METRIC_HDR_LEN', 8);

define('METRIC_DSDEF_LEN', 12 + (2 * METRIC_FLOAT_LEN));

define('METRIC_ARCHIVE_HDR_LEN', 18);  //plus the value of ID length

define('METRIC_PDP_HDR_LEN', 6);

define('METRIC_PDP_ROW_LEN', METRIC_PDP_HDR_LEN + METRIC_FLOAT_LEN);

define('METRIC_CDP_HDR_LEN', 6);

define('METRIC_TYPE_HDR', 0xA1);   //Header

define('METRIC_TYPE_DS', 0xA2);    //Data Source

define('METRIC_TYPE_AD', 0xA3);    //Archive Definition

define('METRIC_TYPE_PDP', 0xA4);   //Primary Data Point

define('METRIC_TYPE_CDP', 0xA5);   //Consolidated Data Point

class Metric {

    private $file;

    private $h;

    private $version = 1;

    private $tick_sec = 0;

    private $data_sources = array();

    private $archives = array();

    private $lastTick = array('data' => array(), 'archive' => array());

    private $dataSourceTypes = array(
        'GAUGE'    => 0x01,
        'COUNTER'  => 0x02,
        'ABSOLUTE' => 0x03,
        'GAUGEZ'   => 0x04
    );

    private $archiveCFs      = array(
        'AVERAGE' => 0x01,
        'MIN'     => 0x02,
        'MAX'     => 0x03,
        'LAST'    => 0x04,
        'COUNT'   => 0x05
    );

    public function __construct($file) {

        $this->file = $file;

        if($this->exists()){

            $this->h = fopen($file, 'c+');

            $this->restoreOptions();

            $this->update();

        }

    }

    public function __destruct(){

        if(is_resource($this->h))
            fclose($this->h);

    }

    public function exists() {

        return (file_exists($this->file) && filesize($this->file) > 0);

    }

    /**
     * addDataSource('speed', 'COUNTER', 600, 'U', 'U');
     *
     * @param mixed $dsname         //The name of the data source
     * @param mixed $type           //Data source types are: GAUGE, COUNTER, ABSOLUTE
     * @param mixed $min            //Minimum allowed value
     * @param mixed $max            //Maximum allowed value
     * @param mixed $description    //String describing the data source
     *
     * @return boolean
     */
    public function addDataSource($dsname, $type, $min = NULL, $max = NULL, $description = NULL) {

        $type = strtoupper($type);

        if(! array_key_exists($type, $this->dataSourceTypes))
            return false;

        $this->data_sources[$dsname] = array(
            'name'      => $dsname,
            'desc'      => $description,
            'type'      => $this->dataSourceTypes[$type],
            'ticks'     => 0,
            'min'       => $min,
            'max'       => $max,
            'last'      => -1
        );

        return true;

    }

    /**
     * addArchive('day_average', 'AVERAGE', 60, 24);
     *
     * @param mixed $archive_id     Name of the archive
     * @param mixed $cf             Consolidation function and can be: AVERAGE, MIN, MAX or LAST
     * @param mixed $ticks          Number of ticks to consolidate into a row
     * @param mixed $rows           Number of rows to store in the archive
     * @param mixed $description    A string describing the archive
     * @return boolean
     */
    public function addArchive($archive_id, $cf, $ticks = NULL, $rows = NULL, $description = NULL) {

        if(!$archive_id)
            return false;

        $cf = strtoupper($cf);

        if(!array_key_exists($cf, $this->archiveCFs))
            return false;

        $this->archives[$archive_id] = array(
            'id'    => $archive_id,
            'desc'  => $description,
            'cf'    => $this->archiveCFs[$cf],      //Consolidation function
            'ticks' => $ticks,                      //Number of ticks to consolidate into a row
            'rows'  => $rows,                       //Number of rows to store in the archive
            'last'  => -1                           //Pointer to the current row
        );

        return true;

    }

    /**
     * Calculate the length of a row within an archive
     *
     * @return integer
     */
    private function getCDPLength() {

        return METRIC_CDP_HDR_LEN + (count($this->data_sources) * METRIC_FLOAT_LEN);

    }

    /**
     * Calculate the start position in the file of a data source
     *
     * @param mixed $dsname The name of the data source.  If omitted, returns the position of the byte after all data sources
     *
     * @return integer
     */
    private function getDataSourceOffset($dsname = null) {

        $offset = METRIC_HDR_LEN;

        //Get the length of all names and descriptions
        foreach($this->data_sources as $ds){

            if($ds['name'] === $dsname)
                break;

            $offset += METRIC_DSDEF_LEN + strlen($ds['name'] . $ds['desc']) + ($ds['ticks'] * METRIC_PDP_ROW_LEN);

        }

        return $offset;

    }

    /**
     * Calculate the start position in the file of an archive
     *
     * @param mixed $archive_id The name of the archive
     *
     * @return integer
     */
    private function getArchiveOffset($archive_id = 0) {

        $row_length = $this->getCDPLength();

        $offset = METRIC_HDR_LEN;

        foreach($this->data_sources as $ds)
            $offset += METRIC_DSDEF_LEN + strlen($ds['name'] . $ds['desc']) + ($ds['ticks'] * METRIC_PDP_ROW_LEN);

        foreach($this->archives as $id => $archive) {

            if($archive_id == $id)
                break;

            $offset += METRIC_ARCHIVE_HDR_LEN + ($row_length * $archive['rows']) + strlen($archive['id'] . $archive['desc']);

        }

        return $offset;

    }

    /**
     * Get a tick value
     *
     * @param mixed $time Defaults to the current time if not specified
     *
     * @return integer|boolean
     */
    public function getTick($time = NULL) {

        if($time === NULL)
            $time = time();

        elseif(! is_numeric($time))
            $time = strtotime($time);

        return intval(floor($time / $this->tick_sec));

    }

    /**
     * Load data sources and archives from an existing RRD database file.
     *
     * @throws \Exception
     *
     * @return boolean
     */
    private function restoreOptions() {

        if(!is_resource($this->h))
            return false;

        while($type = fread($this->h, 2)) {

            switch(ord($type)) {
                case METRIC_TYPE_HDR: //Header

                    $bytes = fread($this->h, METRIC_HDR_LEN - 2);

                    $header = unpack('vversion/Vticksec', $bytes);

                    if(intval($header['version']) != $this->version)
                        throw new \Exception('RRD file version error.  File is version ' . $header['version'] . ' but RRD is version ' . $this->version);

                    $this->tick_sec = $header['ticksec'];

                    break;

                case METRIC_TYPE_DS: //DataSource

                    $header = unpack('vtype/Clen', fread($this->h, 3));

                    $name = fread($this->h, $header['len']);

                    $body = unpack('Clen', fread($this->h, 1));

                    $desc = (($body['len'] > 0) ? fread($this->h, $body['len']) : NULL);

                    $foot = unpack('vticks/fmin/fmax/llast', fread($this->h, 6 + (2 * METRIC_FLOAT_LEN)));

                    $ds = array(
                        'name'      => $name,
                        'desc'      => $desc,
                        'type'      => $header['type'],
                        'ticks'     => $foot['ticks'],
                        'min'       => ($foot['min'] == -1) ? NULL : $foot['min'],
                        'max'       => ($foot['max'] == -1) ? NULL : $foot['max'],
                        'last'      => $foot['last']
                    );

                    $this->data_sources[$name] = $ds;

                    $start = ftell($this->h);

                    if($ds['last'] < 0){

                        $this->lastTick['data'][$ds['name']] = $this->getTick() - 1;

                    }else{

                        //Skip to the current PDP row
                        $offset = $start + $ds['last'] * METRIC_PDP_ROW_LEN;

                        fseek($this->h, $offset);

                        $pdp = unpack('vtype/Vtick/fvalue', fread($this->h, METRIC_PDP_ROW_LEN));

                        $this->lastTick['data'][$ds['name']] = $pdp['tick'];

                    }

                    //Skip over the primary data points
                    fseek($this->h, $start + ($ds['ticks'] * METRIC_PDP_ROW_LEN));

                    break;

                case  METRIC_TYPE_AD: //Archive definition

                    $archive = array();

                    $header = unpack('Clen', fread($this->h, 1));

                    $archive['id'] = fread($this->h, $header['len']);

                    $body = unpack('Clen', fread($this->h, 1));

                    $archive['desc'] = fread($this->h, $body['len']);

                    $archive = array_merge($archive, unpack('vcf/Vticks/Vrows/llast', fread($this->h, 14)));

                    $this->archives[$archive['id']] = $archive;

                    $start = ftell($this->h);

                    $len = $this->getCDPLength();

                    if($archive['last'] < 0) {

                        $this->lastTick['archive'][$archive['id']] = $this->getTick() - $archive['ticks'] - 1;

                    } else {

                        //Skip to the current archive row
                        $offset = $len * $archive['last'];

                        fseek($this->h, $offset, SEEK_CUR);

                        $row = unpack('vtype/Vtick', fread($this->h, $len));

                        $this->lastTick['archive'][$archive['id']] = $row['tick'];

                    }

                    //Skip the rest of the archive
                    fseek($this->h, $start + ($len * $archive['rows']));

                    break;

                case METRIC_TYPE_PDP: //PDP (Primary Data Point)
                case METRIC_TYPE_CDP: //CDP (Consolidated Data Point)

                    die('THIS SHOULD NOT HAPPEN AT FILE POSITION ' . ftell($this->h) . "\n");

                default:

                    die('Unexpected block type! TYPE=' . dechex(ord($type)) . "\n");

            }

        }

        return true;

    }

    private function writeDataSource(&$ds) {

        //Calculate how many primary data points we need
        foreach($this->archives as $archive){

            if($archive['ticks'] > $ds['ticks'])
                $ds['ticks'] = $archive['ticks'];

        }

        $line = pack('vvC', METRIC_TYPE_DS, $ds['type'], strlen($ds['name'])) . $ds['name'];

        $line .= pack('C', strlen($ds['desc'])) . $ds['desc'];

        $min = ($ds['min'] === NULL) ? -1 : $ds['min'];

        $max = ($ds['max'] === NULL) ? -1 : $ds['max'];

        $line .= pack('vffl', $ds['ticks'], $min, $max, $ds['last']);

        $len = strlen($line) - strlen($ds['name']) - strlen($ds['desc']);

        if($len != METRIC_DSDEF_LEN)
            die('dataSource header length is not METRIC_DSDEF_LEN(' . METRIC_DSDEF_LEN . ") LENGTH=$len\n");

        if(fwrite($this->h, $line) !== strlen($line))
            return false;

        $pdp = str_repeat(pack('vVf', METRIC_TYPE_PDP, 0, 0), $ds['ticks']);

        return fwrite($this->h, $pdp) === strlen($pdp);

    }

    private function writeArchive(&$archive) {

        $data = pack('vC', METRIC_TYPE_AD, strlen($archive['id'])) . $archive['id'];

        $data .= pack('C', strlen($archive['desc'])) . $archive['desc'];

        $data .= pack('vVVl', $archive['cf'], $archive['ticks'], $archive['rows'], $archive['last']);

        $len = strlen($data) - strlen($archive['id']) - strlen($archive['desc']);

        if($len != METRIC_ARCHIVE_HDR_LEN)
            die('archive header length is not METRIC_ARCHIVE_HDR_LEN(' . METRIC_ARCHIVE_HDR_LEN . ") LENGTH=$len\n");

        for($i = 0; $i < $archive['rows']; $i++)
            $data .= $this->writeCDP(0, array_fill(0, count($this->data_sources), 0), false);

        return fwrite($this->h, $data) === strlen($data);

    }

    private function writeCDP($tick, $values, $do_write = true) {

        $row = pack('vV', METRIC_TYPE_CDP, $tick);

        if(strlen($row) != METRIC_CDP_HDR_LEN)
            die('Archive row length is not METRIC_CDP_HDR_LEN(' . METRIC_CDP_HDR_LEN . ') LENGTH=' . strlen($row));

        foreach($values as $value)
            $row .= pack('f', $value);

        if($do_write === true)
            return fwrite($this->h, $row) === strlen($row);

        return $row;

    }

    public function create($tick_sec = 1) {

        if(is_resource($this->h)){

            fclose($this->h);

            unlink($this->file);

        }

        if(!($this->h = fopen($this->file, 'c+')))
            return false;

        $this->tick_sec = $tick_sec;

        $header = pack('vvV', METRIC_TYPE_HDR, $this->version, $this->tick_sec);

        fwrite($this->h, $header);

        //Store the data source definitions
        //Data sources consist of a header, followed by enough primary data points to maintain the archives
        foreach($this->data_sources as &$ds)
            $this->writeDataSource($ds);

        //Create the archive sections
        //Archives consist of a header, followed by a payload section of size $rows
        foreach($this->archives as &$archive)
            $this->writeArchive($archive);

        return true;

    }

    public function setValue($dsname, $value) {

        if(! array_key_exists($dsname, $this->data_sources))
            return FALSE;

        if(! is_numeric($value))
            return FALSE;

        $tick = $this->getTick();

        $ds =& $this->data_sources[$dsname];

        //Set the minimum value
        if($ds['min'] !== null && $ds['min'] > $value)
            $value = $ds['min'];

        //Set the maximum value
        if($ds['max'] !== null && $ds['max'] < $value)
            $value = $ds['max'];

        flock($this->h, LOCK_EX);

        if($ds['last'] < 0)
            $ds['last'] = 0;

        $offset_start = $this->getDataSourceOffset($dsname);

        $offset = $offset_start
            + METRIC_DSDEF_LEN
            + strlen($ds['name'] . $ds['desc']);

        $pos = $offset + ($ds['last'] * METRIC_PDP_ROW_LEN);

        fseek($this->h, $pos);

        $bytes = fread($this->h, METRIC_PDP_ROW_LEN);

        $current = unpack('vtype/Vtick/fvalue', $bytes);

        if(($diff = $tick - $current['tick']) > 0){

            //Only bring the PDPs up to date if this isn't the first write
            if($current['tick'] > 0){

                //Calculate the last row by adding the diff and getting remainder of division by ticks.
                $ds['last'] = ($ds['last'] + $diff) % $ds['ticks'];

                if($diff > $ds['ticks'])
                    $diff = $ds['ticks'];

                for($i = 1; $i < $diff; $i++){

                    $num = $diff - $i;

                    $newtick = $tick - $num;

                    $row = $ds['last'] - $num;

                    if($row < 0)
                        $row = $ds['ticks'] + $row;

                    fseek($this->h, $offset + ($row * METRIC_PDP_ROW_LEN));

                    fwrite($this->h, pack('vVf', METRIC_TYPE_PDP, $newtick, 0));

                }

                $pos = $offset + ($ds['last'] * METRIC_PDP_ROW_LEN);

            }

            $current['tick'] = $tick;

            $current['value'] = 0;

            fseek($this->h, $offset_start + METRIC_DSDEF_LEN + strlen($ds['name'] . $ds['desc']) - 4);

            fwrite($this->h, pack('l', $ds['last']));

            $this->lastTick['data'][$dsname] = $tick;

        }

        switch($this->data_sources[$dsname]['type']) {

            case 0x01:  //GAUGE
            case 0x04:  //GAUGEZ

                if($value > $current['value'])
                    $current['value'] = $value;

                break;

            case 0x02:  //COUNTER

                $current['value'] += $value;

                break;

            case 0x03:  //ABSOLUTE

                $current['value'] = $value;

                break;


        }

        fseek($this->h, $pos);

        fwrite($this->h, pack('vVf', METRIC_TYPE_PDP, $current['tick'], $current['value']));

        flock($this->h, LOCK_UN);

        return TRUE;

    }

    /**
     * The update function stores a consolidated data point in the archive for each data source based on the settings
     * supplied when defining the archive.
     *
     * * A primary data point is a single value stored for each 'tick'.
     * * A consolidated data point is a value calculated based on 1 or more primary data points using the consolidation
     * function specified when defining the archive.
     *
     * How this works is as follows:
     *
     * * Step 1: Load all the available data points ready to be processed
     * * Step 2: Make sure there are data points for all ticks from last_tick to current_tick
     * * Step 3: Check if there are enough primary data points to create a consolidated data point.
     * * Step 4: If so, apply the consolidation function
     * * Step 5: Update the starting point in the archive definition
     * * Step 6: Store the consolidated data point value in the archive.
     *
     */
    public function update() {

        if(!is_resource($this->h))
            return false;

        $current_tick = $this->getTick();

        $updates = array();

        foreach($this->archives as $archive_id => $archive){

            $data = array();

            $last_tick = $this->lastTick['archive'][$archive_id];

            $update_tick = $last_tick + $archive['ticks'];

            if($current_tick > $update_tick){

                foreach($this->data_sources as $dsname => $ds){

                    if($ds['ticks'] < $archive['ticks'])
                        throw new \Exception("There are not enough primary data points({$ds['ticks']}) to satify this archive({$archive['ticks']})");

                    $start = $this->getDataSourceOffset($dsname) + METRIC_DSDEF_LEN + strlen($ds['name'] . $ds['desc']);

                    for($tick = $current_tick - 1; $tick > $last_tick; $tick--){

                        //If the PDP for this tick has not been written
                        if($this->lastTick['data'][$dsname] < $tick){

                            $data[$tick] = 0;

                        }else{

                            $diff = ($tick - $this->lastTick['data'][$dsname]);

                            $row = $ds['last'] + $diff;

                            if($row < 0)
                                $row = $ds['ticks'] + $row;

                            fseek($this->h, $start + ($row * METRIC_PDP_ROW_LEN));

                            $pdp = unpack('vtype/Vtick/fvalue', fread($this->h, METRIC_PDP_ROW_LEN));

                            $data[$tick] = ($pdp['tick'] === $tick) ? $pdp['value'] : 0;

                        }

                    }

                    ksort($data);

                    $current_data = array();

                    foreach($data as $tick => $value) {

                        if($tick >= $current_tick)         //Not ready to process this data point yet
                            break;

                        $current_data[$tick] = $value;

                        if(count($current_data) == $archive['ticks']) {

                            $cvalue = $this->consolidate($archive['cf'], $current_data, $ds['type']);

                            $updates[$archive_id][$tick][$dsname] = $cvalue;

                            $current_data = array();

                        }

                    }
                }

            }

        }

        if(count($updates) > 0) {

            foreach($updates as $archive_id => $rows) {

                $archive =& $this->archives[$archive_id];

                //Get the start of the archive
                $offset_start = $this->getArchiveOffset($archive_id);

                foreach($rows as $tick => $values) {

                    if(count($values) != count($this->data_sources))
                        throw new \Exception('All dataSources must be written in an update!');

                    //Get the current row we are working on
                    $row = $archive['last'] + 1;

                    if($row >= $archive['rows'])
                        $row = 0;

                    $offset = METRIC_ARCHIVE_HDR_LEN + strlen($archive['id'] . $archive['desc']) + ($row * $this->getCDPLength());

                    $pos = $offset_start + $offset;

                    fseek($this->h, $pos); //Seek to the correct archive position

                    $this->writeCDP($tick, $values);

                    $archive['last'] = $row;

                    $this->lastTick['archive'][$archive_id] = $tick;

                }

                $pos = $offset_start + METRIC_ARCHIVE_HDR_LEN + strlen($archive['id'] . $archive['desc']) - 4;

                fseek($this->h, $pos);

                fwrite($this->h, pack('l', $archive['last']));

            }

            return true;

        }

        return false;

    }

    private function consolidate($cf, $data_points, $ds_type = null) {

        $value = NULL;

        //GAUGEZ means we want to ignore zero values in our consolidation
        if($ds_type === 0x04){

            $data_points = array_filter($data_points, function($value){
                return ($value <> 0);
            });

        }

        switch($cf) {

            case 0x01: //AVERAGE

                $value = 0;

                if(is_array($data_points) && count($data_points) > 0){

                    foreach($data_points as $dp)
                        $value += $dp;

                    $value = $value / count($data_points);

                }

                break;

            case 0x02: //MIN

                $value = array_shift($data_points);

                foreach($data_points as $dp)
                    if($dp < $value) $value = $dp;

                break;

            case 0x03: //MAX

                $value = array_shift($data_points);

                foreach($data_points as $dp)
                    if($dp > $value) $value = $dp;

                break;

            case 0x04: //LAST

                $value = array_pop($data_points);

                break;

            case 0x05:

                $value = array_sum($data_points);

                break;

        }

        return $value;

    }

    public function hasDataSource($name) {

        return isset($this->data_sources[$name]);
    }

    public function getDataSources() {

        return array_keys($this->data_sources);

    }

    public function getArchives() {

        return array_keys($this->archives);

    }

    public function getfile() {

        return $this->file;

    }

    public function graph($dsname, $archive_id = 0) {

        if(! $this->exists())
            return FALSE;

        $data = array();

        if(!array_key_exists($dsname, $this->data_sources))
            return FALSE;

        if(!array_key_exists($archive_id, $this->archives))
            return FALSE;

        $archive = $this->archives[$archive_id];

        $offset = $this->getArchiveOffset($archive_id) + METRIC_ARCHIVE_HDR_LEN + strlen($archive['id'] . $archive['desc']);

        fseek($this->h, $offset);

        $row_length = $this->getCDPLength();

        while($type = fread($this->h, 2)) {

            if(ord($type) != METRIC_TYPE_CDP)
                break;

            $parts = unpack('Vtick', fread($this->h, 4));

            $tick = $parts['tick'] * $this->tick_sec;

            if(!($tick > 0))
                break;

            $values = array_combine(array_keys($this->data_sources), unpack('f*', fread($this->h, $row_length - 6)));

            $data[$tick] = $values[$dsname];

        }


        $step_sec = $archive['ticks'] * $this->tick_sec;

        if(($diff = ($archive['rows'] - count($data))) > 0) {

            if(count($data) > 0)
                $min = min(array_keys($data));

            else
                $min = $this->getTick($dsname) * $this->tick_sec;

            $start_tick = $min - ($diff * $step_sec);

            for($tick = $start_tick; $tick < $min; $tick += $step_sec)
                $data[$tick] = floatval(0);

        }

        ksort($data);

        return array(
            'ds'      => $this->data_sources[$dsname],
            'archive' => $archive,
            'ticks'   => $data
        );

    }

    /**
     * Retrieve the raw primary data points stored in a data source
     *
     * @param mixed $dsname The name of the data source.
     *
     * @return \array|boolean
     */
    public function data($dsname){

        $data = array();

        $offset = $this->getDataSourceOffset($dsname);

        fseek($this->h, $offset);

        $bytes = fread($this->h, 2);

        if(ord($bytes) !== METRIC_TYPE_DS)
            return false;

        $header = unpack('vtype/Clen', fread($this->h, 3));

        $name = fread($this->h, $header['len']);

        $body = unpack('Clen', fread($this->h, 1));

        $desc = (($body['len'] > 0) ? fread($this->h, $body['len']) : NULL);

        $foot = unpack('vticks/fmin/fmax/llast', fread($this->h, 6 + (2 * METRIC_FLOAT_LEN)));

        $ds = array(
            'name'      => $name,
            'desc'      => $desc,
            'type'      => $header['type'],
            'ticks'     => $foot['ticks'],
            'min'       => ($foot['min'] == -1) ? NULL : $foot['min'],
            'max'       => ($foot['max'] == -1) ? NULL : $foot['max'],
            'last'      => $foot['last']
        );

        while($type = fread($this->h, 2)) {

            if(ord($type) != METRIC_TYPE_PDP)
                break;

            $row = unpack('Vtick/fvalue', fread($this->h, METRIC_PDP_ROW_LEN - 2));

            if($row['tick'] <= 0)
                break;

            $data[$row['tick']] = $row['value'];

        }

        ksort($data);

        return array(
            'ds' => $ds,
            'ticks' => $data
        );

    }

}


<?php

namespace Hazaar\File;

define('RRD_FLOAT_LEN', strlen(pack('f', 0)));

define('RRD_HEADER_LEN', 8);

define('RRD_DSDEF_LEN', 18);

define('RRD_ARCHIVE_HDR_LEN', 22);  //plus the value of ID length

define('RRD_ROW_HEADER_LEN', 6);

class RRD {

    private $rrdfile;

    private $version         = 2;

    private $dataSources     = array();

    private $archives        = array();

    private $data            = array();

    private $tickSec         = 0;

    private $lastTick        = array();

    private $lastWrite       = array();

    private $dataSourceTypes = array(
        'GAUGE'    => 0x01,
        'COUNTER'  => 0x02,
        'ABSOLUTE' => 0x03
    );

    private $archiveCFs      = array(
        'AVERAGE' => 0x01,
        'MIN'     => 0x02,
        'MAX'     => 0x03,
        'LAST'    => 0x04
    );

    public function __construct($rrdfile, $tickSec = 60) {

        $this->rrdfile = $rrdfile;

        $this->tickSec = $tickSec;

        if(file_exists($this->rrdfile)) {

            $this->restoreOptions();

        }

    }

    public function exists() {

        return (file_exists($this->rrdfile) && filesize($this->rrdfile) > 0);

    }

    /*
     * addDataSource('speed', 'COUNTER', 600, 'U', 'U');
     *
     * types are: GAUGE, COUNTER, ABSOLUTE
     */
    public function addDataSource($dsname, $type, $heartbeat = NULL, $min = NULL, $max = NULL, $description = NULL) {

        $type = strtoupper($type);

        if(! array_key_exists($type, $this->dataSourceTypes))
            return FALSE;

        $this->dataSources[$dsname] = array(
            'name'      => $dsname,
            'desc'      => $description,
            'type'      => $this->dataSourceTypes[$type],
            'heartbeat' => ($heartbeat == NULL ? 0 : $heartbeat),
            'min'       => $min,
            'max'       => $max
        );

        return TRUE;

    }

    /*
     * addArchive('AVERAGE', 0.5, 1, 24);
     *
     * $cf is consolidation function and can be: AVERAGE, MIN, MAX or LAST
     */
    public function addArchive($archiveID, $cf, $xff = NULL, $ticks = NULL, $rows = NULL, $description = NULL) {

        if(! $archiveID)
            return FALSE;

        $cf = strtoupper($cf);

        if(! array_key_exists($cf, $this->archiveCFs))
            return FALSE;

        $this->archives[$archiveID] = array(
            'id'    => $archiveID,
            'desc'  => $description,
            'cf'    => $this->archiveCFs[$cf],      //Consolidation function
            'xff'   => $xff,                        //xFiles factor
            'ticks' => $ticks,                      //Number of ticks to consolidate into a row
            'rows'  => $rows,                       //Number of rows to store in the archive
            'last'  => -1                           //Pointer to the current row
        );

        return TRUE;

    }

    private function getArchiveRowLength() {

        return RRD_ROW_HEADER_LEN + (count($this->dataSources) * RRD_FLOAT_LEN);

    }

    private function getArchiveOffset($archiveID = 0) {

        /**
         * Set some known values
         **/

        $rowLength = $this->getArchiveRowLength();

        $offset = RRD_HEADER_LEN + (count($this->dataSources) * RRD_DSDEF_LEN);

        //Get the length of all names and descriptions
        foreach($this->dataSources as $ds)
            $offset += strlen($ds['name'] . $ds['desc']);

        foreach($this->archives as $id => $archive) {

            if($archiveID == $id)
                break;

            $offset += RRD_ARCHIVE_HDR_LEN + ($rowLength * $archive['rows']) + strlen($archive['id'] . $archive['desc']);

        }

        return $offset;

    }

    public function getTick($time = NULL) {

        if($time === NULL)
            $time = time();

        elseif(! is_numeric($time))
            $time = strtotime($time);

        return intval(floor($time / $this->tickSec));

    }

    private function restoreOptions() {

        if(! file_exists($this->rrdfile))
            return FALSE;

        $h = fopen($this->rrdfile, 'r');

        while($type = fread($h, 2)) {

            switch(ord($type)) {
                case 0x81: //Header

                    $bytes = fread($h, 6);

                    $header = unpack('vversion/lticksec', $bytes);

                    if(intval($header['version']) != $this->version)
                        throw new \Hazaar\Exception('RRD file version error.  File is version ' . $header['version'] . ' but RRD is version ' . $this->version);

                    $this->tickSec = $header['ticksec'];

                    break;

                case 0x82: //DataSource

                    $head = unpack('vtype/Clen', fread($h, 3));

                    $name = fread($h, $head['len']);

                    $body = unpack('Clen', fread($h, 1));

                    $desc = (($body['len'] > 0) ? fread($h, $body['len']) : NULL);

                    $foot = unpack('Vhb/fmin/fmax', fread($h, 12));

                    $ds = array(
                        'name'      => $name,
                        'desc'      => $desc,
                        'type'      => $head['type'],
                        'heartbeat' => $foot['hb'],
                        'min'       => ($foot['min'] == -1) ? NULL : $foot['min'],
                        'max'       => ($foot['max'] == -1) ? NULL : $foot['max']
                    );

                    $this->dataSources[$name] = $ds;

                    break;

                case  0x83: //Archive definition

                    $archive = array();

                    $head = unpack('Clen', fread($h, 1));

                    $archive['id'] = fread($h, $head['len']);

                    $body = unpack('Clen', fread($h, 1));

                    $archive['desc'] = fread($h, $body['len']);

                    $archive = array_merge($archive, unpack('vcf/fxff/Vticks/Vrows/llast', fread($h, 18)));

                    $this->archives[$archive['id']] = $archive;

                    $start = ftell($h);

                    $len = $this->getArchiveRowLength();

                    if($archive['last'] < 0) {

                        $this->lastTick[$archive['id']] = $this->getTick() - 1;

                    } else {

                        //Skip to the current archive row
                        $offset = $len * $archive['last'];

                        fseek($h, $offset, SEEK_CUR);

                        $row = unpack('vtype/Vtick', fread($h, $len));

                        $this->lastWrite[$archive['id']] = $row['tick'];

                        $this->lastTick[$archive['id']] = $row['tick'];

                    }

                    //Skip the rest of the archive
                    $offset = $start + ($len * $archive['rows']);

                    fseek($h, $offset);

                    break;

                case 0x84: //Datapoint

                    die('THIS SHOULD NOT HAPPEN AT FILE POSITION ' . ftell($h) . "\n");

                    break;

                default:

                    die('Unexpected block type! TYPE=' . dechex(ord($type)) . "\n");

                    break;

            }

        }

        fclose($h);

        return TRUE;

    }

    private function writeDataSource($h, $ds) {

        $line = pack('vvC', 0x82, $ds['type'], strlen($ds['name'])) . $ds['name'];

        $line .= pack('C', strlen($ds['desc'])) . $ds['desc'];

        $min = ($ds['min'] === NULL) ? -1 : $ds['min'];

        $max = ($ds['max'] === NULL) ? -1 : $ds['max'];

        $line .= pack('Vff', $ds['heartbeat'], $min, $max);

        $len = strlen($line) - strlen($ds['name']) - strlen($ds['desc']);

        if($len != RRD_DSDEF_LEN)
            die('dataSource header length is not RRD_DSDEF_LEN(' . RRD_DSDEF_LEN . ") LENGTH=$len\n");

        return fwrite($h, $line);

    }

    private function writeArchiveHeader($h, $archive) {

        $header = pack('vC', 0x83, strlen($archive['id'])) . $archive['id'];

        $header .= pack('C', strlen($archive['desc'])) . $archive['desc'];

        $header .= pack('vfVVl', $archive['cf'], $archive['xff'], $archive['ticks'], $archive['rows'], $archive['last']);

        $len = strlen($header) - strlen($archive['id']) - strlen($archive['desc']);

        if($len != RRD_ARCHIVE_HDR_LEN)
            die('archive header length is not RRD_ARCHIVE_HDR_LEN(' . RRD_ARCHIVE_HDR_LEN . ") LENGTH=$len\n");

        return fwrite($h, $header);

    }

    private function writeArchiveRow($h, $tick, $values) {

        $row = pack('vV', 0x84, $tick);

        if(strlen($row) != RRD_ROW_HEADER_LEN)
            die('Archive row length is not RRD_ROW_HEADER_LEN(' . RRD_ROW_HEADER_LEN . ') LENGTH=' . strlen($row));

        foreach($values as $value)
            $row .= pack('f', $value);

        return fwrite($h, $row);

    }

    public function create() {

        if(file_exists($this->rrdfile))
            unlink($this->rrdfile);

        if(!($h = fopen($this->rrdfile, 'w')))
            return false;

        $header = pack('vvl', 0x81, $this->version, $this->tickSec);

        fwrite($h, $header);

        //Store the dataSource definitions
        foreach($this->dataSources as $ds)
            $this->writeDataSource($h, $ds);

        //Create the archive sections
        //Archives consist of a header, followed by a payload section of size $rows
        foreach($this->archives as $archiveID => $archive) {

            $this->writeArchiveHeader($h, $archive);

            for($i = 0; $i < $archive['rows']; $i++)
                $this->writeArchiveRow($h, 0, array_fill(0, count($this->dataSources), 0));

            $this->lastTick[$archiveID] = $this->getTick() - 1;

        }

        fclose($h);

        return TRUE;

    }

    /*
     * setValue('speed', 12345);
     */
    public function setValue($dsname, $value) {

        if(! array_key_exists($dsname, $this->dataSources))
            return FALSE;

        if(! is_numeric($value))
            return FALSE;

        $tick = $this->getTick();

        //Set the minimum value
        if($this->dataSources[$dsname]['min'] !== NULL && $this->dataSources[$dsname]['min'] > $value)
            $value = $this->dataSources[$dsname]['min'];

        //Set the maximum value
        if($this->dataSources[$dsname]['max'] !== NULL && $this->dataSources[$dsname]['max'] < $value)
            $value = $this->dataSources[$dsname]['max'];

        foreach($this->archives as $archiveID => $archive) {

            if(! array_key_exists($archiveID, $this->data))
                $this->data[$archiveID] = array();

            if(! array_key_exists($dsname, $this->data[$archiveID]))
                $this->data[$archiveID][$dsname] = array();

            $data =& $this->data[$archiveID][$dsname];

            switch($this->dataSources[$dsname]['type']) {

                case 0x01:  //GAUGE

                    if(! isset($data[$tick]) || $value > $data[$tick])
                        $data[$tick] = $value;

                    break;

                case 0x02:  //COUNTER

                    if(! isset($data[$tick]))
                        $data[$tick] = 0;

                    $data[$tick] += $value;

                    break;

                case 0x03:  //ABSOLUTE

                    $data[$tick] = $value;

                    break;

            }

        }

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
     * * Step 1: Make sure there are data points for all ticks from lastTick to currentTick
     * * Step 2: Check if there are enough primary data points to create a consolidated data point.
     * * Step 3: If so, apply the consolidation function
     * * Step 4: Update the starting point in the archive definition
     * * Step 5: Store the consolidated data point value in the archive.
     *
     */
    public function update() {

        if(! file_exists($this->rrdfile))
            return FALSE;

        $currentTick = $this->getTick();

        $updates = array();

        foreach($this->archives as $archiveID => &$archive) {

            if(! array_key_exists($archiveID, $this->data))
                $this->data[$archiveID] = array();

            $lastTick = $this->lastTick[$archiveID];    //lastTick is the tick that was last processed

            $updateTick = $lastTick + 1;                //updateTick is when we are next allowed to process

            if($currentTick > $updateTick) {

                $this->lastTick[$archiveID] = $currentTick - 1;

                foreach($this->dataSources as $dsname => $ds) {

                    if(! array_key_exists($dsname, $this->data[$archiveID]))
                        $this->data[$archiveID][$dsname] = array();

                    $dataPoints = &$this->data[$archiveID][$dsname];

                    //STEP 1 - Make sure ticks are up to date
                    for($tick = $updateTick; $tick < $currentTick; $tick++) {

                        //If there are no data points yet, or the datapoint for $tick does not exist
                        if(! array_key_exists($tick, $dataPoints))
                            $dataPoints[$tick] = 0;

                    }

                    //STEP 2 - Check if there are enough dataPoints for this archive
                    //We do this by looking at the datapoints and pulling out those that are after the last update
                    ksort($dataPoints);

                    $currentData = array();

                    foreach($dataPoints as $tick => $dataPoint) {

                        if(array_key_exists($archiveID, $this->lastWrite) && $tick <= $this->lastWrite[$archiveID]) { //Remove old data points

                            unset($dataPoints[$tick]);

                            continue;

                        } elseif($tick >= $currentTick)         //Not ready to process this data point yet
                            break;

                        $currentData[$tick] = $dataPoint;

                        if(count($currentData) == $archive['ticks']) {

                            $value = $this->consolidate($archive['cf'], $currentData);

                            $updates[$archiveID][$tick][$dsname] = $value;

                            $currentData = array();

                        }

                    }

                }

            }

        }

        if(count($updates) > 0) {

            if(!($h = fopen($this->rrdfile, 'c')))
                return false;

            foreach($updates as $archiveID => $rows) {

                $archive =& $this->archives[$archiveID];

                //Get the start of the archive
                $offset_start = $this->getArchiveOffset($archiveID);

                foreach($rows as $tick => $values) {

                    if(count($values) != count($this->dataSources))
                        die('All dataSources must be written in an update!');

                    //Get the current row we are working on
                    $row = $archive['last'] + 1;

                    if($row >= $archive['rows'])
                        $row = 0;

                    $offset = RRD_ARCHIVE_HDR_LEN + strlen($archive['id'] . $archive['desc']) + ($row * $this->getArchiveRowLength());

                    $pos = $offset_start + $offset;

                    fseek($h, $pos); //Seek to the correct archive position

                    $this->writeArchiveRow($h, $tick, $values);

                    $archive['last'] = $row;

                    $this->lastWrite[$archiveID] = $tick;

                }

                $pos = $offset_start + RRD_ARCHIVE_HDR_LEN + strlen($archive['id'] . $archive['desc']) - 4;

                fseek($h, $pos);

                fwrite($h, pack('l', $archive['last']));

            }

            fclose($h);

            return TRUE;

        }

        return FALSE;

    }

    private function consolidate($cf, $dataPoints) {

        $value = NULL;

        switch($cf) {

            case 0x01: //AVERAGE

                $value = 0;

                foreach($dataPoints as $dp)
                    $value += $dp;

                $value = $value / count($dataPoints);

                break;

            case 0x02: //MIN

                $value = array_shift($dataPoints);

                foreach($dataPoints as $dp)
                    if($dp < $value) $value = $dp;

                break;

            case 0x03: //MAX

                $value = array_shift($dataPoints);

                foreach($dataPoints as $dp)
                    if($dp > $value) $value = $dp;

                break;

            case 0x04: //LAST

                $value = array_pop($dataPoints);

                break;

        }

        return $value;

    }

    public function hasDataSource($name) {

        return isset($this->dataSources[$name]);
    }

    public function getDataSources() {

        return array_keys($this->dataSources);

    }

    public function getArchives() {

        return array_keys($this->archives);

    }

    public function getRRDFile() {

        return $this->rrdfile;

    }

    public function setTickSec($tickSec) {

        $this->tickSec = $tickSec;

    }

    public function getTickSec() {

        return $this->tickSec;

    }

    public function graph($dsname, $archiveID = 0) {

        if(! $this->exists())
            return FALSE;

        $data = array();

        if(! array_key_exists($dsname, $this->dataSources))
            return FALSE;

        if(! array_key_exists($archiveID, $this->archives))
            return FALSE;

        $h = fopen($this->rrdfile, 'r');

        $archive = $this->archives[$archiveID];

        $offset = $this->getArchiveOffset($archiveID) + RRD_ARCHIVE_HDR_LEN + strlen($archive['id'] . $archive['desc']);

        fseek($h, $offset, SEEK_SET);

        $rowLength = $this->getArchiveRowLength();

        while($type = fread($h, 2)) {

            if(ord($type) != 0x84)
                break;

            $parts = unpack('Vtick', fread($h, 4));

            $tick = $parts['tick'] * $this->tickSec;

            if(! ($tick > 0))
                continue;

            $values = array_combine(array_keys($this->dataSources), unpack('f*', fread($h, $rowLength - 6)));

            $data[$tick] = $values[$dsname];

        }

        $stepSec = $archive['ticks'] * $this->tickSec;

        if(($diff = ($archive['rows'] - count($data))) > 0) {

            if(count($data) > 0)
                $min = min(array_keys($data));

            else
                $min = $this->getTick() * $this->tickSec;

            $startTick = $min - ($diff * $stepSec);

            for($tick = $startTick; $tick < $min; $tick += $stepSec)
                $data[$tick] = 0;

        }

        fclose($h);

        ksort($data);

        return array(
            'dataSource' => $this->dataSources[$dsname],
            'archive'    => $archive,
            'ticks'      => $data
        );

    }

}


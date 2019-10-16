<?php

namespace Hazaar;

/**
 * @brief       The version class
 *
 * @detail      Handy for comparing version numbers such as 1.0 vs 1.3 or even 2.3.6 vs 1.6.3.7.32
 *
 * @module      core
 */
class Version {

    private $version;

    /**
     * Version constructor.
     *
     * @param $version string The version number as a string.
     *
     * @throws \Exception
     */
    public function __construct($version) {

        if($version == NULL)
            throw new \Hazaar\Exception('Version can not be null');

        if(! preg_match('/[0-9]+(\\.[0-9]+)*/', $version))
            throw new \Hazaar\Exception('Invalid version format');

        $this->version = $version;

    }

    /**
     * Get the current version number
     *
     * @return string
     */
    public final function get() {

        return $this->version;

    }

    /**
     * Magic method to output the version as a string
     *
     * @return string
     */
    public function __tostring() {

        return $this->get();

    }

    /**
     * Compare the version to another version.
     *
     * Returns an integer indicating the result:
     * * 0 Indicates the versions are equal.
     * * -1 Means the vesion is less than $that.
     * * 1 Means the version is greater than $that.
     *
     * @param mixed $that The version to compare against.  Can be either a version string or another version object.
     *
     * @return int Either -1, 0 or 1 to indicate if the version is less than, equal to or greater than $that.
     */
    public function compareTo($that) {

        if($that == NULL)
            return 1;

        if(! $that instanceof Version)
            $that = new Version($that);

        $thisParts = preg_split('/\\./', $this->get());

        $thatParts = preg_split('/\\./', $that->get());

        $length = max(count($thisParts), count($thatParts));

        for($i = 0; $i < $length; $i++) {

            $thisPart = $i < count($thisParts) ? intval($thisParts[$i]) : 0;

            $thatPart = $i < count($thatParts) ? intval($thatParts[$i]) : 0;

            if($thisPart < $thatPart)
                return -1;

            if($thisPart > $thatPart)
                return 1;

        }

        return 0;

    }

    /**
     * Compares two versions to see if they are equal.
     *
     * @param $that The version to compare to.
     *
     * @return bool TRUE or FALSE indicating if the versions are equal.
     */
    public function equals($that) {

        if($this == $that)
            return TRUE;

        if($that == NULL)
            return FALSE;

        if(get_class($this) != get_class($that))
            return FALSE;

        return ($this->compareTo($that) == 0);
    }

}
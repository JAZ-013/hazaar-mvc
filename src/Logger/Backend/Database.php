<?php

namespace Hazaar\Logger\Backend;

class Database extends \Hazaar\Logger\Backend {

    private $failed = false;

    private $db;

    public function init() {

        $this->setDefaultOption('host', 'localhost');

        $this->setDefaultOption('database', 'hazaar_default');

        $this->setDefaultOption('table', 'log');

        $this->setDefaultOption('write_ip', true);

        $this->setDefaultOption('write_timestamp', true);

        $this->setDefaultOption('write_uri', true);

        $config = array(
            'driver' => $this->getOption('driver'),
            'host'   => $this->getOption('host'),
            'dbname' => $this->getOption('database')
        );

        if($this->hasOption('username'))
            $config['user'] = $this->getOption('username');

        if($this->hasOption('password'))
            $config['password'] = $this->getOption('password');

        $this->db = new \Hazaar\DBI($config);

    }

    public function write($tag, $message, $level = LOG_NOTICE) {

        if($this->failed)
            return null;

        try {

            $row = array(
                'tag' => $tag,
                'message' => $message,
                'level'   => $level
            );

            $remote = $_SERVER['REMOTE_ADDR'];

            if($this->getOption('write_ip'))
                $row['remote'] = $remote;

            if($this->getOption('write_timestamp'))
                $row['timestamp'] = new \Hazaar\Date();

            if($this->getOption('write_uri'))
                $row['uri'] = $_SERVER['REQUEST_URI'];

            $this->db->insert($this->getOption('table'), $row);

        } catch(\Exception $e) {

            $this->failed = true;

            throw $e;

        }

    }

    public function trace() {

    }

}

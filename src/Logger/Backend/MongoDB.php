<?php

namespace Hazaar\Logger\Backend;

class MongoDB extends \Hazaar\Logger\Backend {

    private $collection;

    public function init() {

        $hosts = $this->getOption('hosts');

        if(! $hosts) {

            throw new Exception\NoMongoDBHost();

        }

        $this->setDefaultOption('database', 'hazaar_default');

        $this->setDefaultOption('collection', 'log');

        $this->setDefaultOption('write_ip', true);

        $this->setDefaultOption('write_timestamp', true);

        $this->setDefaultOption('write_uri', true);

        $db = new \Hazaar\Mongo\DB(array(
            'hosts'    => $hosts,
            'database' => $this->getOption('database')
        ));

        $this->collection = $db->selectCollection($this->getOption('collection'));

    }

    public function write($tag, $message, $level = LOG_NOTICE) {

        if($this->failed)
            return null;

        try {

            $doc = array('tag' => $tag, 'message' => $message);

            $remote = $_SERVER['REMOTE_ADDR'];

            if($this->getOption('write_ip'))
                $doc['remote'] = $remote;

            if($this->getOption('write_timestamp'))
                $doc['timestamp'] = new \Hazaar\Date();

            $doc['level'] = strtoupper($this->getLogLevelName($level));

            if($this->getOption('write_uri'))
                $doc['uri'] = $_SERVER['REQUEST_URI'];

            $this->collection->save($doc);

        } catch(\Exception $e) {

            $this->failed = true;

            throw $e;

        }

    }

    public function trace() {

    }

}

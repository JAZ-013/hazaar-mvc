<?php

/**
 * @file        Hazaar/Cache/Backend/Database.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar\Cache\Backend;

/**
 * @brief The database cache backend.
 *
 * @detail This is another reliable almost always available cache backend, similar to file.  However if your database supports clustering/replication
 * such as MySQL or PostgreSQL, then you have just given yourself a cheap reliable clustered cache backend.
 *
 * This backend works by creating tables in the database server by default with a name of cache_{namespace}.  You can of course override this
 * but why would you want to?  It works.  Just make sure you have your databas environment configred and the user has create privileges.
 *
 * NOTE: The configuration options are passed directly "as is" to the database backend.  This allows the backend to override the default
 * database settings when creating the DBI object.  This allows you to have a completely separate database where the cache user has table
 * create privileges in a database that is not where you keep all your important stuff.
 *
 * Available config options:
 *
 * * cache_table - The name of the table to use for this instance of the cache backend. Default: cache_{namespace}.
 * * ...DBI options... - See the DBI documentation for options you can use to configure your DBI instance.
 *
 * @since 1.0.0
 */
class Database extends \Hazaar\Cache\Backend {

    private   $db;

    protected $weight = 6;

    private   $namespace;

    private   $timeout;

    static public function available(){

        return true;

    }

    public function init($namespace) {

        $this->namespace = $namespace;

        $this->addCapabilities('array', 'keepalive');

        $this->configure(array(
            'cache_table' => 'cache_' . $namespace
        ));

        if(! trim($this->options->cache_table))
            throw new Exception\NoDBTable();

        if($this->options->has('schema'))
            $this->options->config['schema'] = $this->options['schema'];

        $this->db = \Hazaar\DBI\Adapter::getInstance($this->options->get('config'));

        if(!$this->db->table('__meta__')->exists()){

            $fields = array(
                'namespace'    => 'TEXT PRIMARY KEY',
                'tablename'    => 'TEXT',
                'timeout'  => 'integer'
            );

            $this->db->createTable('__meta__', $fields);

        }

        if(!$this->db->table($this->options->cache_table)->exists()){

            $fields = array(
                'key'    => 'TEXT PRIMARY KEY',
                'value'  => 'TEXT',
                'expire' => 'INTEGER'
            );

            $this->db->createTable($this->options->cache_table, $fields);

        }

        //If the lifetime value is greater than 0 then we support namespace timeouts.
        if($this->options->lifetime > 0){

            $this->addCapabilities('expire_ns', 'keepalive');

            //If a timeout exists, load it and check if we need to drop the namespace.
            if(!($timeout = $this->db->table('__meta__')->find(array('namespace' => $this->namespace), array('timeout'))->execute()->fetchColumn(0)))
                $timeout = 0;

            //If the namespace has expired, drop it
            if(time() >= $timeout){

                $this->db->dropTable($this->namespace);

                $this->timeout = time() + $this->options->lifetime;

            }

        }

    }

    /**
     * Store the namespace timeout in the cache dir timeout file.
     *
     * This should only happen if a keepalive() has been called.
     */
    function __destruct(){

        if(!$this->db)
            return;

        if($this->timeout > 0)
            $this->db->table('__meta__')->insert(array('namespace' => $this->namespace, 'tablename' => $this->options->cache_table, 'timeout' => $this->timeout), null, 'namespace', true);

        $this->db->beginTransaction();

        //Cleanup any expired namespace tables
        $dead_tables = $this->db->table('__meta__')->find(array('timeout' => array('$lt' => time())));

        while($meta = $dead_tables->fetch()){

            if($this->db->dropTable($meta['tablename']))
                $this->db->table('__meta__')->delete(array('namespace' => $meta['namespace']));

        }

        $this->db->commit();

    }

    private function keepalive(){

        if($this->options->keepalive === true && $this->options->lifetime > 0)
            $this->timeout = time() + $this->options->lifetime;

    }

    public function has($key) {

        $result = $this->db->exists($this->options->cache_table, array('key' => $key, 'expire' => array('$gt' => time())));

        $this->keepalive();

        return boolval($result);

    }

    public function get($key) {

        $criteria = array(
            'key' => $key, 
            '$or' => array(
                array('expire' => null),
                array('expire' => array('$gt' => time()))
            )
        );

        $result = $this->db->table($this->options->cache_table)->findOne($criteria, array('value'));

        $this->keepalive();

        if(! is_array($result))
            return FALSE;

        return stripslashes($result['value']);

    }

    public function set($key, $value, $timeout = NULL) {

        $data = array('key' => $key, 'value' => $value);

        if($timeout > 0)
            $data['expire'] = time() + $timeout;

        $value = addslashes($value);

        $this->db->table($this->options->cache_table)->insert($data, null, 'key', true);

        $this->keepalive();

    }

    public function remove($key) {

        $this->db->table($this->options->cache_table)->delete(array('key' => $key));

        $this->keepalive();

    }

    public function clear() {

        $this->db->table($this->options->cache_table)->deleteAll();

        $this->keepalive();

    }

    public function toArray(){

        $this->keepalive();

        return $this->db->table($this->options->cache_table)->collate('key', 'value');

    }

}
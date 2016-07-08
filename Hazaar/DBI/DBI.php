<?php

/**
 * @file        Hazaar/DBI/DBI.php
 *
 * @author      Jamie Carl <jamie@hazaarlabs.com>
 *
 * @copyright   Copyright (c) 2012 Jamie Carl (http://www.hazaarlabs.com)
 */
namespace Hazaar;

/**
 * @brief Relational Database Interface
 *
 * @detail The DB module provides classes for access to relational database via
 * "PDO":http://www.php.net/manual/en/book.pdo.php (PHP Data Object) drivers and classes. This
 * approach allows developers to use these classes to access a range of different database servers.
 *
 * PDO has supporting drivers for:
 *
 * * "PostgreSQL":http://www.postgresql.org
 * * "MySQL":http://www.mysql.com
 * * "SQLite":http://www.sqlite.org
 * * "MS SQL Server":http://www.microsoft.com/sqlserver
 * * "Oracle":http://www.oracle.com
 * * "IBM Informix":http://www.ibm.com/software/data/informix
 * * "Interbase":http://www.embarcadero.com/products/interbase
 *
 * Access to database functions is all done using a common class structure.
 *
 * h2. Example Usage
 *
 * <code>
 * $db = new Hazaar\DBI();
 * $result = $this->execute('SELECT * FROM users');
 * while($row = $result->fetch()){
 * //Do things with $row here
 * }
 * </code>
 */
class DBI {

    private static $default_config = array();

    private static $connections = array();

    private $config = NULL;

    private $driver;

    private $schema_file;

    private $migration_log = array();

    // Prepared statements
    private $statements = array();

    function __construct($config_env = NULL) {

        if ($config_env == NULL || is_string($config_env)) {

            $this->config = $this->getDefaultConfig($config_env);

        } elseif (is_array($config_env)) {

            $this->config = new \Hazaar\Map($config_env);

        } elseif ($config_env instanceof \Hazaar\Map) {

            $this->config = $config_env;

        }

        if (\Hazaar\Map::is_array($this->config)) {

            if (!$this->config->has('dsn')) {

                $dsn = $this->config->driver . ':';

                $this->config->del('driver');

                $dsn .= $this->config->flatten('=', ';');

                $this->config->dsn = $dsn;

            }

            $this->connect($this->config->dsn, $this->config->user, $this->config->password);

        } else {

            throw new \Exception('No DBI configuration found!');

        }

        $this->schema_file = realpath(APPLICATION_PATH . '/..') . '/db/schema.json';

    }

    static public function getDefaultConfig($env = NULL) {

        if (!array_key_exists($env, DBI::$default_config)) {

            DBI::$default_config[$env] = new \Hazaar\Application\Config('database.ini', $env);
        }

        return DBI::$default_config[$env];

    }

    public function connect($dsn, $username = NULL, $password = NULL, $driver_options = NULL) {

        $driver = ucfirst(substr($dsn, 0, strpos($dsn, ':')));

        if (!$driver)
            throw new DBI\Exception\DriverNotSpecified();

        if (!array_key_exists($driver, DBI::$connections))
            DBI::$connections[$driver] = array();

        $hash = md5(serialize(array(
            $driver,
            $dsn,
            $username,
            $password,
            $driver_options
        )));

        if (array_key_exists($hash, DBI::$connections)) {

            $this->driver = DBI::$connections[$hash];

        } else {

            $class = 'Hazaar\DBI\DBD\\' . $driver;

            if (!class_exists($class))
                throw new DBI\Exception\DriverNotFound($driver);

            $this->driver = new $class($this->config);

            if (!$driver_options)
                $driver_options = array();

            $driver_options = array_replace(array(
                \PDO::ATTR_STRINGIFY_FETCHES => FALSE,
                \PDO::ATTR_EMULATE_PREPARES => FALSE
            ), $driver_options);

            if (!($this->driver->connect($dsn, $username, $password, $driver_options)))
                throw new DBI\Exception\ConnectionFailed($dsn);

            DBI::$connections[$hash] = $this->driver;
        }

        return TRUE;

    }

    public function getDriver() {

        $class = get_class($this->driver);

        return substr($class, strrpos($class, '\\') + 1);

    }

    public function beginTransaction() {

        return $this->driver->beginTransaction();

    }

    public function commit() {

        return $this->driver->commit();

    }

    public function getAttribute($option) {

        return $this->driver->getAttribute($option);

    }

    public function getAvailableDrivers() {

        $drivers = array();

        $dir = new \Hazaar\File\Dir(dirname(__FILE__) . '/DBD');

        while($file = $dir->read()) {

            if (preg_match('/class (\w*) extends \\\Hazaar\\\DBI\\\BaseDriver\W/m', $file->getContents(), $matches)) {

                $drivers[] = $matches[1];
            }
        }

        return $drivers;

    }

    public function inTransaction() {

        return $this->driver->inTransaction();

    }

    public function lastInsertId() {

        return $this->driver->lastInsertId();

    }

    public function quote($string) {

        return $this->driver->quote($string);

    }

    public function rollBack() {

        return $this->driver->rollback();

    }

    public function setAttribute() {

        return $this->driver->setAttribute();

    }

    public function errorCode() {

        return $this->driver->errorCode();

    }

    public function errorInfo() {

        return $this->driver->errorInfo();

    }

    public function exec($sql) {

        return $this->driver->exec($sql);

    }

    public function query($sql) {

        $result = $this->driver->query($sql);

        if($result instanceof \PDOStatement)
            return new \Hazaar\DBI\Result($result);

        return $result;

    }

    public function exists($table, $criteria = array()) {

        return $this->table($table)->exists($criteria);

    }

    public function insert($table, $fields, $returning = NULL) {

        return $this->driver->insert($table, $fields, $returning);

    }

    public function update($table, $fields, $criteria = array()) {

        return $this->driver->update($table, $fields, $criteria);

    }

    public function delete($table, $criteria) {

        return $this->driver->delete($table, $criteria);

    }

    public function deleteAll($table) {

        return $this->driver->deleteAll($table);

    }

    public function __get($tablename) {

        return $this->table($tablename);

    }

    public function __call($tablename, $args) {

        $args = array_merge(array(
            $tablename
        ), $args);

        return call_user_func_array(array(
            $this,
            'table'
        ), $args);

    }

    public function table($name, $alias = NULL) {

        return new DBI\Table($this->driver, $name, $alias);

    }

    public function call($method, $args = array()) {

        $arglist = array();

        foreach($args as $arg)
            $arglist[] = (is_numeric($arg) ? $arg : $this->quote($arg));

        $sql = 'SELECT ' . $method . '(' . implode(',', $arglist) . ');';

        return $this->query($sql);

    }

    /**
     * List all tables currently in the connected database.
     *
     * @since 2.0
     */
    public function listTables() {

        return $this->driver->listTables();

    }

    /**
     * Test that a table exists in the connected database.
     *
     * @param string $table
     *            The name of the table to check for.
     *
     * @param string $schema
     *            The database schema to look in. Defaults to public.
     */
    public function tableExists($table) {

        return $this->driver->tableExists($table);

    }

    /**
     * Create a new table in the database.
     *
     * @param string $name
     *
     * @param string $columns
     */
    public function createTable($name, $columns) {

        return $this->driver->createTable($name, $columns);

    }

    public function describeTable($name, $sort = NULL) {

        return $this->driver->describeTable($name, $sort);

    }

    public function renameTable($from_name, $to_name) {

        return $this->driver->renameTable($from_name, $to_name);

    }

    public function dropTable($name) {

        return $this->driver->dropTable($name);

    }

    public function addColumn($table, $column_spec) {

        return $this->driver->addColumn($table, $column_spec);

    }

    public function alterColumn($table, $column, $column_spec) {

        return $this->driver->alterColumn($table, $column, $column_spec);

    }

    public function dropColumn($table, $column) {

        return $this->driver->dropColumn($table, $column);

    }

    public function listSequences() {

        return $this->driver->listSequences();

    }

    public function describeSequence($name) {

        return $this->driver->describeSequence($name);

    }

    public function listIndexes($table) {

        return $this->driver->listIndexes($table);

    }

    public function createIndex($idx_info, $table) {

        return $this->driver->createIndex($idx_info, $table);

    }

    public function dropIndex($name) {

        return $this->driver->dropIndex($name);

    }

    public function listTableConstraints($name = NULL, $type = NULL, $invert_type = FALSE) {

        return $this->driver->listTableConstraints($name, $type, $invert_type);

    }

    public function addConstraint($info, $table) {

        return $this->driver->addConstraint($info, $table);

    }

    public function dropConstraint($name, $table) {

        return $this->driver->dropConstraint($name, $table);

    }

    public function execCount() {

        return $this->driver->execCount();

    }

    public function getSchemaVersion() {

        if (!$this->schema_info->exists())
            return false;

        $result = $this->schema_info->find(array(), array(
            'version'
        ))->sort('version', true);

        if ($row = $result->row())
            return $row['version'];

        return false;

    }

    public function getSchemaVersions($with_file_obj = false) {

        $db_dir = dirname($this->schema_file);

        $migrate_dir = $db_dir . '/migrate';

        $versions = array();

        /**
         * Get a list of all the available versions
         */
        $dir = new \Hazaar\File\Dir($migrate_dir);

        if ($dir->exists()) {

            while($file = $dir->read()) {

                if (preg_match('/(\d*)_(\w*)/', $file, $matches)) {

                    if ($with_file_obj) {

                        $versions[(int) $matches[1]] = $file;
                    } else {

                        $versions[(int) $matches[1]] = str_replace('_', ' ', $matches[2]);
                    }
                }
            }

            ksort($versions);

            return $versions;
        }

    }

    /**
     * Creates the info table that stores the version info of the current database.
     */
    private function createInfoTable() {

        $table = 'schema_info';

        if (!$this->tableExists($table)) {

            $this->createTable($table, array(
                'version' => array(
                    'data_type' => 'int8',
                    'not_null' => true,
                    'primarykey' => true
                )
            ));

            return true;
        }

        return false;

    }

    private function getColumn($needle, $haystack, $key = 'name') {

        foreach($haystack as $item) {

            if (array_key_exists($key, $item) && $item[$key] == $needle)
                return $item;
        }

        return null;

    }

    private function colExists($needle, $haystack, $key = 'name') {

        return ($this->getColumn($needle, $haystack, $key) !== null) ? true : false;

    }

    private function getColumnDiff($new, $old) {

        $this->log("Column diff is not implemented yet!");

        return null;

    }

    private function getTableDiffs($new, $old) {

        $diff = array();

        /**
         * Look for any differences between the existing schema file and the current schema
         */
        $this->log("Looking for new and updated columns");

        foreach($new as $col) {

            /*
             * Check if the column is in the schema and if so, check it for changes
             */
            if (!$this->colExists($col['name'], $old)) {

                $this->log("Column '$col[name]' is new.");

                $diff['add'][$col['name']] = $col;
            }
        }

        $this->log("Looking for removed columns");

        foreach($old as $col) {

            if (!$this->colExists($col['name'], $new)) {

                $this->log("Column '$col[name]' has been removed.");

                $diff['drop'][] = $col['name'];
            }
        }

        return $diff;

    }

    /**
     * Snapshot the database schema and create a new schema version with migration replay files.
     *
     * This method is used to create the database schema migration files. These files are used by the \Hazaar\DBI::migrate()
     * method to bring a database up to a certain version. Using this method simplifies creating these migration files
     * and removes the need to create them manually when there are trivial changes.
     *
     * When developing your project
     *
     * Currently only the following changes are supported:
     * * Table creation, removal and rename.
     * * Column creation, removal and alteration.
     * * Index creation and removal.
     *
     * p(notice notice-info). Table rename detection works by comparing new tables with removed tables for tables that have the same columns. Because
     * of this, rename detection will not work if columns are added or removed at the same time the table is renamed. If you want to
     * rename a table, make sure that this is the only operation being performed on the table for a single snapshot. Modifying other
     * tables will not affect this. If you want to rename a table AND change it's column layout, make sure you do either the rename
     * or the modifications first, then snapshot, then do the other operation before snapshotting again.
     *
     * @param string $comment
     *            A comment to add to the migration file.
     *
     * @throws \Exception
     *
     * @return boolean True if the snapshot was successful. False if no changes were detected and nothing needed to be done.
     */
    public function snapshot($comment = null, $test = false) {

        $this->log('Snapshot process starting');

        if ($test)
            $this->log('Test mode ENABLED');

        if ($versions = $this->getSchemaVersions()) {

            end($versions);

            $latest_version = key($versions);

        } else {

            $latest_version = 0;

        }

        $version = $this->getSchemaVersion();

        if ($latest_version > $version)
            throw new \Exception('Snapshoting a database that is not at the latest schema version is not supported.');

        $this->beginTransaction();

        $db_dir = dirname($this->schema_file);

        if (!is_dir($db_dir)) {

            if (file_exists($db_dir))
                throw new \Exception('Unable to create database migration directory.  It exists but is not a directory!');

            mkdir($db_dir);

        }

        try {

            $result = $this->query('SELECT now()');

            if ($result->count() == 0)
                throw new \Exception('No rows returned!');

            $this->log("Starting at: " . $result->fetchColumn(0));

        }
        catch(\Exception $e) {

            $this->log('There was a problem connecting to the database!');

            $this->log($e->getMessage());

            return false;

        }

        $init = false;

        /**
         * Load the existing stored schema to use for comparison
         */
        $schema = (file_exists($this->schema_file) ? json_decode(file_get_contents($this->schema_file), true) : array());

        if ($schema) {

            $this->log('Existing schema loaded.');

            $this->log(count(ake($schema, 'tables', array())) . ' tables defined.');

            $this->log(count(ake($schema, 'indexes', array())) . ' indexes defined.');

        } else {

            $comment = 'Initial Snapshot';

            $this->log('No existing schema.  Creating initial snapshot.');

            $init = true;

        }

        /**
         * Prepare a new version number based on the current date and time
         */
        $version = date('YmdHis');

        /**
         * Stores the schema as it currently exists in the database
         */
        $current_schema = array('version' => $version);

        /**
         * Stores only changes between $schema and $current_schema
         */
        $changes = array();

        /**
         * Check for any new tables or changes to existing tables.
         * This pretty much looks just for tables to add and
         * any columns to alter.
         */
        foreach($this->listTables() as $table) {

            $name = $table['schema'] . '.' . $table['name'];

            if ($name == 'public.schema_info')
                continue;

            $this->log("Processing table '$name'.");

            $cols = $this->describeTable($table['name'], $table['schema'], 'ordinal_position');

            $current_schema['tables'][$name] = $cols;

            if (array_key_exists('tables') && array_key_exists($name['tables'])) {

                $this->log("Table '$name' already exists.  Checking differences.");

                $diff = $this->getTableDiffs($cols['tables'][$name]);

                if (count($diff) > 0) {

                    $this->log("Table '$name' has changed.");

                    $changes['up']['alter']['table'][$name] = $diff;

                    foreach($diff as $diff_mode => $col_diff) {

                        $diff_mode = ($diff_mode == 'add') ? 'drop' : 'add';

                        foreach($col_diff as $col_name => $col_info) {

                            if ($diff_mode == 'add') {

                                $info = $this->getColumn($col_info['tables'][$name]);

                                $changes['down']['alter']['table'][$name][$diff_mode][$col_name] = $info;

                            } else {

                                $changes['down']['alter']['table'][$name][$diff_mode][] = $col_name;

                            }

                        }

                    }

                } else {

                    $this->log("No changes to '$name'.");

                }

            } else { // Table doesn't exist, so we add a command to create the whole thing

                $this->log("Table '$name' has been created.");

                $changes['up']['create']['table'][] = array(
                    'name' => $name,
                    'cols' => $cols
                );

                if ($init)
                    $changes['down']['raise'] = 'Can not revert initial snapshot';

                else
                    $changes['down']['remove']['table'][] = $name;

            }

            $indexes = $this->listIndexes($table['name'], $table['schema']);

            if ($indexes)
                $current_schema['indexes'][$name] = $indexes;

            if (array_key_exists('indexes') && array_key_exists($name['indexes'])) {

                $this->log('Table index diff is not completed yet!');

                $diff = array();

                foreach($indexes as $key => $index) {

                    $def = array(
                        'type' => 'index',
                        'def' => $index
                    );

                    $changes['up']['create'][] = $def;

                }

            }

        }

        if (array_key_exists('tables')) {

            /**
             * Now look for any tables that have been removed
             */
            $missing = array_diff(array_keys($schema['tables']), array_keys($current_schema['tables']));

            if (count($missing) > 0) {

                foreach($missing as $table) {

                    $this->log("Table '$table' has been removed.");

                    $changes['up']['remove']['table'][] = $table;

                    $changes['down']['create']['table'][] = array(
                        'name' => $table,
                        'cols' => $schema['tables'][$table]
                    );

                }

            }

        }

        /**
         * Now compare the create and remove changes to see if a table is actually being renamed
         */
        if (isset($changes['up']['create']) && isset($changes['up']['remove']['table'])) {

            $this->log('Looking for renamed tables.');

            foreach($changes['up']['create'] as $create_key => $create) {

                if ($create['type'] == 'table') {

                    $cols = array();

                    foreach($create['cols'] as $col)
                        $cols[$col['name']] = $col;

                    foreach($changes['up']['remove']['table'] as $remove_key => $remove) {

                        if (!array_diff_assoc($schema['tables'][$remove], $changes['up']['remove']['table'])) {

                            $this->log("Table '$remove' has been renamed to '{$create['name']}'.", LOG_NOTICE);

                            $changes['up']['rename']['table'][] = array(
                                'from' => $remove,
                                'to' => $create['name']
                            );

                            $changes['down']['rename']['table'][] = array(
                                'from' => $create['name'],
                                'to' => $remove
                            );

                            /**
                             * Clean up the changes
                             */
                            unset($changes['up']['create'][$create_key]);

                            if (count($changes['up']['create']) == 0)
                                unset($changes['up']['create']);

                            unset($changes['up']['remove']['table'][$remove_key]);

                            if (count($changes['up']['remove']['table']) == 0)
                                unset($changes['up']['remove']['table']);

                            if (count($changes['up']['remove']) == 0)
                                unset($changes['up']['remove']);

                            foreach($changes['down']['remove']['table'] as $down_remove_key => $down_remove) {

                                if ($down_remove == $create['name'])
                                    unset($changes['down']['remove']['table'][$down_remove_key]);
                            }

                            foreach($changes['down']['create'] as $down_create_key => $down_create) {

                                if ($down_create['name'] == $remove)
                                    unset($changes['down']['create'][$down_create_key]);

                            }

                            if (count($changes['down']['create']) == 0)
                                unset($changes['down']['create']);

                            if (count($changes['down']['remove']['table']) == 0)
                                unset($changes['down']['remove']['table']);

                            if (count($changes['down']['remove']) == 0)
                                unset($changes['down']['remove']);
                        }

                    }

                }

            }

        }

        if ($test) {

            return ake($changes,'up');

            /**
             * Save the migrate diff file
             */
        } elseif (count($changes) > 0) {

            if (!$comment)
                $comment = "New Snapshot";

            $this->log('Comment: ' . $comment);

            if ($init == true) {

                $changes = array(
                    'version' => $version,
                    'schema' => $current_schema
                );

            } else {

                $this->log('Migration diffs are not fully supported yet! Be careful!');

            }

            $migrate_dir = $db_dir . '/migrate';

            if (!file_exists($migrate_dir)) {

                $this->log('Migration directory does not exist.  Creating.');

                mkdir($migrate_dir);

            }

            $migrate_file = $migrate_dir . '/' . $version . '_' . str_replace(' ', '_', trim($comment)) . '.json';

            $this->log("Writing migration file to '$migrate_file'");

            file_put_contents($migrate_file, json_encode($changes, JSON_PRETTY_PRINT));

            $this->log("Saving current schema ($this->schema_file)");

            file_put_contents($this->schema_file, json_encode($current_schema, JSON_PRETTY_PRINT));

            $this->createInfoTable();

            $this->insert('schema_info', array(
                'version' => $version
            ));

            $this->commit();

            return true;
        }

        $this->log('No changes detected.');

        return false;

    }

    /**
     * Database migration method.
     *
     * This method does some fancy database migration magic. It makes use of the 'db' subdirectory in the project directory
     * which should contain the schema.json file. This file is the current database schema definition.
     *
     * A few things can occur here.
     *
     * # If the database schema does not exist, then a new schema will be created using the schema.json schema definition file.
     * This will create the database at the latest version of the schema.
     * # If the database schema already exists, then the current version is checked against the version requested using the
     * $version parameter. If no version is requested ($version is NULL) then the latest version number is used.
     * # If the version numbers are different, then a migration will be performed.
     * # # If the requested version is greater than the current version, the migration mode will be 'up'.
     * # # If the requested version is less than the current version, the migration mode will be 'down'.
     * # All migration files between the two selected versions (current and requested) will be replayed using the migration mode.
     *
     * This process can be used to bring a database schema up to the latest version using database migration files stored in the
     * db/migrate project subdirectory. These migration files are typically created using the \Hazaar\DBI::snapshot() method
     * although they can be created manually. Take care when using manually created migration files.
     *
     * The migration is performed in a database transaction (if the database supports it) so that if anything goes wrong there
     * is no damage to the database. If something goes wrong, errors will be availabl in the migration log accessible with
     * \Hazaar\DBI::getMigrationLog(). Errors in the migration files can be fixed and the migration retried.
     *
     * @param int $version
     *            The database schema version to migrate to.
     *
     * @return boolean Returns true on successful migration. False if no migration was neccessary. Throws an Exception on error.
     */
    public function migrate($version = null, $test = false) {

        $this->log('Migration process starting');

        if ($test)
            $this->log('Test mode ENABLED');

        $mode = 'up';

        $current_version = 0;

        $versions = $this->getSchemaVersions(true);

        $file = new \Hazaar\File($this->schema_file);

        if (!$file->exists())
            throw new \Exception("This application has no schema file.  Database schema is not being managed.");

        if (!($schema = json_decode($file->get_contents(), true)))
            throw new \Exception("Unable to parse the migration file.  Bad JSON?");

        if(!array_key_exists('version', $schema))
            $schema['version'] = 1;

        if ($version) {

            /**
             * Make sure the requested version exists
             */
            if (!array_key_exists($version, $versions))
                throw new \Exception("Unable to find migration version '$version'.");

        } else {

            if (count($versions) > 0) {
                /**
                 * No version supplied so we grab the last version
                 */
                end($versions);

                $version = key($versions);

                reset($versions);

                $this->log('Migrating database to version: ' . $version);

            } else {

                $version = $schema['version'];

                $this->log('Initialising database at version: ' . $version);

            }

        }

        /**
         * Check that the database exists and can be written to.
         */
        try {

            $this->createInfoTable();

        }
        catch(\PDOException $e) {

            if ($e->getCode() == 7) {

                $name = $this->config->dbname;

                throw new \Exception("Database '$name' does not exist.  Please create the database with owner '{$this->config->user}'.");
            }

            throw new \Exception($e->getMessage());
        }

        /**
         * Get the current version (if any) from the database
         */
        if($this->tableExists('schema_info')){

            if ($result = $this->table('schema_info')->find(array(), array('version'))->sort('version', true)) {

                $row = $result->fetch();

                $current_version = $row['version'];

                $this->log("Current database version: " . ($current_version ? $current_version : "None"));

            }

        }

        /**
         * Check to see if we are at the current version first.
         */
        if ($current_version == $version) {

            $this->log("Database is already at version: $version");

            return false;

        }

        $this->log('Starting database migration process.');

        if (!$test)
            $this->beginTransaction();

        $committed_versions = array();

        if (!$current_version && $version == $schema['version']) {

            /**
             * This section sets up the database using the existing schema without migration replay.
             *
             * The criteria here is:
             *
             * * No current version
             * * $version must equal the schema file version
             *
             * Otherwise we have to replay the migration files from current version to the target version.
             */

            if (count($this->listTables()) > 1)
                throw new \Exception("Tables exist in database but no schema info was found!  This should only be run on an empty database!");

            /*
             * There is no current database so just initialise from the schema file.
             */
            $this->log("Initialising database" . ($version ? " at version '$version'" : ''));

            if ($schema['version'] > 0) {

                foreach(ake($schema, 'tables', array()) as $table => $columns) {

                    $this->log("Creating table '$table'.");

                    if (!$test)
                        $this->createTable($table, $columns);

                }

                foreach(ake($schema, 'indexes', array()) as $table => $index_info) {

                    $this->log("Creating index on '$table'.");

                    if (!$test)
                        $this->createIndex($index_info, $table);
                }

                $committed_versions[] = $schema['version'];
            }

        } else {

            if (!array_key_exists($current_version, $versions))
                throw new \Exception("Your current database version has no migration source.");

            $this->log("Migrating from version '$current_version' to '$version'.");

            if ($version < $current_version) {

                $mode = 'down';

                krsort($versions);

            }

            $source = reset($versions);

            $this->log("Migrating $mode");

            do {

                $ver = key($versions);

                /**
                 * Break out once we get to the end of versions
                 */
                if (($mode == 'up' && ($ver > $version || $ver <= $current_version)) || ($mode == 'down' && ($ver <= $version || $ver > $current_version)))
                    continue;

                if ($mode == 'up') {

                    $this->log("Replaying version '$ver' from file '$source'.");

                } elseif ($mode == 'down') {

                    $this->log("Rolling back version '$ver' from file '$source'.");

                } else {

                    throw new \Exception("Unknown mode!");

                }

                if (!($current_schema = json_decode($source->get_contents(), true)))
                    throw new \Exception("Unable to parse the migration file.  Bad JSON?");

                if (!$this->replay($current_schema[$mode], $test))
                    throw new \Exception("An error occurred replaying the migration script.");

                $committed_versions[] = $ver;

            } while($source = next($versions));

        }

        foreach($committed_versions as $ver) {

            if ($mode == 'up') {

                $this->log('Inserting version record: ' . $ver);

                if (!$test)
                    $this->insert('schema_info', array('version' => $ver));

            } elseif ($mode == 'down') {

                $this->log('Removing version record: ' . $ver);

                if (!$test)
                    $this->delete('schema_info', array('version' => $ver));

            }

        }

        if ($test || $this->commit()) {

            $this->log('Migration completed successfully.');

            return true;
        }

        throw new \Exception('Migration failed when committing transaction.');

    }

    /**
     * Reply a database migration schema file
     *
     * This should only be used internally by the migrate method to replay an individual schema migration file.
     *
     * @param array $schema
     *            The JSON decoded schema to replay.
     */
    private function replay($schema, $test = false) {

        foreach($schema as $action => $data) {

            foreach($data as $type => $items) {

                foreach($items as $item_name => $item) {

                    switch ($action) {

                        case 'create' :

                            $this->log("Creating $type item: $item[name]");

                            if ($test)
                                continue;

                            if ($type == 'table')
                                $this->createTable($item['name'], $item['cols']);

                            else
                                $this->log("I don't know how to create {$type}s!");

                            break;

                        case 'remove' :
                            $this->log("Removing $type item: $item");

                            if ($test)
                                continue;

                            if ($type == 'table')
                                $this->dropTable($item);

                            else
                                $this->log("I don't know how to remove {$type}s!");

                            break;

                        case 'alter' :
                            $this->log("Altering $type $item_name");

                            if ($type == 'table') {

                                foreach($item as $alter_action => $columns) {

                                    foreach($columns as $col) {

                                        if ($alter_action == 'add') {

                                            $this->log("Adding column '$col[name]'.");

                                            if ($test)
                                                continue;

                                            $this->addColumn($item_name, $col);
                                        } elseif ($alter_action == 'drop') {

                                            $this->log("Dropping column '$col'.");

                                            if ($test)
                                                continue;

                                            $this->dropColumn($item_name, $col);
                                        }
                                    }
                                }
                            } else {

                                $this->log("I don't know how to alter {$type}s!");
                            }

                            break;

                        case 'rename' :
                            $this->log("Renaming $type item: $item[from] => $item[to]");

                            if ($test)
                                continue;

                            if ($type == 'table')
                                $this->renameTable($item['from'], $item['to']);

                            else
                                $this->log("I don't know how to rename {$type}s!");

                            break;

                        default :
                            $this->log("I don't know how to $action {$type}s!");

                            break;
                    }
                }
            }
        }

        return true;

    }

    /**
     * Logs a message to the migration log.
     *
     * @param string $msg
     *            The message to log.
     */
    private function log($msg) {

        $this->migration_log[] = array(
            'time' => microtime(true),
            'msg' => $msg
        );

    }

    /**
     * Returns the migration log
     *
     * Snapshots and migrations are complex processes where many things happen in a single execution. This means stuff
     * can go wrong and you will probably want to know what/why when they do.
     *
     * When running \Hazaar\DBI::snapshot() or \Hazaar\DBI::migrate() a log of what has been done is stored internally
     * in an array of timestamped messages. You can use the \Hazaar\DBI::getMigrationLog() method to retrieve this
     * log so that if anything goes wrong, you can see what and fix it/
     */
    public function getMigrationLog() {

        return $this->migration_log;

    }

    /**
     * Prepared statements
     */
    public function prepare($sql, $name = null) {

        $statement = $this->driver->prepare($sql);

        if ($name)
            $this->statements[$name] = $statement;
        else
            $this->statements[] = $statement;

        return $statement;

    }

    public function execute($name, $input_parameters) {

        if (!($statement = ake($this->statements, $name)) instanceof \PDOStatement)
            return false;

        if (!is_array($input_parameters))
            $input_parameters = array(
                $input_parameters
            );

        return $statement->execute($input_parameters);

    }

    public function getPreparedStatements() {

        return $this->statements;

    }

    public function listPreparedStatements() {

        return array_keys($this->statements);

    }

}

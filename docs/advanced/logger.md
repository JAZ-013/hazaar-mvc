# The Logger

HazaarMVC has a built-in frontend/backend logging system. The frontend allows a consistent interface into multiple logging backends.

## Configure the logger

You will configure the logger in your application.ini config file by adding the below options.

### Turn on application logging

```json
{
    "development": {
        "log": {
            "enable": true
        }
    }
}
```

This will turn on logging and by default it will log to a file at /tmp/hazaar.log. We log to the tmp directory because this is common to all Unix systems and should be writable by anyone.

### Select a backend

```json
{
    "development": {
        "log": {
            "enable": true,
            "backend": "firebug"
        }
    }
}
```

This will use the firebug logger backend.

### Set the log level

When you write to the log you can specify a log level. If that level is less than the log level set in the config, the message will be written.

```json
{
    "development": {
        "log": {
            "enable": true,
            "backend": "firebug",
            "level": 0
        }
    }
}
```

### Writing log messages

You can write to the log from anywhere in your application. HazaarMVC provides an alias to the logger frontend called log.

## Example Usage

### Configuration

```json
{
    "development": {
        "log": {
            "enable": true,
            "backend": "file",
            "logfile": "/var/log/apache2/hazaar.log",
            "write_ip": false,
            "level": 3
        }
    }
}
```

### Calling log::write

```php
log::write("This is my log message", 2);
```

This message will be logged because the log level (2) is less than the level set in our config (3).

If you are using namespaces, remember that the log alias is in the root namespace so you will need to prefix it with a backslash for it to find it.

```php
namespace Application\Model\Stuff;
\log::write("This is my log from inside a namespace");
```

## Frontend Options

The frontend doesn't have many options.

* enable - Enable logging (eg true)
* backend - The namd of backend to use (eg: firebug)
* level - The log level to set (eg: 2)

All other options are for each specific backend. The following logging backends are available:

* file - Logs directly to a file on disk.
* database - Logs to a PDO database table.
* mongodb - Logs to a MongoDB collection.
* firebug - Logs to the HTTP response header in a special format recognised by the Firebug/FirePHP Firefox plug-in.
* chain - Logs to multiple backends at once.

## Backend Options

### File

#### Parameters:

* logfile - The file to log messages to (default: /tmp/hazaar.log)
* write_ip - Log the IP of the client (default: true)
* write_timestamp - Log the message timestamp (default: true)
* write_uri - Log the URI of the client (default: true)

### Firebug

#### Parameters: None

This backend has no parameters as it is designed to be used during the development process. Because of this there's no need to allow the option to log extra data like client IP, timestamp, etc as Firebug will already have all of this.

The firebug backend requires that the Firefox plugins firebug and firePHP are installed in the browser.

### Database Logging

#### Parameters:

* driver - The PDO driver to use (required)
* host - The host to connect to (required)
* username - The username to use for authentication
* password - The password
* database - The database to connect to (required)
* table - The table to log to (default: log)
* write_ip - Log the IP of the client (default: true)
* write_timestamp - Log the message timestamp (default: true)
* write_uri - Log the URI of the client (default: true)
* Logs to a PDO database table. You must first create the log table using the below SQL query.

Currently we only have an example for PostgreSQL but you can easily create the same table in MySQL or whatever other PDOdatabase you are using.

```sql
CREATE TABLE log
(
  id serial NOT NULL,
  tag text,
  message text,
  remote inet,
  "timestamp" timestamp with time zone,
  level integer,
  uri text,
  CONSTRAINT log_pkey PRIMARY KEY (id )
)
WITH (
  OIDS=FALSE
);
ALTER TABLE log
  OWNER TO hazaar;
```

### MongoDB

#### Parameters:

* hostname - The MongoDB host (required)
* database - The database to use (default: hazaar_default)
* collection - The collection to write to (default: log)
* write_ip - Log the IP of the client (default: true)
* write_timestamp - Log the message timestamp (default: true)
* write_uri - Log the URI of the client (default: true)

### Chain

* backend - Array of backend names to write to
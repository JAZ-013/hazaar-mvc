# Caching Data

## Introduction

Hazaar has a full featured caching system with multiple frontends and backends which are designed to work in any combination. It is possible to cache just about anything using the caching classes. Using a cache system to store frequently retrieved data can drastically increase the performance of your application. Most cache backends also support inter-session cache so data can be cached in one session and accessed via another, so that cached data can benefit the application as a whole and not just the current client session.

The cache system is implemented as a collection of frontends and backends. Frontends dictate the way in which the cache system is accessed by the application and enforce data lifetime expiration. For example, the basic frontend exposes the `load()` and `save()` methods for loading and saving data, while the func frontend exposes as `call()` method. Backends on the other hand, dictate how and where the data is actually stored and the mechanism used to expire stale data.

### Caching Theory

There are three elements to consider when using the Hazaar caching system. The first is the cache key, which is a unique string identifier that is used to identify cache records. The second is the cache lifetime which states how long the cached data is considered to be 'fresh'. The third element to consider is the application execution path so that parts of the application can be skipped entirely if cached data is available which is the key to boosting performance.

Frontends are designed to return false if no cached data is available and this can be used in test conditions by the application to determine if cached data is available and code execution should be skipped. If no cached data is available it is up to the application to generate the data itself and then store the data in the cache before carrying on.

### Available Frontends

* [[Hazaar\Cache]] - The basic frontend is for storing key/value pairs. Keys are alpha-numeric but values can be anything that can be serialized, including class objects (as long as they are loaded in the correct context) (Supports Timeout)
* [[Hazaar\Cache\Func]] - The function frontend is for storing the result of a function call. This frontend allows you to make a consistent function call via the caching object but the function will only be executed if cached data is available and not expired. If the function returns an object, then the object can only be stored if it is serializable. (Supports Timeout)
* [[Hazaar\Cache\Output]] - The output frontend is used to cache controller responses. The entire execution path of a controller can be skipped if the output has already been generated using this cache frontend. This is useful for caching the entire output of, for example, a report that takes some time to generate. (Supports Timeout)

### Available Backends

* [[Hazaar\Cache\Backend\Session]] - The session backend will store caching data in the current users session using the $_SESSION global variable. The session cache backend is considered the simplest backend for caching data but has the drawback that cached data is only accessible by the current user session and can not be shared amongst sessions.
* [[Hazaar\Cache\Backend\File]] - The file backend will store caching data in the system temporary directory (usually /tmp). Files are plain text serialized data which stores cache data along with caching information like a timestamp used to calculate the expiration timeout. The file cache backend is considered the simplest backend for caching data that is accessible between sessions.
* [[Hazaar\Cache\Backend\APC]] - This backend uses the Advanced PHP Caching module available for PHP. APC has a 'user cache' function that this backend takes advantage of to store data. The APC module supports automatic cache expiration, stores information in active memory and is considered the fastest backend to use for caching.
* [[Hazaar\Cache\Backend\Redis]] - The Redis backend stores cached data with a Redis server. Redis is caching for serious, large-scale applications.
* [[Hazaar\Cache\Backend\Memcached]] - The memcached backend stores cached data with a memcached server. Memcached is caching for medium-scale applications.
* [[Hazaar\Cache\Backend\Database]] - The database backend can store caching information in a PDO database table.
* [[Hazaar\Cache\Backend\Sqlite]] - This backend will store data in a persistent SQLite database that is stored in the Hazaar library runtime directory.
* [[Hazaar\Cache\Backend\Chain]] - The chaining backend allows multiple backends to be used for storing cached data. This allows a mix of backends to be used as persistent backends usually have lower performance as compared to non-persistent backends. Using chaining, a high performance backend can be used as the primary and a low performance persistent backend can be used to protect cached data. The chain backend can be used by simply providing an array of backends to use to the [api:Hazaar\Cache] object.

## Example Usage

Below is an example of how to use the [[Hazaar\Cache]] class to cache some data from a database query using the [[Hazaar\Cache\Backend\Redis]] backend.

```php

$cache = new Hazaar\Cache('redis');

if(!($result = $cache->get('dbi_result'))){

    $db = new Hazaar\DBI\Adapter();

    $result = $db->mytable->findOne(array('id' => 1234));

    $cache->set('db_result', $result);

}

return $result;
```
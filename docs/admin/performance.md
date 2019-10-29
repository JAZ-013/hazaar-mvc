# Optimising for Performance

Hazaar has some built-in performance options that can greatly reduce page load times and TTFB (time to first byte).  These are not enabled by default as many of them rely on optional PHP modules, such as APCu.

## Configuration Caching and APCu

Loading and parsing configuration files from the hard disk on every request is not the most efficient way of doing things, but unfortunately it is necessary.  Add to this process the possibility of a virtually unlimited number of external configuration include files and things can get heavy when trying to load all these files on EVERY request.  Under normal operating circumstances the operating system itself will alleviate some of this strain, but the best way to beef up performance here is to just not try and load off the disk at all.  

**APCu** is the Advanced PHP Cache module that enables in-memory caching of user data.  Hazaar MVC will leverage this to store an already loaded and parsed configuration files (including it's include files) in a shared memory location so that config files are processed only once (unless modified).  The config is then loaded from cache for all subsequent requests, regardless of user, host, sessions, threads, processes or any other mechanism that would normally segregate stored data.

### Enabling APCu Configuration Cache

This could not be easier.  Simply enable the *php_apcu* module in your PHP configuration and that's it.  Hazaar MVC will recognise this and start using it automatically.

Normally this is as simple as adding the following line to your *php.ini* file:

#### Linux
```
extension=php_apcu.so
```

#### Windows
```
extension=php_apcu.dll
```
# Setting your locale

For some functions of Hazaar to work correctly you will need to ensure that your locale is set up correctly. By default the locale for Apache 2 is probably ‘C’, which is ok for a generic approach to things, doesn’t tell us much when it comes time to dealing with things like money.

There are a number of methods for setting the locale depending on what you are trying to do and how you want to affect the running of your server.

## Setting the Locale for your application (recommended)

To set the locale for your application ONLY, you can simply add the following line to your application.ini file if it is not already there.

```ini
app.locale = 'en_AU'
```

!!! Note
    Locales have changed a bit in PHP over the last few releases. Remember that these locales now need to haveUTF-8 or ISO-8859-1 appended
    to them. Previously you could simply refer to en_AU. This may still work, but if it doesn’t try en_AU.ISO-8859-1 or en_AU.UTF-8 for unicode.

!!! Warning
    This locale MUST be enabled on your server. If it is not, then the call will fail and you will receive an error.

## Setting the Locale in Apache

As stated, the default locale for Apache 2 is usually ‘C’. This is because it is generic and covers most country applications (I assume). It is then expected that you set your locale in your application (see above).

However, you can set the locale for your entire Apache instance if you choose. To set the locale for your entire Apache instance just look in the file /etc/apache2/envvars and either set the LANG variable to your locale (such as en_AU, or en_US), or set it to use your default system locale by uncommenting the line that loads the locale from /etc/default/locale.

!!! Note
    This will set the locale for your entire Apache server and affect everything running on it. However if the application sets a locate separately
    (as above) then that will override the default locale. Setting the Apache locale will just make sure a locale is set by default.

!!! Danger
    I can’t stress this enough so I’ll repeat. These locales MUST be enabled on your server. If they are not, then the call will fail and you will get an error.

## Setting System Locale on Debian

Just as an aid to get some people going, here’s how to set a locale on a debian-based system (including ubuntu, etc).

Just run sudo dpkg-reconfigure locales and select the locales you want enabled on your system. This process will also rebuild the locale database for you. If that fails because you don’t have the locales package installed (really, you should have it) then install it by:

```shell
# sudo apt-get update
# sudo apt-get install locales
```
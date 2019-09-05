# Project Layout

Before you start developing your first application, you need to understand the layout of the Hazaar MVC application directory. You will have a project root, which will usually be the name of your project (if you used the The Config Tool to create it. Inside that directory you will have application, library and public directories which define the core structure of a HazaarMVC application.

HazaarMVC has a project layout like most other MVC frameworks. The project root must look like this to work correctly:

```
Project Root
|
|--application
|   |
|   |--configs - Your application configuration files.
|   |
|   |--controllers - This is where your controllers go.
|   |
|   |--models - This is where your models go.
|   |
|   |--views - This is where your views go.
|
|--library
|   |
|   |--Hazaar - A link to the HazaarMVC library, normally /usr/share/libhazaar-framework
|
|--public
    |
    |--index.php - This is a very important file and should not be modified.  It is the entry point into your application.  For now, don't touch it unless  you really know what you're doing.
```

The Hazaar Tool has been provided to make creating and setting up a new project a one step process.

## Layout Explanation

### application

This is the application directory that contains all the models, views and controllers for your application. This is where you will do all of your work. 

### library 

!!! warning
Since moving to composer libraries this directory has been **deprecated** and this documentation will be updated at some point.

This is where library files can be stored or linked. By default it has a link back to the Hazaar MVC library directory where you installed Hazaar MVC. For more advanced projects you will be able to store other 3rd part libraries in here and the Hazaar MVC autoloader will be able to find them. For now, you probably won't want to mess with this and it should just work as is. 

### public 

This is where you point your web server configuration to for the document root. It will contain two files. index.php and .htaccess. Don't mess with either unless you really know what you are doing and need to set up some sort of custom execution path.
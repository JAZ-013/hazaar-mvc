# Application Controllers

## Basic Controllers

Coming soon(ish)...

## Action Controllers

Coming soon(ish)...

## Error Controllers

It is possible to create custom error controllers that you have full control over the look and feel over. While the built-in error controller is great for rapidly prototyping your projects, it is not customisable in any way. This is by design so that a consistent error handling path can be defined for even the most fatal of errors.

At some point you are going to want to display your errors with a custom error controller so that you can use your own views and layouts for a seamless user experience. Fortunately this is VERY easy with Hazaar and can be done in only two steps.

### Step 1 - Define an error controller

You define an error controller just like any other controller in your application by creating a file in the application/controllers directory that contains a controller class. The difference is the method names that you need to use. Because we are handling an error and not executing an action, only 5 method names are supported and are based on the type of response that needs to be given. The possible methods are:

* html() - This is called when the response should be a Hazaar\Controller\Response\Html object. This is the method that will pretty much be called all the time as it is how you define the unique look and feel of your application to the user.
* json() - This is called when the response should be a Hazaar\Controller\Response\Json object. JSON responses will not normally need to be handled as the default error controller should provide adequate handling. You can however use this method if you want to change how JSON request errors are handled.
* xmlrpc() - This is called when the response should be a Hazaar\Controller\Response\Xml object. Similar to theJSON response except this is called when the original controller executed was an XML-RPC controller. See: XMLRPC for more information on XML-RPC controllers.
* text() - This is called when the response should be a Hazaar\Controller\Response\Text object. Use this if you just want to return plain text as the error response.
* run() - This is a generic catch all method that will be executed if an appropriate response method does not exist. If you decide to use this, be careful as you will have to handle all the response types manually.

Here, we will create a new error controller aptly named ErrorController.

```php
class ErrorController extends \Hazaar\Controller\Error {

    protected function html() {

        $out = new \Hazaar\Controller\Response\Layout('application');
  
        $out->addHelper('bootstrap');
  
        $out->add('error');
  
        $out->code = $this->code;
  
        $out->message = $this->errstr;
  
        return $out;
  
    }

}
```

In the above controller we are using a Layout controller response. This is appropriate because the Layout controller response is extended from the Html controller response (as is the View controller response). This means we can use our standard application.phtml file as the response and keep our application look and feel. Then we inject a view into the layout, in this case the 'error' view, and our error will be displayed as though it is part of our application.

Because we are using a layout view we also need to create a custom error view. We will just call this 'error', so we create the file application/views/error.phtml and put the following content in there.

```php
<h1>A <?=$this->code;?> error occurred</h1>
<h3><?=$this->message;?></h3>
```

### Step 2 - Configure the application

The next step is simple. All you need to do now that you have built your custom error controller is tell Hazaar that you want to use it instead of the default controller. We do this in the application.ini file by adding the following configuration directive:

```json
{
  "development": {
    "app": {
      "errorController": "Error""
    }
  }
}
```

Where 'Error' is the name of our controller.

!!! remember
    The actual name of the controller is not the same as the name of the class. The name is the part of the class name before Controller.

Using the above code in our example application that is provided with Hazaar, if you were to navigate to a page that doesn't exist you should now get something like this:

## That's it!

With your error controller now built you can pretty much do whatever you want with your errors. Just keep in mind that if you throw an error inside your error controller, an error loop may occur. Hazaar will protect you from these as best it can however.
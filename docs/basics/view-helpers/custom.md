# Custom View Helpers

Custom view helpers allow application developers to implement their own view helpers.  This is now the recommended way of implementing re-usable code that directly generates HTML output.  Custom view helpers are implemented in the exact same way as the built-in view helpers and therefore have access to all the same functionality.

!!! warning
It is possible to override built-in view helpers by creating custom view helpers with the same name.  This is by design to allow developers that are not happy with a built-in view helper to create their own implementations.  However this can also not be what was intended so *be careful* when selecting custom view helper names.

## Creating a custom view helper

By default, custom view helpers are stored in the *helpers* application directory in a sub-directory names *views* (in future there may be other types of helpers).  If you do no have a *helpers/view* directory in your application directory you will need to create that before continuing.

Create a new *.php* file in the *helpers/view* directory called *Example.php* (note the first uppercase character as per the standard naming convention).  Custom view helpers need to exist in the application namespace so set the namespace to `Application\Helper\View` and create a new class called `Example` that extends the `Hazaar\View\Helper` abstract base class.

```php
<?php

namespace Application\Helper\View;

class Example extends \Hazaar\View\Helper {

  public function tag($label){

    return $this->html->div(array($this->html->h1('Example Tag'), $this->html->div($label)));

  }

}
```

The above view helper provides a method names `tag()` that takes a sinle argument and returns a DIV object that contains a H1 header and another DIV containing the label.  This is a very simplistic example but it is enough for this demonstration.

## Using a custom view helper

Using a custom view helper is EXACTLY the same as using a built-in view helper.  So to use our new *example* view helper we add it to our view. 

```php
<?php

class MyController extends \Hazaar\Controller\Action {

    public function index(){

        $this->view('index');

        $this->view->addHelper('example');

    }

}
```

Once the view helper has been added to the view we can then access the methods it provides as a normal view helper:

```php
<h1>Example</h1>
<?=$this->example->tag('Hello, World');?>
```

# View Helpers

View helpers provide a way to programatically generate views and layouts using PHP.  A helper can be created that provides one or more functions that returns a displayable object such as a `Hazaar\Html\Div`, `Hazaar\Html\Span` or any other object derrived from the `Hazaar\Html\Element` class.

There are a number of view helpers built into Hazaar MVC, such as:

* `html` for easily generating HTML objects programatically, simplifying the interaction between PHP code, variables and the final HTML output.
* `jquery` which easily configures jQuery for use in a view by automatically linking the JavaScript library required and adding custom JavaScript code.
* `fontawesome` will link the FontAwesome library to the selected view, optionally allowing a specific version to be selected.
* `application` which provides direct access to the application object.  While this does not return displayable object it is useful for accessing the application configuration.

Applications can also implement their own [Custom View Helpers](custom.md).

## Adding a View Helper

Adding a view helper is done in the controller, usually around the same time the view is being selected.  This is done by calling `$this->view->addHelper('name')` on the controller.

For example, to link a view and add the Font Awesome view helper:

```php
<?php

class MyController extends \Hazaar\Controller\Action {

    public function index(){

        $this->view('index');

        $this->view->addHelper('fontawesome');

    }

}
```

### Optional Arguments

Some view helpers will support initialisation arguments.  This depends on the view helper itself so you will need to review the documentation for the view helper you are adding.

For example, the FontAwesome view helper supports selecting a specific version (the latest version is selected by default).  To select the version we provide an options array that contains a `version` element with a value of the version we want to use.

```php
<?php

class MyController extends \Hazaar\Controller\Action {

    public function index(){

        $this->view('index');

        $this->view->addHelper('fontawesome', array('version' => '5.0.0'));

    }

}
```

## Using a view helper

View helpers are accessible from within the view context, which means that all you need to do is call methods on `$this->viewhelpername` from inside your view source file.

For example, to use the HTML view helper to generate a div that uses a view data variable:

```php
<h1>Example</h1>
<?=$this->html->div($this->myString)->class('example-class');?>
```

!!! note
The HTML view helper uses the `Hazaar\Html\Element` child classes which support chaining.  This is a feature of the HTML classes, not of the view helpers however, it is suggested that view helpers output `Hazaar\Html\Element` classes where possible.
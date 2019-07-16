# Multiple Class Inheritance

Among the current limitations of PHP, one of the most annoying is you can't have a class extend more than one class. To palliate this limitation and to make the framework truly extendible, Hazaar introduces a class called [[Hazaar\Extender]]. The [[Hazaar\Extender]] class is a special class that allows you to extend your classes using multiple child classes.

!!! warning

Hazaar MVC implements a more tradition form of multiple class inheritance over PHP 5.4 'Trait' feature which attempts to implement similar functionality.

!!! warning

Traits do not as accurately honour the private/protected/public member declaration but the Hazaar MVCExtender class does. The Extender class also allows you to extend from multiple classes without the need to explicitly define them as traits which makes them much more flexible.

## Understanding Multiple Inheritance

Multiple inheritance is the ability for a class to extend more than one class and inherit these class properties and methods. Let's consider an example. Imagine a Story and a Book class, each with its own properties and methods:

```php
class Story {
    protected $title = '';
    protected $topic = '';
    protected $characters = array();

    public function __construct($title = '', $topic = '', $characters = array()) {
      $this->title = $title;
      $this->topic = $topic;
      $this->characters = $characters;
    }

    public function getSummary() {

      return $this->title.', a story about '.$this->topic;
    }
}

class Book {
    protected $isbn = 0;

    function setISBN($isbn = 0) {
        $this->isbn = $isbn;
    }

    public function getISBN() {
        return $this->isbn;
    }
}
```

A ShortStory class extends Story, a ComputerBook class extends Book, and logically, a Novel should extend both Story and Book and take advantage of all their methods. Unfortunately, this is not currently possible in PHP. You can **NOT** write the Novel declaration as:

```php
class Novel extends Story, Book {
}

$myNovel = new Novel();
$myNovel->getISBN();
```

!!! Danger

This is wrong, so <b>DO NOT</b> do this.

One possibility would be to have Novel implements two interfaces instead of having it extend two classes, but this would prevent you from having the methods actually written in the parent classes.

Why would you use this instead of PHP traits?

Traits differ quite a lot in the way they are implemented in an attempt to solve the multiple inheritance problem. You would use the Hazaar 

Extender class if:

* You are using a version of PHP that doesn't support traits, such as 5.3 or older.
* You are trying to extend two or more standard classes. That is, they have NOT been declared as traits.
* You like things to be consistent, and implemented in a logical manner.

PHP traits is only new to PHP 5.4, so if you are on PHP 5.3 or older you can use the Extender class to implement similar functionality.
Traits also require that child classes be explicitly declared as a trait, meaning you can not use a standard class definition as a trait. If you want to extend two or more standard classes then you should use the Hazaar Extender class instead of traits.

## Using the Extender

The Extender class takes another approach to the problem, taking an existing class and extending it a posteriori. The process involves two steps:

* Declaring a class as extendible by extending it from the Extender class
* Registering child classes in the parent class constructor

Here is an example of using the Extender:

```php
class Novel extends \Hazaar\Extender {
    public function __construct(){

        parent::extend('Story');
        parent::extend('Book');
    }
}

$myNovel = new Novel();
$myNovel->getISBN();
```

You declare your class as normal, extending it from Hazaar\Extender, in line with PHP's ability to only inherit from one class. The Novel class is declared as extendible by the code located in the `__construct()` method. The method of the child classes (Story and Book) are added afterwards to the Novel class by each call to `parent::extend()`. The next sections will explicitly explain this process. When the `getISBN() `method of the Novel class is called, everything happens as if the class had been defined with multiple class inheritance, except it's the magic of the `Extender::__call()` method that just simulates this. The `getISBN()` method is added to the Novel class.

## Declaring a Class As Extendible

To declare a class as extendible, you simply extend the Hazaar\Extender abstract class and call it's `extend()` method with the name of the class you want to extend as the first parameter, and any other parameters to pass to the child class constructor. For safety reasons I suggest using `parent::extend()` to make sure you access the Extender method and not a local method.

!!! Notice

Any class that is declared as abstract can still be extended using this method. Internally, a "wrapper class" is used to allow extending abstract classes.

To extend the 'A' class and pass a string to the constructor:

```php
class AB extends \Hazaar\Extender {
    function __construct(){
        parent::extend('A', 'Hello, World');
    }
}
```

Order is very important when calling `extend()`. Classes are added in the order in while they are called with `extend()` and methods will not clobber existing methods. This means that if you are extending class A and then class B and both have a member named `getText()`, then only the first class added will expose it's `getText()` method (in this case, class A).

```php
class A {
    public function getText(){
        return 'This is class A';
    }
}

class B {
    public function getText(){
        return 'This is class B';
    }
}

class AB extends \Hazaar\Extender {
    function __construct(){
        parent::extend('A');
        parent::extend('B');
    }
}
$ab = new AB();
echo $ab->getText();
```

This example will output the string:

```
This is class A
```

## Member Accessibility

A lot of work has gone into making sure that class members behave as you would expect. That is, only public members are accessible from outside the object. Private members are only accessible from inside the class definition they are defined in. Protected members are only accessible from inside the instantiated object and not from outside. This goes for both methods and variables.

This is in line with standard PHP object oriented access methodology.
----------------
QUICKSTART GUIDE
----------------

### Requirements

PHP 5.3+ ... and a text-editor.

### Download

Git (latest stable release):

    $ mkdir artax
    $ cd artax
    $ git init
    $ git pull git@github.com:rdlowrey/Artax.git

### Installation

Specify the application-wide debug level and require the Artax.php bootstrap.

    <?php
    define('AX_DEBUG', 1); // acceptable values: 0, 1, 2
    require '/hard/path/to/Artax.php';

### Once the Boot Process Completes

After including the Artax.php bootstrap file you'll have two variables in the
global namespace:

  - `$provider` - An instance of the `Artax\Provider` injection container
  
  - `$mediator` - An instance of the `Artax\Mediator` event mediator

You should use the public interfaces of these two objects to build your app
by registering event listeners and defining dependency injection definitions.


### Read the Wiki!!!

  - Familiarize yourself with [Artax Error Handling][wiki-errs]. If you don't,
  you'll probably break your app and wish you had read up on what *not* to do 
  regarding error/exception/shutdown handling in Artax.
  
  - Learn how to use `Artax\Mediator` to lazy-load dependency-injected, fully
  testable class event listeners. Also read how you can use events to write
  function and lambda-based code on the [Event Management wiki page][wiki-events].
  
  - Learn how `Artax\Provider` helps eliminate `static`, `global`, Singleton
  and any other global state in your code at the [Dependency Injection wiki page][wiki-deps].

### Epilogue

Artax was created to help PHP programmers grow up and write better code:
code that doesn't use `static` and drift into class-based bastardizations
of good OOP practices. Please don't use Artax for evil. **DO NOT** inject
the `Artax\Provider` dependency injection container into your classes and
turn it into a Service Locator anti-pattern. Also, source code is the 
definitive documentation. The Artax source is very well-documented. Use it.
There are only ~325 lines of testable code, so if you're going to build an
application on Artax, do yourself a favor and understand the foundation.


[wiki-events]: https://github.com/rdlowrey/Artax/wiki/Event-Management "Artax Event Management"
[wiki-deps]: https://github.com/rdlowrey/Artax/wiki/Dependency-Injection "Artax Dependency Injection"
[wiki-errs]: https://github.com/rdlowrey/Artax/wiki/Error-Management "Artax Error Management"

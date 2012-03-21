### WHAT IS IT?

**Artax** is a lightweight scaffold for creating evented PHP applications. At 
~300 lines of testable code, the Core library isn't itself a framework. We are,
however, working on an HTTP submodule to layer a 100% REST-compliant application
framework on top of the Artax Core.

###### REQUIREMENTS

PHP 5.4+ is required as Artax makes use of the following features not available
prior to 5.4:

* Short array syntax
* Array and constructor dereferencing

###### INSTALLATION

Download the package and require the bootstrap at the top of your PHP files.

```
require '/hard/path/to/bootstrap/file/Artax.php';
```

### BACKGROUND

In 2006 Rasmus Lerdorf, the creator of PHP, [lamented the ubiquity of sprawling,
monolithic PHP frameworks](http://toys.lerdorf.com/archives/38-The-no-framework-PHP-MVC-framework.html):

> _"Nothing is going to build your application for you, no matter what it promises.
You are going to have to build it yourself ... Clean separation of your views,
controller logic and backend model logic is easy to do with PHP. Using these
ideas, you should be able to build a clean framework aimed specifically at your
requirements instead of trying to refactor a much larger and more complex
external framework."_

The "baggage" Lerdorf describes as packaged with many PHP frameworks is a
primary inspiration for Artax.

###### PROJECT GOALS

* Allow both event-driven and linear cause/effect application design;
* Integrate simple, built-in dependency injection;
* Lazy-load anything that can be put off without performance penalties;
* Eschew the use of `static` entirely in favor of maximum testability and 
full API transparency;
* Favor the object-orientation required for complex application management while 
still allowing cowboy-coders to use closure like a boss for function-based code;
* Create a super-lightweight web application layer that's 100% compliant with the
specs and spirit of Fielding's Representational State Transfer.

###### WHAT IT DOESN'T DO

The Artax Core provides a basic background for structuring PHP applications. It
aims to do this without:

* Adding unnecessary processing or memory overhead to what you're actually
trying to accomplish;
* Limiting developers to 'lowest common denominator' language features: Artax
is currently built for PHP 5.4 with an eye towards utilizing new language
features as they become available.
* Forcing a new vernacular on its users because, with built-in functionality for
almost everything you can think of, PHP is essentially already a "framework".

###### WHAT'S ON THE HORIZON

We're working on the aforementioned HTTP application layer that more accurately 
fits the "framework" billing.

###### WHAT'S WITH THE NAME?

Children of the 1980s are likely familiar with [The NeverEnding Story](http://www.imdb.com/title/tt0088323/) 
and may remember the scene where Atreyu's faithful steed, Artax, died in the Swamp
of Sadness. The name is an homage to one of the greatest childrens movies of all
time.

### EPILOGUE

The idea of using someone else's code as the basis for your application
is often inherently distasteful. Many of us are drawn to programming
because it affords us the opportunity to do something better or faster than what
other people have done. We didn't sign up, text-editor in hand, just so we could
copy and paste Martin Fowler's or Douglas Crockford's or Fabien Potencier's work. At
the same time, we have to balance our competitive egos with the knowledge that
time is a scarce resource and sometimes the wheel just doesn't require reinventing.
Artax attempts to address the standard, repetitive boilerplate machinations of
enterprise-level PHP applications without the bloat and myriad dependencies 
packaged with many modern frameworks.

If you don't have much experience developing PHP applications, this may not be
the right tool for you. There is no built-in MVC structure, though it should be
trivial to implement an evented MVC application on top of the Artax Core. There
are no "helper" libraries for generating emoticons or HTML forms: just a SOLID, 
readable, documented, 100% unit-tested scaffold for writing evented applications.

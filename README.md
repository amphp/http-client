
> DISCLAIMER: Everything in this repository is in constant flux, so don't draw any
long-term conclusions about how things are currently written. It's all very
experimental; we're constantly trying and rejecting new solutions to old problems.

### WHAT IS IT?

**Artax** is a _lightweight_ micro-framework for creating PHP applications for
web and command line environments. It's me finally getting around to the nebulous
refactoring of my dev toolkit that we all dream about but never get around to.
It's not at all stable and any attempt at using any of the code here should be
considered highly experimental.

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
primary inspiration for **Artax**.

### PROJECT GOALS

* Allow both event-driven and linear cause/effect application design;
* Integrate simple, built-in dependency injection;
* Route HTTP requests by URI and/or HTTP request method allowing
resource-oriented RESTful application design;
* Lazy-load anything that can be put off without performance penalties;
* Eschew the use of `static` entirely in favor of maximum testability and 
full API transparency;
* Favor the object-orientation required for complex application management while 
still allowing cowboy-coders to use closure like a boss for function-based code;

### WHAT IT DOESN'T DO

The framework provides a basic outline for structuring PHP applications. It aims
to do this without:

* Shoving specific or proprietary model and view implementations down your throat;
* Adding unnecessary processing or memory overhead to what you're actually
trying to accomplish;
* Limiting developers to 'lowest common denominator' language features: **Artax**
is currently built for PHP 5.4 with an eye towards utilizing new language
features as they become available.
* Forcing a new vernacular on its users because, with built-in functionality for
almost everything you can think of, PHP is essentially already a "framework".

### WHAT THE HELL DOES THE NAME MEAN?

When I was ten years old I saw [The NeverEnding Story](http://www.imdb.com/title/tt0088323/) 
for the first time. I still haven't gotten over the scene where Atreyu's horse, 
Artax, died in the Swamp of Sadness. The name is an homage to one of my favorite
childhood movies.

### EPILOGUE

The idea of using someone else's code as the entire basis for your application
is often inherently distasteful. I think many of us are drawn to programming
because it affords us the opportunity to do something better or faster than what
other people have done. We didn't sign up, text-editor in hand, just so we could
copy and paste Martin Fowler's or Douglas Crockford's or Fabien Potencier's work. At
the same time, we have to balance our competitive egos with the knowledge that
time is a scarce resource and sometimes the wheel just doesn't require reinventing.
**Artax** attempts to address as many of the standard, repetitive boilerplate
machinations of enterprise-level PHP applications without the bloat and myriad 
dependencies packaged with many modern frameworks.

With **Artax** you'll need to validate your own inputs ... *ahem* ... `filter_var`
... *ahem* ... and implement your own models (likely with `PDO` or a third-party
ORM library). A simple view interface is included to standardize templating. The
built-in view templating class uses PHP because, as they say, *PHP is a templating
language*. Its use, however, is not a requisite.

As a result, if you don't have much experience developing PHP applications, this
may not be the right tool for you. There are no "helper" libraries for generating
emoticons or HTML forms: just a SOLID, readable, documented, *fully-tested*
scaffold for writing good code.

### WHAT IS IT?

**Artax** is a _lightweight_ framework for creating PHP web and CLI applications.
I'm doing lots of work on it at present, so it's not at all stable. You shouldn't
even think about trying to use it in its current state.

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

### WHAT IT DOES

* Routes HTTP requests by address and optional HTTP request method allowing
user-friendly, resource-oriented RESTful applications;
* Lazy-loads application controllers;
* Integrates simple built-in dependency injection;
* Eschews the use of `static` entirely in favor of maximum testability and 
complete API transparency;
* Implements convention over configuration when practical and allows developer
autonomy when feasible;
* Makes all of your wildest dreams come true -- especially the ones about unicorns.

### WHAT IT DOESN'T DO

The framework provides a basic outline for structuring PHP applications in a web
environment. It aims to do this without:

* Specifying the type of models or views to implement;
* Forcing a new vernacular on its users;
* Adding unnecessary processing or memory overhead to what you're actually
trying to accomplish;
* Limiting developers to 'lowest common denominator' language features: **Artax**
is currently built for PHP 5.4 with an eye towards utilizing new language
features as they become available.

### WHAT THE HELL DOES THE NAME MEAN?

When I was ten years old I saw [The NeverEnding Story](http://www.imdb.com/title/tt0088323/) 
for the first time. I still haven't gotten over the scene where Atreyu's horse, 
Artax, died in the Swamp of Sadness. The name is an homage to one of my favorite
childhood movies.

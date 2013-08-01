v0.4.0
------

- Added client interfaces for improved testability. `Artax\Client` now implements
  `Artax\BlockingClient` and `Artax\AsyncClient` implements `Artax\NonBlockingClient`.
- Removed `Client::setResponse` and `AsyncClient::setResponse` methods. These methods were deemed
  unnecessary. The only benefit they provided was the ability to assign cached responses. However,
  the same functionality is easily attainable using composition to inject instances of the
  existing client objects.
- Updated `Artax\Observable` and friends. These changes affect extension authors who must now use
  the new API for observing client event broadcasts.

v0.X.X
------

- Sorry, I couldn't be bothered to provide a CHANGELOG prior to v0.4.0

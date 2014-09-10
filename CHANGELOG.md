#### v0.1.0-beta

- Issue #51: Multipart `FormBody` is no longer broken
- Issue #51: Fix fatal error from bad constant name in example script
- Issue #48: more descriptive progress update messages
- Fixed bug preventing correct implementation of the following option settings:
    . Client::OP_HOST_CONNECTION_LIMIT
    . Client::OP_MAX_QUEUE_SIZE
    . Client::OP_MS_IDLE_TIMEOUT
    . Client::OP_MS_CONNECT_TIMEOUT
    . Client::OP_BINDTO
- `AggregateBody`, `FileBody` and `FormBody` have been updated to use promises in preparation for
  non-blocking filesystem IO support.
- `FormBody::addFileField()` has been renamed to `FormBody::addFile()`. Please update code
  references as needed.
- Added tests

v0.1.0-alpha
----------

- Now functions transparently behind HTTP proxy servers.
- `Artax\Client` is now the only client class (previously `Artax\AsyncClient`)
- The old blocking implementation of `Artax\Client` used for synchronous requests has been removed
  completely. Instead, users may call `wait()` on the `After\Promise` instance returned by
  `Artax\Client::request()`.
- `Artax\Client::request()` now accepts an array of URIs and/or requests. In such cases the return
  value is an array of `After\Promise` instances whose keys match those of the original array.
- All optional settings are now referenced using `Artax\Client::OP_*` constants (previously used string literals)
- Options may now also be assigned on a per-request basis instead of always applying client-wide
- Result callbacks are no longer passed at call-time for async requests; `After\Promise` objects are used instead.
- Cookie handling is now integrated as part of the standard client classes
- Exceptions and error handling have been significantly improved
- Old extension system removed because: garbage.
- Individual request progress updates now use the `After\Promise::watch()` API.
- Issues #14, #25 and #26 have been resolved
- Remove submodules, migrate to composer as the primary dependency resolution strategy
- `Artax\Client` now implements `Artax\HttpClient` interface for simplified testing and composition support
- Updated examples

##### BC BREAKS:

- The breakage in this release is extensive as the entire library has been rewritten. **DO NOT**
  upgrade your application to use this release and expect existing code to "just work." You have
  been warned. The best course of action for those upgrading is to examine the `examples/` directory
  to see how things work with the new version. The plan is to use this new API as the basis for a
  forthcoming v1.0.0. Unless you really need the functionality in this release you should probably
  wait for a better-tested v1 release.

#### v0.7.1

- @TODO Update this ???

v0.7.0
------

- @TODO Update this ???

#### v0.6.2

- @TODO Update this ???

#### v0.6.1

- Updated `Alert` dependency to v0.1.2 for latest bugfixes

v0.6.0
------

- Amp submodule removed in favor of new lightweight Alert reactor dependency. Applications relying
  on the Amp submodule's API for evented code using `Artax\AsyncClient` may require updates.
  Evented applications should replace `Amp\*` references with equivalent `Alert\*` values.

- Added a `combineOutboundCookies` property to the cookies extension which enables combining all
  cookie values into a single header as some servers do not correctly handle multiple `Cookie:`
  headers. This behavior is now enabled by default.

- Added new `expectContinue` client option for auto-adding `Expect: 100-continue` headers to
  requests with an entity body (if no `Expect:` header is already assigned). This option is enabled
  by default.

- `MessageParser` moved into main Artax namespace, PECL parser removed, `Parser` interface removed

- Miscellaneous naming, formatting, documentation, bugfixes and other internal improvements.

##### BC BREAKS:

- Request cookies sent via the Cookies extension are now combined by default instead of being split
  into multiple headers.
- Code relying on the now removed Amp submodule API must update to the new Alert API.

#### v0.5.1

- Fixed bug in cookie extension preventing correct wildcard domain resolution of cookies set using
  the format `.domain.com` for requests to domains of the format `subdomain.domain.com` and
  `domain.com`

v0.5.0
------

- Fixed redundant addition of *Accept-Encoding* headers when reusing the same `Request` object for
  multiple requests.
- Option name change: *allowGzipCompress* -> *autoEncoding*. Artax automatically sets or removes
  the *Accept-Encoding* header for you when this option is enabled (ON by default). If this option
  is disabled Artax will not modify the header in any way. Clients still automatically decompress
  gzipped response bodies (if zlib is enabled and the response contains the appropriate headers)
  regardless of this setting.
- Improved IDE support using explicit method calls when setting client options.
- Removed deprecated `AsyncClient::setResponse` method (which mistakenly survived the v0.4.0 cull).

##### BC BREAKS:

* Option key name change: *allowGzipCompress* -> *autoEncoding* (still enabled by default).

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

##### BC BREAKS:

* The entire API for observable events) has changed and must be updated in extensions migrating to
this version from earlier releases.

v0.X.X
------

- Sorry, I couldn't be bothered to provide a CHANGELOG prior to v0.4.0

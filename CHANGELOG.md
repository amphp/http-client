# Changelog

### 4.6.2

 - Fixed `setBodySizeLimit(0)` with HTTP/2 protocol (#297)

### 4.6.1

 - Fixed `te` request header fields being sent via HTTP/2 instead of being stripped (unless the value is `trailers`)

## 4.6.0

 - Add support for `amphp/file` v2 (#295)
 - Fix some parameter names not aligning with parent classes / interfaces.

### 4.5.5

 - Fixed ALPN setting if unsupported (#283)

### 4.5.4

 - Avoid increasing HTTP/2 window size if too many bytes are buffered locally, avoiding exploding buffers if the consumer is slow.
 - Fix inactivity timeout on HTTP/2 with slow consumers.
   Slowly reading the response shouldn't result in inactivity timeouts if the server is responsive.
 - Check for HTTP/1 connection closing while idle (#279)

### 4.5.3

 - Account for server window changes when discarding data frames
   If streams are cancelled, this might result in hanging connections, because the client thinks the server window is still large enough and doesn't increase it.
 - Fixed potential state synchronization errors with async event listeners
 - Write stream window increments asynchronously, avoiding increments for already closed streams
 - Improved exception messages

### 4.5.2

 - Fixed `ConnectionLimitingPool` closing non-idle connections (#278)

### 4.5.1

 - Retry idempotent requests on `Http2ConnectionException`
 - Fix graceful HTTP/2 connection shutdown
 - Improve behavior if HTTP/2 connections become unresponsive

### 4.5.0

 - Added support for resolving protocol relative URLs (#275)
 - Added `FormBody::addFileFromString()`

### 4.4.1

 - Reject pushes with invalid stream ID
 - Fix potential double stream release, which might result in int → float overflows and thus type errors

### 4.4.0

This version fixes a security weakness that might leak sensitive request headers from the initial request to the redirected host on cross-domain redirects, which were not removed correctly. `Message::setHeaders` does _not_ replace the entire set of headers, but only operates on the headers matching the given array keys, see fa79253.

 - Support direct HTTP/2 connections without TLS (#271)
 - Security: Remove headers on cross-domain redirects

### 4.3.1

 - Relax `"conflict"` rule with `amphp/file` to allow `dev-master` installations with Composer v1.x (#267, composer/composer#8856)
 - Error if request URI provides a relative path instead of sending an invalid request (#269)

### 4.3.0

 - **Added inactivity timeout** (#263)
   This provides a separate timeout while waiting for the response or streaming the body. If no data is received for the response within the given number of milliseconds, the request fails similarly to the transfer timeout.
 - **Close idle connections if there are too many**
   Requesting URLs from many hosts without reusing connections will otherwise result in resource exhaustion due to too many open files.
 - Improved types for static analysis

### 4.2.2

 - Fixed transfer timeout enforcement for HTTP/2 (#262)

### 4.2.1

 - Fixed HTTP/2 on 32 bit platforms
 - Fixed potentially stalled requests in ConnectionLimitingPool (#256)

### 4.2.0

 - Add improved ConnectionLimitingPool
   The new ConnectionLimitingPool limits connections instead of streams. In addition, it has improved connection handling, racing between new connections and existing connections becoming available once the limit has been reached. The older LimitedConnectionPool has been renamed to StreamLimitingPool with a class alias for backward compatibility.
 - Don't set ALPN if only HTTP/1.1 is enabled, which allows connections to certain misbehaving servers (#255)

### 4.1.0

 - Fix possible double resolution of promises (#244)
 - Fix assertion error on invalid HTTP/2 frame (#236)
 - Fix HTTP/2 connection reuse if too many concurrent streams for one connection are in use (#246)
 - Allow skipping default `accept`, `accept-encoding` and `user-agent` headers (#238)
 - Keep original header case for HTTP/1 requests (#250)
 - Allow access to informational responses (1XX) (#239)
 - Await `startReceiveResponse` event listeners on HTTP/2 before resolving the response promise (#254)
 - Delay `startReceiveResponse` event until the final response is started to be received, instead of calling it for the first byte or multiple times for HTTP/2 (#254)
 - Use common HTTP/2 parser from `amphp/http`

## 4.0.0

Initial release of `amphp/http-client`, the successor of `amphp/artax`.
This is a major rewrite to support interceptors and HTTP/2.

**Major Changes**

 - Support for HTTP/2 (including push)
 - Support for interceptors to customize behavior
 - Switch to a mutable `Request` / `Response` API, because streams are never immutable
 - Compatibility with `amphp/socket@^1`
 - Compatibility with `amphp/file@^1`
 - Compatibility with `league/uri@^6`

## 3.x - 1.x

Please refer to `CHANGELOD.md` in [`amphp/artax`](https://github.com/amphp/artax).

# Changelog

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

# Changelog

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

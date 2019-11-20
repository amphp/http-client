---
title: Following Redirects
permalink: /follow-redirects
---
If you use `HttpClientBuilder`, the resulting `HttpClient` will automatically follow up to ten redirects by default.
Automatic following can be customized or disabled (using a limit of `0`) using `HttpClientBuilder::followRedirects()`.

{:.image-60}
![Following Redirects Illustration](./images/undraw_road_sign_mfpo.svg)

## Redirect Policy

The `FollowRedirects` interceptor will only follow redirects with a `GET` method.
If another request method is used and a `307` or `308` response is received, the response will be returned as is, so another interceptor or the application can take care of it.
Cross-origin redirects will be attempted without any headers set, so any application headers will be discarded.
If `HttpClientBuilder` is used to configure the client, the `FollowRedirects` interceptor is the outermost interceptor, so any headers set by interceptors will still be present in the response.
It is therefore recommended to set headers via interceptors instead of directly in the request.

## Examining the Redirect Chain

All previous responses can be accessed from the resulting `Response` via `Response::getPreviousResponse()`.
However, the response body is discarded on redirects, so it can no longer be consumed.
If you want to consume redirect response bodies, you need to implement your own interceptor.

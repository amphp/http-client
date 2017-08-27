---
title: Configuration
permalink: /configuration
---
The `DefaultClient` follows redirects by default. There are several other options that can be configured. These can either be configured when constructing a new `DefaultClient` object, or when making a request.

## Per-Client Configuration

`DefaultClient` has `setOption` and `setOptions`. The former accepts one of the option constants and a value, while the latter accepts an associative array mapping option constants to values.

{:.warning}
> Be careful with client-wide options that change the behavior of the client. While sometimes useful, changing them client-wide is discouraged. Use per-request configuration instead.

## Per-Request Configuration

These options can also be passed for specific requests in the `request` method as second parameter. The `$options` parameter accepts an associative array mapping from `Client` constants to values like `DefaultClient::setOptions`.

{:.note}
> `setOption` and `setOptions` are by-design not part of the `Client` interface. Setting the options for specific requests is the preferred method of configuration.

## Available Options

| Option                         | Description                |
| ------------------------------ | -------------------------- |
`Client::OP_AUTO_ENCODING` | Whether to automatically apply compression to requests and responses.
`Client::OP_AUTO_REFERER` | Whether to automatically add a "Referer" header on redirects.
`Client::OP_DEFAULT_HEADERS` | Default headers to send.
`Client::OP_DISCARD_BODY` | Whether to directly discard the HTTP response body or not.
`Client::OP_MAX_BODY_BYTES` | Maximum body size. Set to `0` for streaming responses, e.g. Streaming APIs.
`Client::OP_MAX_HEADER_BYTES` | Maximum header size, usually doesn't have to be adjusted.
`Client::OP_MAX_REDIRECTS` | How many redirects to follow, might be `0` to not follow any redirects.
`Client::OP_TRANSFER_TIMEOUT` | Transfer timeout in milliseconds until an HTTP request is automatically aborted, use `0` to disable.

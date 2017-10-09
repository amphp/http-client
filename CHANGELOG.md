# Changelog

## 3.0.8

 - Fixed null pointer access in response handling completely breaking the client.

## 3.0.7 [ borked ]

 - Clean references correctly, so unused bodies aren't consumed and the connection is closed.

## 3.0.6

 - Allow empty content type for multipart form fields.
 - Fail aborted requests correctly.
 - Apply transfer timeouts correctly (was previously a header timeout only).
 - Check for incomplete response bodies and error out in case of an incomplete body.
 - Close sockets if response body is not consumed instead of trying to silently consume it in the background, which might hang indefinitely (depending on the timeout).

## 3.0.5

 - Fixed multipart body bounaries not ending in `\r\n`.

## 3.0.4

 - Fixed GC issues if request bodies mismatches the specified content-length.

## 3.0.3

 - Read the public suffix list only once instead of once per check. This was supposed to work previously, but failed to set the `$initialized` flag.

## 3.0.2

 - Fixed issues with cookies when IDN support is not available.

## 3.0.1

 - Enforce body size limit also for compressed responses. This is a protection measure against malicious servers as described [here](https://blog.haschek.at/2017/how-to-defend-your-website-with-zip-bombs.html).

## 3.0.0
 - Upgrade to Amp v2.
 - Major refactoring for streamed response bodies.
 - Updated redirect policy to use a new `Request` if the host changes.

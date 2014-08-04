v0.8.0
------

- ~~Fix/update SSL/TLS to actually work and to also support new 5.6 capabilities~~ (implemented)
- ~~Add proxy server support~~ (implemented)
- ~~Provide progress bar functionality (old extension system was garbage, removed it)~~ (implemented)
- Fix custom `Iterator` body WTFs.
- Get some test coverage
- Update README to reflect massive changes from previous releases

v1.0.0
------

- Support non-blocking file-system operations for streaming request and response entity bodies.
  Package adapters to work with either pthreads/php-uv for non-blocking fs operations.

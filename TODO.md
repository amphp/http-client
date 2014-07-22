v0.8.0
------

- Update README to reflect the massive changes
- Get some freaking test coverage
- Fix/update SSL/TLS to actually work and to also support new 5.6 capabilities
- Fix custom `Iterator` body WTFs.
- Provide progress bar functionality (old extension system was garbage, removed it)

v0.9.0
------

- Add proxy server support
- Support non-blocking file-system operations for streaming request and response entity bodies.
  Package adapters to work with either pthreads or php-uv for these operations.

v0.10.0
-------

- ??? We may be looking at v1.0.0 once the previous items are complete
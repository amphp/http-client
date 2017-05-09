v1.0.6
------

- Fix cookie leakage to wrong origins and cookie accept criteria
- Add `Notify::HANDSHAKE_COMPLETE` to allow inspecting the TLS data

v1.0.5
------

- Fix requests that use IPv6 addresses as host in the URL directly

v1.0.4
------

- Fix CVE-2016-5385 "httpoxy" vulnerability with environment variables

v1.0.3
------

- Fix URI query string handling (do not rewrite)

v1.0.2
------

- Complete Notify::RESPONSE with an export_socket function
- Fixed cyclic reference with socket

v1.0.1
------

- Fixed cookie domain matching
- Fixed issue #87

v1.0.0
------

- Initial release

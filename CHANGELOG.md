v2.0.1
------

- Remove cyclic reference involving RequestCycle and thus the socket resource, which led to the socket only being freed after cycle collector kicked in.

v2.0.0
------

- Updated amp dependency to version ^1
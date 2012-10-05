<?php

namespace Artax\Streams;

/**
 * Indicates that the socket has disconnected on a read initiated by stream_select
 * 
 * php.net stream_select docs:
 * 
 * "The streams listed in the read array will be watched to see if characters become available for 
 * reading (more precisely, to see if a read will not block - in particular, a stream resource is 
 * also ready on end-of-file, in which case an fread() will return a zero length string)."
 * 
 * This exception can be caught when invoking SocketStream::read to determine that the selected
 * socket stream connection has been closed.
 */
class SocketDisconnectedException extends StreamException {}
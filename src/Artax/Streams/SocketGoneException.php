<?php

namespace Artax\Streams;

/**
 * Thrown when a read attempt is made on a socket whose connection has gone away
 */
class SocketGoneException extends IoException {}
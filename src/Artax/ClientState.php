<?php

namespace Artax;

class ClientState {
    const AWAITING_SOCKET = 0;
    const SENDING_REQUEST_HEADERS = 2;
    const SENDING_BUFFERED_REQUEST_BODY = 4;
    const SENDING_STREAM_REQUEST_BODY = 8;
    const READING_HEADERS = 16;
    const READING_UNTIL_CLOSE = 32;
    const READING_UNTIL_LENGTH_REACHED = 64;
    const READING_CHUNKS = 128;
    const RESPONSE_RECEIVED = 256;
    const ERROR = 512;
}
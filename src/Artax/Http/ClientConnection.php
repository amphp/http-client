<?php

namespace Artax\Http;

interface ClientConnection {
    function setConnectTimeout($seconds);
    function setConnectFlags($flags);
    function connect();
    function isConnected();
    function hasBeenIdleFor($secondsOfInactivity);
    function resetActivityTimestamp();
    function close();
    function getId();
    function getHost();
    function getPort();
    function getAuthority();
    function getUri();
    function getStream();
    function writeData($data);
    function readBytes($bytes);
    function readLine();
    function __toString();
}

<?php

namespace Artax\Http;

interface StreamConnection {
    
    function connect();
    function getUri();
    function isConnected();
    function close();
    function getId();
    function getAuthority();
    function getStream();
    function setConnectTimeout($seconds);
    function setConnectFlags($flags);
    function __toString();
}

<?php

namespace Artax\Http;

interface StreamConnection {
    
    function connect($flags);
    function getUri();
    function isConnected();
    function close();
    function getId();
    function getAuthority();
    function getStream();
    function setConnectTimeout($seconds);
    function __toString();
}

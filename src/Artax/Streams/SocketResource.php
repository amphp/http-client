<?php

namespace Artax\Streams;

interface SocketResource extends Resource {
    
    function setConnectTimeout($seconds);
    function setConnectFlags($flagBitmask);
    function setContextOptions(array $options);
    
    function getActivityTimestamp();
    function getBytesSent();
    function getBytesRecd();
    
    function getScheme();
    function getHost();
    function getPort();
    function getAuthority();
    function getPath();
    function getUri();
    
}

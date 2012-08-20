<?php

namespace Artax\Http;

interface Response extends Message {
    
    function getStartLine();
    function getStatusCode();
    function setStatusCode($statusCode);
    function getStatusDescription();
    function setStatusDescription($statusDescription);
    function send();
    function wasSent();
}

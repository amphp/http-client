<?php

namespace Artax\Http;

interface Response extends Message {

    function getStatusCode();
    function getStatusDescription();
    function send();
    function wasSent();

}

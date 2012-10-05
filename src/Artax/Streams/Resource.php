<?php

namespace Artax\Streams;

interface Resource {

    function open();
    function close();
    function read($bytesToRead);
    function write($dataToWrite);
    function getResource();
}

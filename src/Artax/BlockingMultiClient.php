<?php

namespace Artax;

interface BlockingMultiClient extends BlockingClient {
    function requestMulti(array $requests, callable $onEachResponse, callable $onEachError);
}

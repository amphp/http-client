<?php

namespace Artax;

interface BlockingClient extends ObservableClient {
    function request($uriOrRequest);
    function requestMulti(array $requests, callable $onEachResponse, callable $onEachError);
}

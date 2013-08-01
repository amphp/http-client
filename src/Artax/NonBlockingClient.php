<?php

namespace Artax;

interface NonBlockingClient extends ObservableClient {
    function request($uriOrRequest, callable $onResponse, callable $onError);
}

<?php

namespace Artax;

interface BlockingClient extends ObservableClient {
    function request($uriOrRequest);
}

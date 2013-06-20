<?php

namespace Artax;

interface Extension {
    function subscribe(ObservableClient $client);
    function unsubscribe();
}


<?php

namespace Artax;

interface Extension {
    function extend(ObservableClient $client);
    function unextend();
}


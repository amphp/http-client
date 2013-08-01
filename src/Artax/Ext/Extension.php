<?php

namespace Artax\Ext;

use Artax\ObservableClient;

interface Extension {
    function extend(ObservableClient $client);
    function unextend();
}

<?php

namespace Amp\Artax;

interface HttpClient {
    public function request($uriOrRequest);
}

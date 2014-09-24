<?php

namespace Amp\Artax;

interface HttpClient {
    public function request($uriOrRequest);
    public function requestMulti(array $urisOrRequests);
    public function setOption($option, $value);
    public function setAllOptions(array $options);
}

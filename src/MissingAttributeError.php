<?php

namespace Amp\Http\Client;

final class MissingAttributeError extends \Error
{
    public function __construct(string $message)
    {
        parent::__construct($message);
    }
}

<?php

namespace Amp\Http\Client\Internal;

/** @internal */
trait ForbidSerialization
{
    final public function __sleep()
    {
        throw new \Error(__CLASS__ . ' does not support serialization');
    }
}

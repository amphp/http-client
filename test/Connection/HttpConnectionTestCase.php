<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Connection;

use Amp\PHPUnit\AsyncTestCase;
use League\Uri;

abstract class HttpConnectionTestCase extends AsyncTestCase
{
    protected function createUriFromString(string $uri): Uri\Http
    {
        return \method_exists(Uri\Http::class, 'new')
            ? Uri\Http::new($uri)
            : Uri\Http::createFromString($uri);
    }
}

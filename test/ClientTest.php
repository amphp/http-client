<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace Amp\Http\Client\Test;

use Amp\Http\Client\Client;
use Amp\PHPUnit\AsyncTestCase;
use Amp\Promise;

class ClientTest extends AsyncTestCase
{
    private $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->client = new Client;
    }

    public function testUserInfoDeprecation(): Promise
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('has been deprecated');

        return $this->client->request('https://username:password@localhost');
    }
}

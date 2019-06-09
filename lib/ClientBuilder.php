<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\ApplicationInterceptorClient;
use Amp\Socket\SocketPool;
use Amp\Socket\UnlimitedSocketPool;

final class ClientBuilder
{
    private $socketPool;
    private $networkInterceptors = [];
    private $applicationInterceptors = [];

    public function __construct(?SocketPool $socketPool = null)
    {
        $this->socketPool = $socketPool ?? new UnlimitedSocketPool;
    }

    public function addNetworkInterceptor(NetworkInterceptor $networkInterceptor): self
    {
        $this->networkInterceptors[] = $networkInterceptor;

        return $this;
    }

    public function addApplicationInterceptor(ApplicationInterceptor $applicationInterceptor): self
    {
        $this->applicationInterceptors[] = $applicationInterceptor;

        return $this;
    }

    public function build(): Client
    {
        $client = new SocketClient($this->socketPool);
        foreach ($this->networkInterceptors as $networkInterceptor) {
            $client->addNetworkInterceptor($networkInterceptor);
        }

        $applicationInterceptors = $this->applicationInterceptors;
        while ($applicationInterceptor = \array_pop($applicationInterceptors)) {
            $client = new ApplicationInterceptorClient($client, $applicationInterceptor);
        }

        return $client;
    }
}

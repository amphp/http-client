<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\ApplicationInterceptorClient;
use Amp\Socket\Connector;
use Amp\Socket\DnsConnector;

final class ClientBuilder
{
    private $connector;
    private $networkInterceptors = [];
    private $applicationInterceptors = [];

    public function __construct(?Connector $connector = null)
    {
        $this->connector = $connector ?? new DnsConnector;
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
        $client = new SocketClient($this->connector);
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

<?php

namespace Amp\Http\Client;

final class ClientBuilder
{
    private $connectionPool;
    private $networkInterceptors = [];
    private $applicationInterceptors = [];

    public function __construct(?Connection\ConnectionPool $connectionPool = null)
    {
        $this->connectionPool = $connectionPool ?? new Connection\DefaultConnectionPool;
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
        $client = new SocketClient($this->connectionPool);
        foreach ($this->networkInterceptors as $networkInterceptor) {
            $client->addNetworkInterceptor($networkInterceptor);
        }

        $applicationInterceptors = $this->applicationInterceptors;
        while ($applicationInterceptor = \array_pop($applicationInterceptors)) {
            $client = new Internal\ApplicationInterceptorClient($client, $applicationInterceptor);
        }

        return $client;
    }
}

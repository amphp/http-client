<?php

namespace Artax;

use Alert\Reactor,
    After\Deferred,
    After\Failure,
    Addr\Resolver,
    Addr\ResolverFactory;

class TcpConnector {
    /**
     * DNS address family constants
     * @TODO These constants should be part of the DNS lib ... move 'em out
     */
    const ADDR_INET4 = 1;
    const ADDR_INET6 = 2;

    const OP_BIND_IP_ADDRESS = 'op.bind-ip-address';
    const OP_MS_CONNECT_TIMEOUT = 'op.ms-connect-timeout';
    // @TODO const OP_MS_DNS_TIMEOUT = 3; // Wait until Addr DNS lib API stabilizes

    private $reactor;
    private $dnsResolver;
    private $options = [
        self::OP_BIND_IP_ADDRESS => '',
        self::OP_MS_CONNECT_TIMEOUT => 30000
    ];

    public function __construct(Reactor $reactor, Resolver $dnsResolver = null) {
        $this->reactor = $reactor;
        $this->dnsResolver = $dnsResolver ?: (new ResolverFactory)->createResolver($reactor);
    }

    /**
     * Make a socket connection to the specified URI
     *
     * @param string $uri
     * @param array $options
     * @return \After\Promise
     */
    public function connect($uri, array $options = []) {
        // Host names are always case-insensitive
        $uri = strtolower($uri);
        extract(@parse_url($uri));
        if (empty($host) || empty($port)) {
            return new Failure(new \DomainException(
                'Invalid socket URI: host name and port number required'
            ));
        }

        $scheme = isset($scheme) ? $scheme : 'tcp';
        $options = $options ? array_merge($this->options, $options) : $this->options;

        $tcpConnectorStruct = new TcpConnectorStruct;
        $tcpConnectorStruct->scheme = $scheme;
        $tcpConnectorStruct->host = $host;
        $tcpConnectorStruct->port = $port;
        $tcpConnectorStruct->uri = "{$scheme}://{$host}:{$port}";
        $tcpConnectorStruct->options = $options;
        $tcpConnectorStruct->deferred = new Deferred;

        $this->dnsResolver->resolve($host, function($resolvedIp, $ipType) use ($tcpConnectorStruct) {
            $this->onDnsResolution($tcpConnectorStruct, $resolvedIp, $ipType);
        });

        return $tcpConnectorStruct->deferred->promise();
    }

    private function onDnsResolution(TcpConnectorStruct $tcpConnectorStruct, $resolvedIp, $ipType) {
        if ($resolvedIp === null) {
            $tcpConnectorStruct->deferred->fail(new DnsException(
                sprintf(
                    'DNS resolution failed for %s (error code: %d)',
                    $tcpConnectorStruct->uri,
                    $ipType
                )
            ));
        } else {
            $tcpConnectorStruct->resolvedAddress = ($ipType === self::ADDR_INET6)
                ? "[{$resolvedIp}]:{$tcpConnectorStruct->port}"
                : "{$resolvedIp}:{$tcpConnectorStruct->port}";

            $this->doConnect($tcpConnectorStruct);
        }
    }

    private function doConnect(TcpConnectorStruct $tcpConnectorStruct) {
        $flags = STREAM_CLIENT_CONNECT | STREAM_CLIENT_ASYNC_CONNECT;
        $timeout = 42; // <--- timeout not applicable for async connections
        $contextOptions = [];
        $bindToIp = $tcpConnectorStruct->options[self::OP_BIND_IP_ADDRESS];
        if ($bindToIp) {
            $contextOptions['socket']['bindto'] = $bindToIp;
        }
        $ctx = stream_context_create($contextOptions);
        $addr = $tcpConnectorStruct->resolvedAddress;

        if ($socket = @stream_socket_client($addr, $errno, $errstr, $timeout, $flags, $ctx)) {
            $tcpConnectorStruct->socket = $socket;
            $this->initializePendingSocket($tcpConnectorStruct);
        } else {
            $tcpConnectorStruct->deferred->fail(new SocketException(
                sprintf(
                    'Connection to %s failed: [Error #%d] %s',
                    $tcpConnectorStruct->uri,
                    $errno,
                    $errstr
                )
            ));
        }
    }

    private function initializePendingSocket(TcpConnectorStruct $tcpConnectorStruct) {
        $socket = $tcpConnectorStruct->socket;
        $socketId = (int) $socket;
        stream_set_blocking($socket, false);

        $timeout = $tcpConnectorStruct->options[self::OP_MS_CONNECT_TIMEOUT];
        if ($timeout > 0) {
            $tcpConnectorStruct->timeoutWatcher = $this->reactor->once(function() use ($tcpConnectorStruct) {
                $this->timeoutSocket($tcpConnectorStruct);
            }, $timeout);
        }

        $tcpConnectorStruct->connectWatcher = $this->reactor->onWritable($socket, function() use ($tcpConnectorStruct) {
            $this->fulfillSocket($tcpConnectorStruct);
        });
    }

    private function timeoutSocket(TcpConnectorStruct $tcpConnectorStruct) {
        $this->reactor->cancel($tcpConnectorStruct->connectWatcher);
        $this->reactor->cancel($tcpConnectorStruct->timeoutWatcher);
        $timeout = $tcpConnectorStruct->options[self::OP_MS_CONNECT_TIMEOUT];
        $tcpConnectorStruct->deferred->fail(new SocketException(
            sprintf('Socket connect timeout exceeded: %d ms', $timeout)
        ));
    }

    private function fulfillSocket(TcpConnectorStruct $tcpConnectorStruct) {
        $this->reactor->cancel($tcpConnectorStruct->connectWatcher);
        if ($tcpConnectorStruct->timeoutWatcher !== null) {
            $this->reactor->cancel($tcpConnectorStruct->timeoutWatcher);
        }

        $tcpConnectorStruct->deferred->succeed($tcpConnectorStruct->socket);
    }

    /**
     * Set socket connector options
     *
     * @param mixed $option
     * @param mixed $value
     * @throws \DomainException on unknown option key
     * @return self
     */
    public function setOption($option, $value) {
        switch ($option) {
            case self::OP_MS_CONNECT_TIMEOUT:
                $this->options[self::OP_MS_CONNECT_TIMEOUT] = (int) $value;
                break;
            case self::OP_BIND_IP_ADDRESS:
                $this->options[self::OP_BIND_IP_ADDRESS] = (string) $value;
                break;
            default:
                throw new \DomainException(
                    sprintf('Unknown option: %s', $option)
                );
        }

        return $this;
    }
}

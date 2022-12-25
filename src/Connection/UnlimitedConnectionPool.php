<?php declare(strict_types=1);

namespace Amp\Http\Client\Connection;

use Amp\Cancellation;
use Amp\ForbidSerialization;
use Amp\Http\Client\Request;

final class UnlimitedConnectionPool implements ConnectionPool
{
    use ForbidSerialization;

    private ConnectionLimitingPool $pool;

    public function __construct(?ConnectionFactory $connectionFactory = null)
    {
        $this->pool = ConnectionLimitingPool::byAuthority(\PHP_INT_MAX, $connectionFactory);
    }

    public function __clone()
    {
        $this->pool = clone $this->pool;
    }

    public function getTotalConnectionAttempts(): int
    {
        return $this->pool->getTotalConnectionAttempts();
    }

    public function getTotalStreamRequests(): int
    {
        return $this->pool->getTotalStreamRequests();
    }

    public function getOpenConnectionCount(): int
    {
        return $this->pool->getOpenConnectionCount();
    }

    public function getStream(Request $request, Cancellation $cancellation): Stream
    {
        return $this->pool->getStream($request, $cancellation);
    }
}

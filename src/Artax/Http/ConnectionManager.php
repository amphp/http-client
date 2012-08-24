<?php 

namespace Artax\Http;

use Spl\Mediator,
    Artax\Http\Exceptions\MaxConcurrencyException;

class ConnectionManager {

    /**
     * @var array
     */
    private $connPool = array();
    
    /**
     * @var array
     */
    private $connsInUse = array();
    
    /**
     * @var array
     */
    private $sslOptions = array();
    
    /**
     * @var int
     */
    private $connectTimeout = 60;
    
    /**
     * @var int
     */
    private $hostConcurrencyLimit = 5;
    
    /**
     * @param array $options
     * @return void
     */
    public function setSslOptions(array $options) {
        $this->sslOptions = $options;
    }
    
    /**
     * @param int $seconds
     * @return void
     */
    public function setConnectTimout($seconds) {
        $this->connectTimeout = (int) $seconds;
    }
    
    /**
     * @param int $maxConnections
     * @return void
     */
    public function setHostConcurrencyLimit($maxConnections) {
        $integerized = (int) $maxConnections;
        $normalized = $integerized < 1 ? 1 : $integerized;
        $this->hostConcurrencyLimit = $normalized;
    }
    
    /**
     * Checkout a new connection (subject to the host concurrency limit) and connect
     * 
     * @param Request $request
     * 
     * @return ClientConnection -or- null if no connection slots are available
     */
    public function checkout($scheme, $host, $port) {
        $authority = "$host:$port";
        
        if (!isset($this->connPool[$authority])) {
            $this->connPool[$authority] = array();
        }
        
        foreach ($this->connPool[$authority] as $key => $conn) {
            if (!$this->isCheckedOut($conn)) {
                $this->doCheckout($conn);
                return $conn;
            }
        }
        
        $openConnsToAuthority = count($this->connPool[$authority]);
        
        if ($this->hostConcurrencyLimit > $openConnsToAuthority) {
        
            $conn = $this->makeConnection($scheme, $host, $port);
            $conn->connect();
            $this->connPool[$authority][$conn->getId()] = $conn;
            $this->doCheckout($conn);
            return $conn;
            
        }
        
        throw new MaxConcurrencyException(
            "Cannot connect to $authority: the maximum number of allowed concurrent connections " .
            "for this authority are already in use ({$this->hostConcurrencyLimit})"
        );
    }
    
    /**
     * @param ClientConnection $conn
     * @return bool
     */    
    protected function isCheckedOut(ClientConnection $conn) {
        return in_array($conn->getUri(), $this->connsInUse);
    }
    
    /**
     * @param ClientConnection $conn
     * @return void
     */
    protected function doCheckout(ClientConnection $conn) {
        $this->connsInUse[] = $conn->getUri();
        $conn->resetActivityTimestamp();
    }
    
    /**
     * Mark a connection as "not in use"
     * 
     * @param ClientConnection $conn
     * @return void
     */
    public function checkin(ClientConnection $conn) {
        $key = array_search($conn->getUri(), $this->connsInUse);
        unset($this->connsInUse[$key]);
    }
    
    /**
     * A factory method to create new connections
     * 
     * @param string $scheme
     * @param string $host
     * @param int $port
     * 
     * @return ClientConnection
     */
    public function makeConnection($scheme, $host, $port, $flags = STREAM_CLIENT_CONNECT) {
        
        if (strcmp('https', $scheme)) {
            $conn = new TcpConnection($host, $port);
        } else {
            $conn = new SslConnection($host, $port);
            $conn->setSslOptions($this->sslOptions);
        }
        
        $conn->setConnectTimeout($this->connectTimeout);
        $conn->setConnectFlags($flags);
        
        return $conn;
    }
    
    /**
     * Close the specified connection
     * 
     * @param ClientConnection $conn
     * @return void
     */
    public function close(ClientConnection $conn) {
        $this->checkin($conn);
        $conn->close();
        $authority = $conn->getAuthority();
        $id = $conn->getId();
        unset($this->connPool[$authority][$id]);
    }
    
    /**
     * Close all open connections
     * 
     * @return int Returns the number of connections closed
     */
    public function closeAll() {
        $connsClosed = 0;
        
        foreach ($this->connPool as $authority => $connArr) {
            foreach ($connArr as $conn) {
                $this->close($conn);
                ++$connsClosed;
            }
        }
        
        return $connsClosed;
    }
    
    /**
     * Close any open connections to the specified host, regardless of port number
     * 
     * @param string $host
     * @return int Returns the number of connections closed
     */
    public function closeByHost($host) {
        $connsClosed = 0;
        $normalizedHost = rtrim($host, ':') . ':';
        
        foreach ($this->connPool as $authority => $connArr) {
            if (0 !== strpos($authority, $normalizedHost)) {
                continue;
            }
            foreach ($connArr as $conn) {
                $this->closeConnection($conn);
                ++$connsClosed;
            }
        }
        
        return $connsClosed;
    }
    
    /**
     * Close any open connections to the specified authority (host:port)
     * 
     * @param string $authority
     * @return int Returns the number of connections closed
     */
    public function closeByAuthority($authority) {
        if (empty($this->connPool[$authority])) {
            return 0;
        }
        
        $connsClosed = 0;
        foreach ($this->connPool[$authority] as $conn) {
            $this->closeConnection($conn);
            ++$connsClosed;
        }
        
        return $connsClosed;
    }
    
    /**
     * Close all connections that have been idle longer than the specified number of seconds
     * 
     * @param int $maxInactivitySeconds
     * @return int Returns the number of connections closed
     */
    public function closeIdle($maxInactivitySeconds) {
        $maxInactivitySeconds = (int) $maxInactivitySeconds;
        $connsClosed = 0;
        
        foreach ($this->connPool as $authority => $connArr) {
            foreach ($connArr as $conn) {
                if (!$conn->hasBeenIdleFor($maxInactivitySeconds)) {
                    continue;
                }
                $this->closeConnection($conn);
                ++$connsClosed;
            }
        }
        
        return $connsClosed;
    }
    
}

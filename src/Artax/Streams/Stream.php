<?php

namespace Artax\Streams;

use Spl\TypeException;

class Stream implements Resource {
    
    /**
     * @var resource
     */
    protected $resource;
    
    /**
     * @var string
     */
    private $path;
    
    /**
     * @var string
     */
    private $mode;
    
    /**
     * @param string $path
     * @param string $mode
     * @return void
     */
    public function __construct($path, $mode) {
        $this->path = $path;
        $this->mode = $mode;
    }
    
    /**
     * Open the stream resource specified by the path and mode passed to the constructor
     * 
     * @throws StreamException
     * @return void
     */
    public function open() {
        if (!$this->resource = $this->doOpen($this->path, $this->mode)) {
            throw new StreamException(
                'Failed opening stream: fopen(' . $this->path . ', ' . $this->mode . ')'
            );
        }
    }
    
    /**
     * A test seam for mocking fopen results
     * 
     * @param string $path
     * @param string $mode
     * @return mixed Returns stream resource on success or FALSE on failure
     */
    protected function doOpen($path, $mode) {
        return @fopen($path, $mode);
    }
    
    /**
     * Close the stream resource (fclose)
     * 
     * @return void
     */
    public function close() {
        if (!empty($this->resource)) {
            @fclose($this->resource);
            $this->resource = null;
        }
    }
    
    /**
     * Read data from the stream resource (fread)
     * 
     * @param int $bytesToRead
     * @throws IoException
     * @return string
     */
    public function read($bytesToRead) {
        $readData = $this->doRead($bytesToRead);
        
        if (false === $readData) {
            $resourceId = $this->getResourceId() ?: '(none)';
            throw new IoException(
                'Failed reading ' . $bytesToRead . ' bytes from resource ID# ' . $resourceId
            );
        }
        
        return $readData;
    }
    
    /**
     * A test seam for mocking fread results
     * 
     * @return mixed Returns read data or FALSE on error
     */
    protected function doRead($bytes) {
        return @fread($this->resource, $bytes);
    }
    
    /**
     * @return int
     */
    private function getResourceId() {
        return empty($this->resource) ? 0 : (int) $this->resource;
    }
    
    /**
     * Write data to the stream resource (fwrite)
     * 
     * @param string $dataToWrite
     * @throws IoException
     * @return int Returns the number of bytes written
     */
    public function write($dataToWrite) {
        $bytesWritten = $this->doWrite($dataToWrite);
        
        if (false === $bytesWritten) {
            $length = strlen($dataToWrite);
            $resourceId = $this->getResourceId() ?: '(none)';
            throw new IoException(
                'Failed writing ' . $length . ' bytes to resource ID# ' . $resourceId
            );
        }
        
        return $bytesWritten;
    }
    
    /**
     * A test seam for mocking fwrite results
     * 
     * @return mixed Returns the number of bytes written or FALSE on error
     */
    protected function doWrite($data) {
        return @fwrite($this->resource, $data);
    }
    
    /**
     * Access the raw stream resource
     * 
     * @return resource
     */
    public function getResource() {
        return $this->resource;
    }
}

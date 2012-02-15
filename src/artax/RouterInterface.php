<?php

/**
 * RouterInterface Interface File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax {

  /**
   * RouterInterface
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  interface RouterInterface
  {
    /**
     * Loads the appropriate controller for the specified request
     * 
     * @param RequestInterface $request The request object to match
     */
    public function dispatch(RequestInterface $request);
  }
}

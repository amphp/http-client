<?php

/**
 * RouterAbstract Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax {

  /**
   * RouterAbstract Class
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  abstract class RouterAbstract implements RouterInterface
  {
    /**
     * @var ProviderInterface
     */
    protected $deps;
    
    /**
     * @var MatcherInterface
     */
    protected $matcher;
    
    /**
     * 
     */
    public function __construct(ProviderInterface $deps, MatcherInterface $matcher)
    {
      $this->deps    = $deps;
      $this->matcher = $matcher;
    }
  }
}

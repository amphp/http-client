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
     * @var blocks\mediator\MediatorInterface
     */
    protected $mediator;
    
    /**
     * 
     */
    public function __construct(
      DepProvider $deps,
      MatcherInterface $matcher,
      blocks\mediator\MediatorInterface $mediator
    )
    {
      $this->deps     = $deps;
      $this->matcher  = $matcher;
      $this->mediator = $mediator;
    }
  }
}

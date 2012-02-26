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
  abstract class RouterAbstract implements RouterInterface, NotifierInterface
  {
    use NotifierTrait;
    
    /**
     * @var ProviderInterface
     */
    protected $deps;
    
    /**
     * @var MatcherInterface
     */
    protected $matcher;
    
    /**
     * @var RouteList
     */
    protected $routeList;
    
    /**
     * @var RequestInterface
     */
    protected $request;
    /**
     * 
     */
    public function __construct(
      DepProvider $deps,
      MatcherInterface $matcher,
      MediatorInterface $mediator,
      RouteList $routeList,
      RequestInterface $request
    )
    {
      $this->deps      = $deps;
      $this->matcher   = $matcher;
      $this->mediator  = $mediator;
      $this->routeList = $routeList;
      $this->request   = $request;
    }
  }
}

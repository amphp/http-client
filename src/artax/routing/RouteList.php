<?php

/**
 * RouteList Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage routing
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax\routing {

  /**
   * RouteList Class
   * 
   * @category   artax
   * @package    core
   * @subpackage routing
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class RouteList extends \SplObjectStorage
  {
    /**
     * Add a Route object to the List
     * 
     * Before addition, the Route object is inspected to ensure that it
     * hasn't already been added. The Route object must also specify both
     * alias and target properties to be added to the list.
     * 
     * @param Route $route Route object
     * @param mixed $data  Data to associate with the object
     * 
     * @return bool TRUE on successful add -or- FALSE if add failed
     * @throws \artax\exceptions\InvalidArgumentException If not passed a Route object
     */
    public function attach($route, $data=NULL)
    {
      if ( ! $route instanceof RouteInterface) {
        $msg = 'attach() expects an instance of RouteInterface: ' . 
          get_class($route) . ' specified';
        throw new \artax\exceptions\InvalidArgumentException($msg);
      }
      if (NULL !== $data && ! is_string($data)) {
        $msg = 'attach() expects a string $data parameter: ' .gettype($data) .
          ' specified';
        throw new \artax\exceptions\InvalidArgumentException($msg);
      }
      parent::attach($route, $data);
    }
    
    /**
     * Overloads the parent addAll function to prevent non-Route entries
     * 
     * @param RouteList $obj RouteList object
     * 
     * @return void
     * @throws \artax\exceptions\InvalidArgumentException If not passed a RouteList object
     */
    public function addAll($obj)
    {
      $expected = __CLASS__;
      if ( ! $obj instanceof $expected) {
        $msg = "RouteList::addAll expects an instance of $expected: " . 
          get_class($obj) . ' specified';
        throw new \artax\exceptions\InvalidArgumentException($msg);
      }
      parent::addAll($obj);
    }
    
    /**
     * Build the route list from a structured array
     * 
     * @param array $arr A structured array of route values
     * 
     * @return RouteList Object instance for method chaining
     * @throws exceptions\ErrorException On invalid array structure
     * @throws exceptions\InvalidArgumentException On invalid route values
     */
    public function addAllFromArr(array $arr)
    {
      foreach (array_keys($arr) as $key) {
        $this->addFromArr($arr[$key], $key);
      }
      return $this;
    }
    
    /**
     * Attach a route to the list using a structured array of route values
     * 
     * Requires the array be of the following structure:
     *  - [string $name]
     *    - [string $alias, string $controller, array $constraints]
     * 
     * @param array  $arr  A structured array of route values
     * @param string $data An optional route name string for reverse routing
     * 
     * @return RouteList Object instance for method chaining
     * @throws exceptions\InvalidArgumentException On invalid route values
     */
    public function addFromArr(array $arr, $data=NULL)
    {
      $arr[2] = isset($arr[2]) ? $arr[2] : [];
      $route  = new Route($arr[0], $arr[1], $arr[2]);
      $this->attach($route, $data);
      return $this;
    }
    
    /**
     * Locate a route by its data info key
     * 
     * Route objects are stored in a queue structure. This means that if duplicate
     * info is stored for two separate routes, the route with matching data that
     * was added to the storage list first will be returned.
     * 
     * @param string $data Data parameter associated with a stored route object
     * 
     * @return mixed Returns Route object if match found or `FALSE` if no stored
     *               route objects have data matching the `$data` parameter.
     */
    public function find($data)
    {
      $this->rewind();
      while ($this->valid()) {
        if ($this->getInfo() == $data) {
          return $this->current();
        }
        $this->next();
      }
      return FALSE;
    }
  }
}

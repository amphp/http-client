<?php

/**
 * Route Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @subpackage routing
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Routing {
  
  /**
   * Route Class
   * 
   * @category   Artax
   * @package    core
   * @subpackage routing
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class Route implements RouteInterface
  {
    /**
     * Route alias to match against
     * @var string
     */
    protected $alias;
    
    /**
     * Dot-notation namespaced Controller class.
     * 
     * The class constructor will be passed the request object upon instantiation.
     * 
     * @var string
     */
    protected $controller;
    
    /**
     * Key-Value array mapping capture parameters to regex patterns
     * @var array
     */
    protected $constraints;
    
    /**
     * Compiled route matching regular expression pattern
     * @var string
     */
    protected $pattern;
    
    /**
     * Assigns protected matcher properties on instantiation
     * 
     * @param string $alias       Route alias to attempt to match
     * @param string $controller  Controller to instantiate on route alias match
     * @param array  $constraints Array of regex pattern constraints for parameter
     *                            capture groups
     * 
     * @return void
     * @uses Route::buildPattern
     */
    public function __construct($alias, $controller, array $constraints=[])
    {
      $this->alias       = $alias;
      $this->controller  = $controller;
      $this->constraints = $constraints;
      $this->pattern     = $this->buildPattern($alias, $constraints);
    }
    
    /**
     * Setter method for route alias
     * 
     * @param string $alias       Route alias string
     * @param array  $constraints An array of named capture argument constraints
     * 
     * @return void
     * @throws InvalidArgumentException On invalid route specification
     * @used-by Route::buildPattern
     * @uses    Route::buildPattern
     */
    protected function buildPattern($alias, $constraints)
    {
      if (preg_match_all('/<([\p{L}_]+)>/u', $alias, $matches, PREG_SET_ORDER)) {
        $argNames = [];
        foreach ($matches as $m) {
          if (isset($constraints[$m[1]])) {
            if (in_array($m[1], $argNames)) {
              $msg = 'Duplicate route arguments not supported: <'.$m[1].'>';
              throw new \InvalidArgumentException($msg);
            }
            $repl = '(?P<'.$m[1].'>'.$constraints[$m[1]].')';
            $alias = str_replace('<'.$m[1].'>', $repl, $alias);
            $argNames[] = $m[1];
          } else {
            $msg = 'Named route argument requires matching constraint: <'.$m[1].'>';
            throw new \InvalidArgumentException($msg);
          }
        }
      }
      return $this->compile($alias);
    }
    
    /**
     * Compiles an Artax route into a matchable regex pattern
     * 
     * @param string $alias Request route alias
     * 
     * @return string Returns a regular expression pattern for route matching
     * @used-by Route::buildPattern
     */
    protected function compile($alias)
    {
      $find = ['#',  ':any',  ':num', ':alpha', ':alphanum'];
      $repl = ['\#', '[^/]+', '\d+',  '\p{L}+', '[\p{L}\d]+'];
      return '#^' . str_replace($find, $repl, $alias) . '$#u';
    }
    
    /**
     * Getter method for route alias
     * 
     * @return string Returns route alias string
     */
    public function getAlias()
    {
      return $this->alias;
    }
    
    /**
     * Getter method for route target controller
     * 
     * @return string Returns namespaced route target controller
     */
    public function getController()
    {
      return $this->controller;
    }
    
    /**
     * Getter method for route argument constraints
     * 
     * @return array Returns an array of route argument constraints
     */
    public function getConstraints()
    {
      return $this->constraints;
    }
    
    /**
     * Getter method for generated route-matching pattern
     * 
     * @return string Returns generated route-matching pattern
     */
    public function getPattern()
    {
      return $this->pattern;
    }
  }
}

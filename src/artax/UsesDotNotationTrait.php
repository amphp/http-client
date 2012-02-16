<?php

/**
 * Artax UsesDotNotationTrait Trait File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax {

  /**
   * Artax UsesDotNotationTrait Trait
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait UsesDotNotationTrait
  {
    /**
     * DotNotation object instance
     * @var DotNotation
     */
    protected $dotNotation;
    
    /**
     * Setter method for protected `$dotNotation` property
     * 
     * @param DotNotation $dotNotation DotNotationuration object instance
     * 
     * @return mixed Object instance for method chaining
     */
    public function setDotNotation(DotNotation $dotNotation)
    {
      $this->dotNotation = $dotNotation;
      return $this;
    }
    
    /**
     * Getter method for protected `$dotNotation` property
     * 
     * @return DotNotation Returns dotNotationuration object or `NULL` if not set
     */
    public function getDotNotation()
    {
      return $this->dotNotation;
    }
  }
}

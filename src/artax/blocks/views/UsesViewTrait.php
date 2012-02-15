<?php

/**
 * Artax UsesViewTrait Trait File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    blocks
 * @subpackage views
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\views {

  /**
   * Artax UsesViewTrait Trait
   * 
   * @category   artax
   * @package    blocks
   * @subpackage views
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  trait UsesViewTrait
  {
    /**
     * ViewInterface object instance
     * @var ViewInterface
     */
    protected $view;
    
    /**
     * Setter method for protected `$view` property
     * 
     * @param ViewInterface $view View object instance
     * 
     * @return mixed Object instance for method chaining
     */
    public function setView(ViewInterface $view)
    {
      $this->view = $view;
      return $this;
    }
    
    /**
     * Getter method for protected `$view` property
     * 
     * @return ViewInterface Returns ViewInterface object or `NULL` if not assigned
     */
    public function getView()
    {
      return $this->view;
    }
  }
}

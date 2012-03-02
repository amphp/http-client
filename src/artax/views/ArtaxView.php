<?php

/**
 * ArtaxView Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @subpackage views
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\views {
  
  /**
   * ArtaxView Class
   * 
   * @category   artax
   * @package    core
   * @subpackage views
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class ArtaxView extends \artax\Bucket implements ViewInterface
  {    
    /**
     * View template filepath
     * @var string
     */
    protected $template;
    
    /**
     * Assign a variable to the template
     * 
     * @param string $name Variable name
     * @param mixed  $var  Variable value
     * 
     * @return ArtaxView Object instance for method chaining
     */
    public function setVar($name, $var)
    {
      return $this->set($name, $var);
    }
    
    /**
     * Retrieve a template variable's value
     * 
     * @param string $name Variable name
     * 
     * @return mixed Variable value
     */
    public function getVar($name)
    {
      return $this->get($name);
    }
    
    /**
     * Assign an array of multiple template variables at once
     * 
     * @param array  $vars An associative key-value array for mass template
     *                     variable assignment at render time.
     * 
     * @return ArtaxView Returns object instance for method chaining.
     */
    public function setAll(array $vars)
    {
      $this->load($vars, TRUE);
      return $this;
    }
    
    /**
     * Retrieve the rendered template without outputting its contents
     * 
     * @param string $tpl  The template to render
     * @param array  $vars An associative key-value array for mass template
     *                     variable assignment at render time.
     * 
     * @return string Rendered template
     */
    public function render($tpl, array $vars=[])
    {
      if ($vars) {
        $this->load($vars, TRUE);
      }
      ob_start();
      extract($this->params);
      try {
        require $tpl;
        $rendered = ob_get_contents();
        ob_end_clean();
        return $rendered;
      } catch (\Exception $e) {
        ob_end_clean();
        throw $e;
      }
    }
    
    /**
     * Output the rendered template
     * 
     * @param string $tpl  The template to render
     * @param array  $vars An associative key-value array for mass template
     *                     variable assignment at output time.
     * 
     * @return void
     */
    public function output($tpl, array $vars=[])
    {
      $output = $this->render($tpl, $vars);
      echo $output;
    }
  }
}

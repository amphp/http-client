<?php

/**
 * ArtaxView Class File
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
   * ArtaxView Class
   * 
   * @category   artax
   * @package    blocks
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
     * @param mixed  $var  Variable contents
     * 
     * @return \artax\Views\Smarty Object instance for method chaining
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
     * @return mixed Variable contents
     */
    public function getVar($name)
    {
      return $this->get($name);
    }
    
    /**
     * Retrieve the rendered template without outputting its contents
     * 
     * @return string Rendered template
     */
    public function render()
    {
      ob_start();
      extract($this->params);
      try {
        require $this->template;
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
     * @return void
     */
    public function output()
    {
      echo $this->render();
    }
    
    /**
     * Setter method for object $template property
     * 
     * @param string $tpl Template path (relative to SMARTY_TPL_DIR)
     * 
     * @return \artax\Views\Smarty Object instance for method chaining
     */
    public function setTemplate($tpl)
    {
      $this->template = $tpl;
      return $this;
    }
  }
}

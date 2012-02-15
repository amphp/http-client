<?php

/**
 * Artax Smarty View File
 * 
 * PHP version 5.3
 * 
 * @category   artax
 * @package    blocks
 * @subpackage views
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace artax\blocks\views {
  
  /**
   * Artax Smarty View Class
   * 
   * The class integrates Smarty templating functionality via magic methods.
   * All smarty functions are available as member methods of the current object.
   * Additionally, once a variable is assigned to the Smarty object, 
   * it is available as a magic property of the current object.
   *
   * @category   artax
   * @package    blocks
   * @subpackage views
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class Smarty implements ViewInterface
  {    
    /**
     * Smarty class instance
     * @var Smarty
     */
    protected $smarty;
    
    /**
     * Smarty template path (relative to SMARTY_TPL_DIR)
     * @var string
     */
    protected $template;
    
    /**
     * Smarty template cache_id
     * @var string
     */
    protected $cache_id;
    
    /**
     * Smarty template compile_id
     * @var string
     */
    protected $compile_id;
    
    /**
     * Initializes object's Smarty property
     * 
     * @access public
     * @return void
     */
    public function __construct(\Smarty $smarty=NULL)
    {
      if ($smarty) {
        $this->setSmarty($smarty);
      }
    }
    
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
      $this->smarty->assign($name, $var);
      return $this;
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
      return isset($this->smarty->tpl_vars[$var])
        ? $this->smarty->tpl_vars[$var]
        : NULL;
    }
    
    /**
     * Retrieve the rendered template without outputting its contents
     * 
     * @return string Rendered template
     */
    public function render()
    {
      $template   = $this->template ? $this->template : '';
      $cache_id   = $this->cache_id ? $this->cache_id : '';
      $compile_id = $this->compile_id ? $this->compile_id : '';
      
      return $this->smarty->fetch($template, $cache_id, $compile_id);
    }
    
    /**
     * Render and output the template to STDOUT
     * 
     * @return void
     */
    public function output()
    {
      $template   = $this->template ? $this->template : '';
      $cache_id   = $this->cache_id ? $this->cache_id : '';
      $compile_id = $this->compile_id ? $this->compile_id : '';
      
      $this->smarty->display($template, $cache_id, $compile_id);
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
    
    /**
     * Setter method for object's $smarty property
     * 
     * @param \Smarty Smarty class instance
     * 
     * @return \artax\Views\Smarty Object instance for method chaining
     */
    public function setSmarty(\Smarty $smarty)
    {
      $this->smarty = $smarty;
      return $this;
    }
    
    /**
     * Setter method for object $cache_id property
     * 
     * @param string $cache_id Cache ID
     * 
     * @return \artax\Views\Smarty Object instance for method chaining
     */
    public function setCacheId($cache_id)
    {
      $this->cache_id = (string)$cache_id;
      return $this;
    }
    
    /**
     * Setter method for object $compile_id property
     * 
     * @param string $compile_id Compile ID
     * 
     * @return \artax\Views\Smarty Object instance for method chaining
     */
    public function setCompileId($compile_id)
    {
      $this->compile_id = (string)$compile_id;
      return $this;
    }
    
    /**
     * Checks Smarty object's directory paths to ensure functionality
     * 
     * @param \Smarty Smarty class instance
     * 
     * @return bool TRUE on successful validation or FALSE on failure
     */
    public static function validateSmartyDirs(\Smarty $smarty)
    {
      ob_start();
      $smarty->testInstall();
      $r = stristr(ob_get_contents(), 'FAILED') ? FALSE : TRUE;
      ob_end_clean();
      return $r;
    }
    
    /**
     * Magic method mapping function calls to the Smarty object
     *
     * @return mixed
     * @throws \artax\BadMethodCallException On invalid property
     */
    public function __call($method, $args)
    {
      $callback = array($this->smarty, $method);
      if ($this->smarty && is_callable($callback)) {
        call_user_func_array($callback, $args);
      } else {
        $msg = "Invalid method: $method() does not exist or is not " .
          'accessible in the current scope';
        throw new \artax\BadMethodCallException($msg);
      }
    }
    
    /**
     * Magic Smarty object read properties
     *
     * @return mixed Specified property value
     * @throws \artax\OutOfBoundsException On invalid property
     */
    public function __get($prop)
    {      
      try {
        return $this->smarty->$prop;
      } catch (\Exception $e) {
        $msg = "Invalid property: $$prop does not exist or is not accessible " .
          'in the current scope';
        throw new \artax\OutOfBoundsException($msg);
      }
    }
    
    /**
     * Magic Smarty object property assignment
     *
     * @return void
     * @throws \artax\OutOfBoundsException On invalid property
     */
    public function __set($prop, $val)
    {      
      try {
        $this->smarty->$prop = $val;
        return;
      } catch (\Exception $e) {
        $msg = "Invalid property: $$prop does not exist or is not accessible " .
          'in the current scope';
        throw new \artax\OutOfBoundsException($msg);
      }
    }
  }
}

?>

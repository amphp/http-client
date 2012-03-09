<?php

/**
 * DotNotation Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Ioc
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace Artax\Ioc {

  /**
   * DotNotation Class
   * 
   * The class is used to transfrom dot-notation class names between valid
   * PHP style and the DotNotation style:
   * 
   * `Namespace.ClassName` to `\Namespace\ClassName`
   * 
   * @category   Artax
   * @package    Ioc
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class DotNotation
  {
    /**
     * Parses namespaced class names to and from dot notation.
     * 
     * @param string $dotStr  The dot-notation string to parse
     * @param bool   $reverse Parse a standard class name to dot notation
     * 
     * @return mixed Returns a transformed class name.
     */
    public function parse($dotStr, $reverse = FALSE)
    {
        $repl = !$reverse ? '.' : '\\';
        $new  = !$reverse ? '\\' : '.';
        $cls  = !$reverse ? '\\' : ''; // Don't prepend dot on reverse
        $str  = trim($dotStr, $repl);        
        return $cls . str_replace($repl, $new, $str);;
    }
  }
}

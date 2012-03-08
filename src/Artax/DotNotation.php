<?php

/**
 * DotNotation Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace Artax {

  /**
   * DotNotation Class
   * 
   * The DotNotation class is used to transfrom dot-notation class names from
   * the format `namespace.ClassName.methodName` or `namespace.ClassName` to
   * a valid PHP namespaced class names with an optionally associated method.
   * 
   * @category   Artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class DotNotation
  {
    /**
     * Parses namespaced class and method components to and from dot notation
     * 
     * @param string $dotStr  The dot-notation string to parse
     * @param bool   $reverse Parse a standard class name to dot notation
     * @param bool   $method  Whether or not the string contains a method
     * 
     * @return mixed Returns an array of class and method name or a string class
     *               name if the `$method` parameter is FALSE.
     */
    public function parse($dotStr, $reverse=FALSE, $method=FALSE)
    {
      $repl = ! $reverse ? '.' : '\\';
      $new  = ! $reverse ? '\\' : '.';
      $cls  = ! $reverse ? '\\' : ''; // Don't prepend dot to class names on reverse
      $str  = trim($dotStr, $repl);
      
      if ( ! $method) {
        $r = $cls . str_replace($repl, $new, $str);
      } elseif (strstr($str, $repl)) {
        $parts = explode($repl, $str);
        $func  = array_pop($parts);
        $cls  .= implode($new, $parts);
        $r     = [$cls, $func];
      }
      return $r;
    }
  }
}

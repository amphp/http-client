<?php

/**
 * DotNotation Class File
 * 
 * PHP version 5.4
 * 
 * @category   artax
 * @package    core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
 
namespace artax {

  /**
   * DotNotation Class
   * 
   * The DotNotation class is used to transfrom dot-notation class names from
   * the format `namespace.ClassName.methodName` or `namespace.ClassName` to
   * a valid PHP namespaced class names with an optionally associated method.
   * 
   * @category   artax
   * @package    core
   * @author     Daniel Lowrey <rdlowrey@gmail.com>
   */
  class DotNotation
  {
    /**
     * Parses namespaced class and method components from dot notation string
     * 
     * @param string $dotStr The dot-notation string to parse
     * @param bool   $method Whether or not the string contains a method
     * 
     * @return mixed Returns an array of class and method name or a string class
     *               name if the `$method` parameter is FALSE.
     */
    public function parse($dotStr, $method=FALSE)
    {
      $str = trim($dotStr, '.');
      
      if ( ! $str) {
        $msg = 'Invalid dot notation string: ' . $dotStr ?: 'none';
        throw new exceptions\InvalidArgumentException($msg);
      } elseif ( ! $method) {
        $r = '\\' . str_replace('.', '\\', $str);
      } elseif (strstr($str, '.')) {
        $parts = explode('.', $str);
        $func  = array_pop($parts);
        $class  = '\\' . implode('\\', $parts);
        $r = [$class, $func];
      }
      return $r;
    }
  }
}

<?php

/**
 * Artax ProviderInterface Interface File
 * 
 * PHP version 5.4
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Ioc;
  
/**
 * ProviderInterface
 * 
 * @category Artax
 * @package  Ioc
 * @author   Daniel Lowrey <rdlowrey@gmail.com>
 */
interface ProviderInterface
{
    /**
     * Factory method for object instantiation
     * 
     * @param string $type   A DotNotation string class name
     * @param array  $custom A key/value array specifying custom dependencies
     */
    public function make($type, array $custom);
}

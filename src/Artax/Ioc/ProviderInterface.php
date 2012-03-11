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
 * Specifies a front-facing interface for dependency providers.
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
     * @param string $dotStr A dot-notation class name
     */
    public function make($dotStr);
}

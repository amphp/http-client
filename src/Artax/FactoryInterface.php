<?php

/**
 * Artax FactoryInterface Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax;

/**
 * Factory interface for non-static factories
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
interface FactoryInterface
{
    /**
     * Factory method for object instantiation
     */
    public function make();
}

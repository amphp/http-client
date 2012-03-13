<?php

/**
 * Artax FactoryAbstract Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax;

/**
 * An abstract factory class specifying a non-static factory method.
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
abstract class FactoryAbstract
{
    /**
     * Factory method for object instantiation
     */
    abstract public function make();
}

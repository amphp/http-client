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
 * Prior to PHP 5.4's introduction of constructor dereferencing, static factory
 * methods were sometimes desirable in order to manufacture an object without
 * cluttering the global namespace with factory object instantiations. This
 * can now be done without the use of `static`, so we use a concrete
 * instantiation for all factories:
 * 
 * ```php
 * (new MyClassFactory)->make();
 * ```
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

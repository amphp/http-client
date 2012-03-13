<?php

/**
 * Artax ProviderFactory Class File
 * 
 * PHP version 5.4
 * 
 * @category   Artax
 * @package    Ioc
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */

namespace Artax\Ioc;

/**
 * A factory class to provision Provider dependency containers.
 * 
 * `Provider` instances require access to the parsing functionality in
 * the `DotNotation` class to resolve dot-notation class names. The factory
 * method provisions and returns a new provider instance.
 * 
 * @category   Artax
 * @package    Ioc
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class ProviderFactory extends \Artax\FactoryAbstract
{
    /**
     * Factory method to instantiate an injected Provider
     * 
     * @return Provider Returns an injected Provider instance
     */
    public function make()
    {
        return new Provider(new DotNotation);
    }
}

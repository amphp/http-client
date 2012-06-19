<?php

/**
 * Artax ProviderDefinitionException File
 * 
 * PHP version 5.3
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 * @copyright  ${copyright.msg}
 * @license    All code subject to the ${license.name}
 * @version    ${project.version}
 */
 
namespace Artax;
use LogicException;

/**
 * Exception thrown when the Provider cannot correctly instantiate a class
 * 
 * This exception can be thrown if an invalid injection definition was
 * specified for a class the Provider is attempting to instantiate. It can
 * also result if the Provider is asked to instantiate a class whose
 * constructor has arguments that either:
 * 
 * 1. Don't specify a class typehint (array and callable typehints won't work)
 * 2. Fail to specify NULL as the default value if not typehinted
 * 
 * The last way this exception can be thrown is if the Provider is asked to
 * instantiate a class that doesn't exist or cannot be autoloaded by any
 * registered class loaders.
 * 
 * @category   Artax
 * @package    Core
 * @author     Daniel Lowrey <rdlowrey@gmail.com>
 */
class ProviderDefinitionException extends LogicException {}

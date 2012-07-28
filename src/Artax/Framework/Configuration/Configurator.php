<?php
/**
 * Configurator Class File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Configuration;

use Artax\Injection\Injector,
    Artax\Events\Mediator;

/**
 * Applies application configuration directives specified in the supplied Config instance
 * 
 * Both application config files and plugin manifests influence operation by adding listeners
 * to the system event mediator and definitions to the system's dependency injection container. 
 * This class applies the directives of a parsed configuration object to the mediator and injector.
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class Configurator {
    
    private $injector;
    private $mediator;
    
    public function __construct(Injector $injector, Mediator $mediator) {
        $this->injector = $injector;
        $this->mediator = $mediator;
    }
    
    public function configure(Config $config) {
        if ($config->has('requiredFiles')) {
            foreach ($config->get('requiredFiles') as $filepath) {
                $this->requireFile($filepath);
            }
        }
        
        if ($config->has('eventListeners')) {
            $this->mediator->pushAll($config->get('eventListeners'));
        }
        
        if ($config->has('injectionDefinitions')) {
            $this->injector->defineAll($config->get('injectionDefinitions'));
        }
        
        if ($config->has('injectionImplementations')) {
            $this->injector->implementAll($config->get('injectionImplementations'));
        }
        
        if ($config->has('sharedClasses')) {
            $this->injector->shareAll($config->get('sharedClasses'));
        }
    }
    
    protected function requireFile($filepath) {
        if (false === @include $filepath) {
            throw new ConfigException("Failed loading required file: $filepath");
        }
    }
}

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
 * Applies application configuration directives from the supplied Config instance
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
    
    public function apply(Config $config) {
        if ($requiredFiles = $config->get('requiredFiles')) {
            foreach ($requiredFiles as $filepath) {
                $this->requireFile($filepath);
            }
        }
        
        if ($eventListeners = $config->get('eventListeners')) {
            $this->mediator->pushAll($eventListeners);
        }
        
        if ($injectionDefinitions = $config->get('injectionDefinitions')) {
            $this->injector->defineAll($injectionDefinitions);
        }
        
        if ($injectionImplementations = $config->get('injectionImplementations')) {
            $this->injector->implementAll($injectionImplementations);
        }
        
        if ($sharedClasses = $config->get('sharedClasses')) {
            $this->injector->shareAll($sharedClasses);
        }
    }
    
    protected function requireFile($filepath) {
        if (false === @include $filepath) {
            throw new ConfigException("Failed loading required file: $filepath");
        }
    }
}

<?php

namespace Artax\Framework;

use Artax\Injection\Injector,
    Artax\Events\Mediator,
    Artax\Framework\Config\Config;

class BootConfigurator {
    
    private $injector;
    private $mediator;
    
    public function __construct(Injector $injector, Mediator $mediator) {
        $this->injector = $injector;
        $this->mediator = $mediator;
    }
    
    public function configure(Config $config) {
        if ($config->get('applyRouteShortcuts')) {
            $this->enableRouteShortcuts();
        }
        
        if ($config->get('autoResponseContentLength')) {
            $this->enableAutoResponseContentLength();
        }
        
        if ($config->get('autoResponseDate')) {
            $this->enableAutoResponseDate();
        }
        
        if ($config->get('autoResponseStatus')) {
            $this->enableAutoResponseStatus();
        }
        
        if ($config->get('autoResponseEncode')) {
            $encodableMediaRanges = $config->get('autoResponseEncodeMediaRanges');
            $this->enableAutoResponseEncode($encodableMediaRanges);
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
    
    protected function enableRouteShortcuts() {
        $this->injector->share('Artax\\Framework\\Plugins\\RouteShortcuts');
        $this->mediator->push(
            '__sys.route.new',
            'Artax\\Framework\\Plugins\\RouteShortcuts'
        );
    }
    
    protected function enableAutoResponseContentLength() {
        $this->mediator->push(
            '__sys.response.beforeSend',
            'Artax\\Framework\\Plugins\\AutoResponseContentLength'
        );
    }
    
    protected function enableAutoResponseDate() {
        $this->mediator->push(
            '__sys.response.beforeSend',
            'Artax\\Framework\\Plugins\\AutoResponseDate'
        );
    }
    
    protected function enableAutoResponseStatus() {
        $this->mediator->push(
            '__sys.response.beforeSend',
            'Artax\\Framework\\Plugins\\AutoResponseStatus'
        );
    }
    
    protected function enableAutoResponseEncode(array $encodableMediaRanges) {
        $this->injector->define('Artax\\Framework\\Plugins\\AutoResponseEncode', array(
            'request' => 'Artax\\Http\\StdRequest',
            ':encodableMediaRanges' => $encodableMediaRanges
        ));
            
        $this->mediator->push(
            '__sys.response.beforeSend',
            'Artax\\Framework\\Plugins\\AutoResponseEncode'
        );
    }
}

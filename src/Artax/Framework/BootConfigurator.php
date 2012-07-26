<?php

namespace Artax\Framework;

use Artax\Injection\Injector,
    Artax\Events\Mediator,
    Artax\Framework\Config\Config;

class BootConfigurator {
    
    private $mediator;
    
    public function __construct(Mediator $mediator) {
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
            $this->enableAutoResponseEncode();
        }
    }
    
    protected function enableRouteShortcuts() {
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
    
    protected function enableAutoResponseEncode() {
        $this->mediator->push(
            '__sys.response.beforeSend',
            'Artax\\Framework\\Plugins\\AutoResponseEncode'
        );
        
        $this->mediator->push(
            '__sys.ready',
            'Artax\\Framework\\Plugins\\AutoResponseEncodeMediaRanges'
        );
    }
}

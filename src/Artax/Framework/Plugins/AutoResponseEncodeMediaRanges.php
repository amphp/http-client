<?php
/**
 * AutoResponseEncodeMediaRanges class file
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Plugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Plugins;

use Artax\Framework\Config\Config;

/**
 * ...
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Plugins
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class AutoResponseEncodeMediaRanges {
    
    public function __construct(Config $config, Injector $injector) {
        $this->config = $config;
        $this->injector = $injector;
    }
    
    public function __invoke() {
        if ($customEncodeRanges = $this->config->get('autoResponseEncodeRanges')) {
            $this->assignCustomEncodeRanges($customEncodeRanges);
        }
    }
    
    public function assignCustomEncodeRanges(array $customEncodeRanges) {
        $this->injector->share('Artax\\Framework\\Plugins\\AutoResponseEncode');
        $autoEncoder = $this->injector->make('Artax\\Framework\\Plugins\\AutoResponseEncode');
        $autoEncoder->setEncodableMediaRanges($customEncodeRanges);
    }
}

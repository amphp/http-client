<?php

namespace Artax\Framework\Config;

use SplFileInfo;

class ConfigFactory {
    
    public function make($configFileName) {
        $fileInfo = new SplFileInfo($configFileName);
        $ext = strtolower($fileInfo->getExtension());
        
        switch ($ext) {
            case 'php' :
                $cfgParser = new PhpConfigParser($fileInfo);
                break;
            case 'xml':
                $cfgParser = new XmlConfigParser($fileInfo);
                break;
            default:
                throw new DomainException("Invalid configuration file type: $ext");
        }
        
        return new Config($cfgParser->parse());
    }
    
}

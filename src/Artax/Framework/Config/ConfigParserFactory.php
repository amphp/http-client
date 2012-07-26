<?php

namespace Artax\Framework\Config;

use SplFileInfo,
    DomainException;

class ConfigParserFactory {
    
    /**
     * @param string $configFilepath
     * @return mixed
     * @throws DomainException
     */
    public function make($configFilepath) {
        $extension = pathinfo($configFilepath, PATHINFO_EXTENSION);
        $lowerExt = strtolower($extension);
        
        switch ($lowerExt) {
            case 'php' :
                return new Parsers\PhpConfigParser;
            case 'xml':
                return new Parsers\XmlConfigParser;
            default:
                throw new DomainException(
                    "Invalid configuration file type: $lowerExt"
                );
        }
    }
}

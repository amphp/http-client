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
        $fileInfo = new SplFileInfo($configFilepath);
        $lowerExt = strtolower($fileInfo->getExtension());
        
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

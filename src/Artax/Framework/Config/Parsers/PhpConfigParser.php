<?php

namespace Artax\Framework\Config\Parsers;

use StdClass,
    Traversable,
    Artax\Framework\Config\ConfigException;

class PhpConfigParser implements ConfigParser {
    
    /**
     * @param string $configFilepath
     * @return mixed
     * @throws ConfigException
     */
    public function parse($configFilepath) {
        require $configFilepath;
        
        if (isset($cfg)
            && ($cfg instanceof StdClass || $cfg instanceof Traversable || is_array($cfg))
        ) {
            return $cfg;
        }
        throw new ConfigException(
            'Config file must specify an array, StdClass or Traversable $cfg variable'
        );
    }
}

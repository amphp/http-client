<?php

namespace Artax\Framework\Config\Parsers;

interface ConfigParser {
    
    /**
     * @param string $configFilepath
     */
    function parse($configFilepath);
}

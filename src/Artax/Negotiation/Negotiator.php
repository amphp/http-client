<?php

namespace Artax\Negotiation;

interface Negotiator {
    
    const QVAL_SIGNIFICANT_DIGITS = 4;
    
    /**
     * @param string $rawAcceptHeaderStr
     * @param array $availableTypes
     * @return string
     */
    function negotiate($rawHeaderStr, array $availableTypes);
}

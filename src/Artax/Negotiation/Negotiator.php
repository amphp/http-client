<?php

namespace Artax\Negotiation;

interface Negotiator {
    
    const QVAL_NOT_ACCEPTABLE = 0;
    const QVAL_SIGNIFICANT_DIGITS = 4;
    
    /**
     * @param string $rawHeaderStr
     * @param array $availableTypes
     * @return string
     */
    function negotiate($rawHeaderStr, array $availableTypes);
}

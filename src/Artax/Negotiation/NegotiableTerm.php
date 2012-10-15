<?php

namespace Artax\Negotiation;

interface NegotiableTerm {

    /**
     * Required for array_diff operations on acceptable vs. rejectable terms
     * 
     * @return string
     */
    function __toString();
    
    /**
     * @return int
     */
    function getPosition();
    
    /**
     * @return mixed
     */
    function getType();
    
    /**
     * @return float
     */
    function getQuality();
    
    /**
     * @return bool
     */
    function hasExplicitQuality();
}

<?php

namespace Artax\Negotiation;

/**
 * An immutable value object used during the content-negotiation process
 */
class ScratchTerm {
    
    private $position;
    private $negotiatedType;
    private $negotiatedQuality;
    private $isExplicit;
    
    public function __construct($position, $negotiatedType, $negotiatedQval, $isExplicit) {
        $this->position = $position;
        $this->negotiatedType = $negotiatedType;
        $this->negotiatedQuality = $negotiatedQval;
        $this->isExplicit = $isExplicit;
    }
    
    public function getPosition() {
        return $this->position;
    }
    
    public function getType() {
        return $this->negotiatedType;
    }
    
    public function getQval() {
        return $this->negotiatedQuality;
    }
    
    public function isExplicit() {
        return $this->isExplicit;
    }
}
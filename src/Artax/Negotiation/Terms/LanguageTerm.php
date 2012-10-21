<?php

namespace Artax\Negotiation\Terms;

class LanguageTerm extends Term implements MultipartTerm {
    
    const DELIMITER = '-';
    
    /**
     * @var string
     */
    private $topLevelType;
    
    /**
     * @var string
     */
    private $subType;
    
    /**
     * @param int $position
     * @param string $type
     * @param float $quality
     * @param bool $hasExplictQuality
     * @return void
     */
    public function __construct($position, $type, $quality, $hasExplicitQuality) {
        // rfc2616-sec3.10: "... tags are case-insensitive."
        $type = strtolower($type);
        
        list($topLevelType, $subType) = $this->getTypeParts($type);
        
        $this->topLevelType = $topLevelType;
        $this->subType = $subType;
        
        parent::__construct($position, $type, $quality, $hasExplicitQuality);
    }
    
    /**
     * @return array($topLevelType, $subType)
     */
    protected function getTypeParts($type) {
        $parts = explode(self::DELIMITER, $type);
        $topLevelType = $parts[0];
        $subType = isset($parts[1]) ? $parts[1] : null;
        
        return array($topLevelType, $subType);
    }
    
    /**
     * @return string
     */
    public function getTopLevelType() {
        return $this->topLevelType;
    }
    
    /**
     * @return string
     */
    public function getSubType() {
        return $this->subType;
    }
    
    /**
     * @param string $languageTerm
     * @return bool
     */
    public function rangeMatches($languageTerm) {
        if (self::WILDCARD == $this->getType() || self::WILDCARD == $languageTerm) {
            return true;
        }
        
        // rfc2616-sec3.10: "... tags are case-insensitive."
        $languageTerm = strtolower($languageTerm);
        
        list($topLevelType, $subType) = $this->getTypeParts($languageTerm);
        
        if ($topLevelType == $this->getTopLevelType()
            && (is_null($this->getSubType()) || ($subType == $this->getSubType()))
        ) {
            return true;
        }
        
        return false;
    }
}
<?php

namespace Artax\Negotiation;

class ContentTypeTerm extends AcceptTerm implements RangeTerm {
    
    const WILDCARD = '*';
    const DELIMITER = '/';
    
    /**
     * @var MediaRange
     */
    private $mediaRange;
    
    /**
     * @param int $position
     * @param string $type
     * @param float $quality
     * @param bool $hasExplictQuality
     * @throws \Spl\ValueException On invalid media range value
     * @return void
     */
    public function __construct($position, $type, $quality, $hasExplicitQuality) {
        $this->mediaRange = new MediaRange($type);
        parent::__construct($position, $type, $quality, $hasExplicitQuality);
    }
    
    /**
     * @return string
     */
    public function getTopLevelType() {
        return $this->mediaRange->getTopLevelType();
    }
    
    /**
     * @return string
     */
    public function getSubType() {
        return $this->mediaRange->getSubType();
    }
    
    /**
     * @param string $languageTerm
     * @return bool
     */
    public function rangeMatches($term) {
        $fullWildcard = self::WILDCARD . self::DELIMITER . self::WILDCARD;
        
        if ($this->mediaRange == $fullWildcard || $term == $fullWildcard) {
            return true;
        }
        if ($this->mediaRange == $term) {
            return true;
        }
        
        list($topLevelType, $subType) = explode(self::DELIMITER, $term);
        
        if (self::WILDCARD == $this->getSubType() && $topLevelType == $this->getTopLevelType()) {
            return true;
        }

        return false;
    }
}

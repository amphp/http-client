<?php
/**
 * HTTP Header MediaRangeTerm Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @package      Http
 * @subpackage   Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax\Negotiation;

use InvalidArgumentException,
    Artax\MimeType,
    Artax\MediaRange;

/**
 * Models negotiable media-range terms parsed from HTTP Accept headers
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class MediaRangeTerm extends AcceptTerm {
    
    /**
     * @param int $position
     * @param MediaRange $type
     * @param float $quality
     * @param bool $explictQuality
     * @return void
     */
    public function __construct($position, MediaRange $type, $quality, $explicitQuality) {
        parent::__construct($position, $type, $quality, $explicitQuality);
    }
    
    /**
     * @return string
     */
    public function __toString() {
        return $this->type->__toString();
    }
    
    /**
     * @return string
     */
    public function getRangeTopLevelType() {
        return $this->type->getTopLevelType();
    }
    
    /**
     * @return string
     */
    public function getRangeSubType() {
        return $this->type->getSubType();
    }
    
    /**
     * @return string
     */
    public function getRangeSuffix() {
        return $this->type->getSuffix();
    }
    
    /**
     * @return bool
     */
    public function isRangeExperimental() {
        return $this->type->isExperimental();
    }
    
    /**
     * @param MimeType $mimeType
     * @return bool
     */
    public function rangeMatches(MimeType $mimeType) {
        return $this->type->matches($mimeType);
    }
}

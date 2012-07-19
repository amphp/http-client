<?php
/**
 * Accept HTTP Header Parser Class File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Negotiation\Parsers;

use InvalidArgumentException,
    Artax\MimeType,
    Artax\MimeTypeFactory,
    Artax\Negotiation\NegotiableTerm,
    Artax\Negotiation\MediaRangeFactory,
    Artax\Negotiation\MediaRangeTerm,
    Artax\Negotiation\MediaRangeTermFactory;

/**
 * Parses content-types from the raw Accept header, ordering terms by client-preference
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class AcceptParser extends BaseParser {
    
    /**
     * @var MediaRangeTermFactory
     */
    private $mediaRangeTermFactory;
    
    /**
     * @param MediaRangeTermFactory $mediaRangeTermFactory
     * @return void
     */
    public function __construct(MediaRangeTermFactory $mediaRangeTermFactory = null) {
        $this->mediaRangeTermFactory = $mediaRangeTermFactory ?: new MediaRangeTermFactory;
    }
    
    /**
     * @param string $rawHeader
     * @return array
     */
    protected function getTermsFromRawHeader($rawHeader) {
        $terms = array();
        
        foreach (preg_split('/\s*,\s*/', $rawHeader) as $position => $term) {
            if (preg_match("{^(\S+)\s*;\s*(?:q|level)=([0-9\.]+)}i", $term, $match)) {
                $type = $match[1];
                $quality = $match[2];
                $hasExplicitQuality = true;
            } else {
                $type = $term;
                $quality = 1;
                $hasExplicitQuality = false;
            }
            
            try {
                $terms[] = $this->mediaRangeTermFactory->make(
                    $position, $type, $quality, $hasExplicitQuality
                );
            } catch (InvalidArgumentException $e) {
                continue;
            }
        }
        
        return $terms;
    }
    
    /**
     * @return int
     */
    protected function sortQualityTie(NegotiableTerm $a, NegotiableTerm $b) {
        if ($b->__toString() == '*/*' && $a->__toString() != '*/*') {
            return -1;
        } elseif ($b->__toString() != '*/*' && $a->__toString() == '*/*') {
            return 1;
        } if ($b->getRangeSubType() == '*' && $a->getRangeSubType() != '*') {
            return -1;
        } elseif ($b->getRangeSubType() != '*' && $a->getRangeSubType() == '*') {
            return 1;
        } elseif ($explicitDiff = ($b->hasExplicitQuality() - $a->hasExplicitQuality())) {
            return $explicitDiff/abs($explicitDiff);
        } else {
            // All other sorting factors being equal, the first term specified wins.
            return $a->getPosition() - $b->getPosition();
        }
    }
}

<?php
/**
 * MediaRangeTermFactory Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Negotiation;

use Artax\MediaRangeFactory;

/**
 * Generates MediaRangeTerm instances
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class MediaRangeTermFactory {
    
    /**
     * @var MediaRangeFactory
     */
    private $mediaRangeFactory;
    
    /**
     * @param MediaRangeFactory $mediaRangeFactory
     */
    public function __construct(MediaRangeFactory $mediaRangeFactory = null) {
        $this->mediaRangeFactory = $mediaRangeFactory ?: new MediaRangeFactory;
    }
    
    /**
     * @param int $position
     * @param string $type
     * @param float $quality
     * @param bool $explictQuality
     * @return void
     * @throws InvalidArgumentException
     */
    public function make($position, $type, $quality, $explicitQuality) {
        $mediaRange = $this->mediaRangeFactory->make($type);
        return new MediaRangeTerm($position, $mediaRange, $quality, $explicitQuality);
    }
}

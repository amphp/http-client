<?php
/**
 * MediaRangeFactory Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax;

/**
 * Generates MediaRange instances
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class MediaRangeFactory {
    
    /**
     * @param string $mediaRange
     * @return MediaRange
     * @throws UnexpectedValueException
     */
    public function make($mediaRange) {
        return new MediaRange($mediaRange);
    }
}

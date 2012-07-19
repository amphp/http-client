<?php
/**
 * AcceptTermFactory Class File
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

/**
 * Generates AcceptTerm instances
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class AcceptTermFactory {
    
    /**
     * @param int $position
     * @param mixed $type
     * @param float $quality
     * @param bool $explictQuality
     * @return void
     * @throws InvalidArgumentException
     */
    public function make($position, $type, $quality, $explicitQuality) {
        return new AcceptTerm($position, $type, $quality, $explicitQuality);
    }
}

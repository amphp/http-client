<?php
/**
 * HTTP Header AcceptTerm Class File
 * 
 * @category     Artax
 * @package      Http
 * @subpackage   Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Http\Negotiation;

use InvalidArgumentException;

class AcceptTerm implements NegotiableTerm {
    
    /**
     * @var string
     */
    protected $type;
    
    /**
     * @var int
     */
    private $position;
    
    /**
     * @var float
     */
    private $quality;
    
    /**
     * @var bool
     */
    private $explicitQuality;
    
    /**
     * @param int $position
     * @param string $type
     * @param float $quality
     * @param bool $explictQuality
     * @return void
     */
    public function __construct($position, $type, $quality, $explicitQuality) {
        $this->position = (int) $position;
        $this->type = $type;
        $this->quality = (float) $quality;
        $this->explicitQuality = (bool) $explicitQuality;
    }
    
    /**
     * @return string
     */
    public function __toString() {
        return $this->type;
    }
    
    /**
     * @return int
     */
    public function getPosition() {
        return $this->position;
    }
    
    /**
     * @return mixed
     */
    public function getType() {
        return $this->type;
    }
    
    /**
     * @return float
     */
    public function getQuality() {
        return $this->quality;
    }
    
    /**
     * @return bool
     */
    public function hasExplicitQuality() {
        return $this->explicitQuality;
    }
}

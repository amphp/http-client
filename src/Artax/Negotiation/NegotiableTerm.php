<?php
/**
 * NegotiableTerm Interface File
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
 * A design contract for modeling negotiable terms specified in HTTP Accept headers
 * 
 * @category     Artax
 * @package      Negotiation
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
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

<?php
/**
 * HeaderNegotiator Interface File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */
namespace Artax\Negotiation\Negotiators;

/**
 * A design contract for negotiating response parameters from raw Accept headers
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface HeaderNegotiator {

    /**
     * @param string $rawAcceptHeaderStr
     * @param array $availableTypes
     * @return string
     */
    function negotiate($rawAcceptHeaderStr, array $availableTypes);
}

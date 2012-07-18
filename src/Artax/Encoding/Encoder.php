<?php
/**
 * Encoder Interface File
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax\Encoding;

/**
 * An interface for content encoders
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Encoder {
    
    /**
     * @param string $dataToBeEncoded
     * @return string
     */
    function encode($dataToBeEncoded);
}

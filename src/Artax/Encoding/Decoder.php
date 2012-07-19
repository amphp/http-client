<?php
/**
 * Decoder Interface File
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Encoding;

/**
 * An interface for content decoders
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Decoder {
    
    /**
     * @param string $dataToBeDecoded
     * @return string
     */
    function decode($dataToBeDecoded);
}

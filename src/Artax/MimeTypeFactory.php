<?php
/**
 * MimeTypeFactory Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax;

/**
 * Generates MimeType instances
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class MimeTypeFactory {
    
    /**
     * @param string $mimeType
     * @return MimeType
     * @throws InvalidArgumentException
     */
    public function make($mimeType) {
        return new MimeType($mimeType);
    }
}

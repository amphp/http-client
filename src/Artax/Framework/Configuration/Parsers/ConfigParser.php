<?php
/**
 * ConfigParser Interface File
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Framework\Configuration\Parsers;

/**
 * A design contract for configuration parsers
 * 
 * @category    Artax
 * @package     Framework
 * @subpackage  Configuration
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface ConfigParser {
    
    /**
     * @param string $configFilepath
     */
    function parse($configFilepath);
}

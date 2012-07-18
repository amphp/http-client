<?php
/**
 * CodecException Class File
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax\Encoding;

use RuntimeException;

/**
 * Exception thrown on codec encoding/decoding failure
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class CodecException extends RuntimeException {}

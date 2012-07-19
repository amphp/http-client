<?php
/**
 * Codec Interface File
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the project root
 * @version      ${project.version}
 */
namespace Artax\Encoding;

/**
 * An interface for content encoding codecs
 * 
 * @category     Artax
 * @package      Encoding
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
interface Codec extends Encoder, Decoder {}

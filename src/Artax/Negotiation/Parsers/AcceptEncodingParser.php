<?php
/**
 * AcceptEncoding HTTP Header Parser Class File
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */

namespace Artax\Negotiation\Parsers;

/**
 * Parses codings from the raw Accept-Encoding header, ordering by client-preference
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class AcceptEncodingParser extends BaseParser {}
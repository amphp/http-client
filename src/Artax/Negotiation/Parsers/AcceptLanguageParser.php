<?php
/**
 * AcceptLanguage HTTP Header Parser Class File
 * 
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the base package directory
 * @version     ${project.version}
 */

namespace Artax\Negotiation\Parsers;

/**
 * Parses languages from the raw Accept-Language header, ordering by client-preference
 * 
 * @category    Artax
 * @package     Negotiation
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
class AcceptLanguageParser extends BaseParser {}

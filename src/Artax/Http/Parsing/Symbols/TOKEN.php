<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * Any character that isn't a CTL or separator
 * 
 * token      = 1*<any CHAR except CTLs or separators>
 * separators = "(" | ")" | "<" | ">" | "@"
 *            | "," | ";" | ":" | "\" | <">
 *            | "/" | "[" | "]" | "?" | "="
 *            | "{" | "}" | SP | HT
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class TOKEN extends CHAR {}
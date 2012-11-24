<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * CTL = <any US-ASCII control character (octets 0 - 31) and DEL (127)>
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class CTL extends CHAR {}
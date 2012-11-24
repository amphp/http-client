<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * AN UPALPHAHEX symbol matching [A-F]
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class UPALPHAHEX extends UPALPHA implements HEX {}
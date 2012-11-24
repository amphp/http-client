<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * A LOALPHAHEX symbol matching [a-f]
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class LOALPHAHEX extends LOALPHA implements HEX {}
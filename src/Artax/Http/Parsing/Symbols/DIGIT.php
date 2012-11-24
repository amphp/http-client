<?php

namespace Artax\Http\Parsing\Symbols;

/**
 * DIGIT = <any US-ASCII digit "0".."9">
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class DIGIT extends TOKEN implements HEX {}
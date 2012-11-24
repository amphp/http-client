<?php

namespace Artax\Http\Parsing;

use Iterator,
    RuntimeException,
    Spl\TypeException,
    Spl\DomainException;

/**
 * Generates tokens from a character stream according to the ABNF grammar in RFC 2616 Section 2.2
 * 
 * The design and storage of tokens was carefully implemented to take advantage of the PHP language
 * constructs `isset` and `instanceof` to maximize tokenization and parsing speed. These 
 * considerations are discussed in detail below.
 * 
 * DESIGN CONSIDERATIONS
 * ===
 * 
 * ### Token Design
 * 
 * Generated tokens take the form of "Symbols." These symbols are objects representing the
 * specific type of an individual character as delineated by the ABNF grammar of RFC 2616. PHP's
 * object model affords several advantages with this approach:
 * 
 * 1. Symbol::__toString() can be used for equivalence comparisons for specific characters
 * 
 * 2. Storing characters in symbol instances allows for high performance memory management. HTTP
 * messages of hundreds of kilobytes or more can be tokenized with ~200 symbols when these
 * instances are cached and accessed by reference.
 * 
 * 3. Object-based symbols allow us to easily model the inheritance heirarchies of the relevant ABNF
 * rules. For example, consider the following inheritance tree for the "LOALPHA" symbol described by
 * the HTTP grammar:
 * 
 * - OCTET
 *     - CHAR
 *         - TOKEN
 *             - ALPHA
 *                 - LOALPHA
 * 
 * Using class heirarchies to model the grammar rules allows efficient parsing as `instanceof` 
 * checks can be used to generate the concrete syntax tree. Because `instanceof` is a language 
 * construct, parsers aren't burdened with additional userland functions to verify broad classes
 * of input tokens.
 * 
 * 4. Object symbol heirarchies also allow us to fake "multiple inheritance" through the use of
 * interfaces. For example, an `HT` (horizontal tab "\t") is a subclass of the `CTL` symbol. However,
 * by having `HT` implement the `LWS` interface we can also match `HT` tokens when an `LWS` token
 * is required using a simple `instanceof` check at the parser level.
 * 
 * 
 * ### Symbol Creation and Lookup
 * 
 * To tokenize the Start-Line and Headers we read a single character from the input stream at a
 * time. Each character is subsequently used in the construction of a symbol from HTTP's ABNF
 * grammar to be consumed by a parser. Since the same characters can and will appear often in the
 * subject input stream, each new symbol instance is cached so that future characters matching an
 * already-instantiated symbol can be referenced directly with a hash table lookup.
 * 
 * Three different options for the symbol storage pool were considered:
 * 
 * 1. An object map
 * 2. An object stack
 * 3. A native PHP array
 * 
 * Option 1 provided the desirable quality that the instantiated symbols could be easily shared 
 * across multiple tokenizer instances (by injecting the map into each tokenizer) to minimize memory 
 * usage and object instantiation costs. This benefit, however, was determined to be inconsequential
 * because of the relatively small number of symbols needed to parse average HTTP headers. A general
 * sampling of major sites like google, yahoo, microsoft, facebook, twitter and others revealed that
 * tokenizing the average response message only required approximately 70 unique symbols. As a 
 * result, symbol instantiation costs are insignificant and don't create a bottleneck.
 * 
 * Option 2 (a symbol stack) was originally considered to limit the number of symbols in-memory
 * at any given time and maintain recently used symbols for faster access. This was deemed an 
 * unnecessary optimization for the same reasons presented for Option 1.
 * 
 * Though ~70 symbols are all that's required to parse a normal set of headers, we frequently need
 * to access these symbols thousands of times during the tokenization process. Modern application
 * headers can often expand to multiple kilobytes in size. The implications here were clear: symbol
 * lookup time would be the most significant bottleneck to tokenization performance. As a result,
 * Option 3 (a native PHP array) was selected. Hash lookups on an array using `isset` are by far
 * the fastest option available because `isset` is an actual language construct. Object-based maps
 * slow the access process because they must generally call multiple userland functions before 
 * finally making an `isset` call. In the end, this distinction outweighed the benefits of using
 * a shared pool of symbols across multiple tokenizer instances.
 * 
 * 
 * ### Optimizing Entity Body Parsing
 * 
 * A parser *can* parse HTTP message entity bodies one byte at a time using single-byte tokens.
 * However, in the absence of body encodings (think gzip or deflate) this makes parsing excessively
 * slow for no reason. Many entity bodies require no parsing at all and tokenization needs only 
 * provide the raw body data.
 * 
 * For situations in which tokenized blocks of data are required, the token "granularity" may be set
 * to return a `BLOCK` token holding up to `Tokenizer::$granularity` bytes of data. The obvious
 * use-case is parsing an HTTP message that specifies a `Content-Length:` header with no special
 * transfer or content encodings. In such cases, once a parser parses the message headers it can
 * change the tokenizer's granularity to request a `BLOCK` of the input stream holding the full
 * length of the entity body. This sort of optimization is several orders of magnitude faster than
 * needlessly iterating over each character in the entity body.
 * 
 * 
 * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
 */
class Tokenizer implements Iterator {
    
    /**
     * @var resource
     */
    private $input;
    
    /**
     * @var Symbols\Symbol
     */
    private $token;
    
    /**
     * @var array
     */
    private $tokenPool = array();
    
    /**
     * @var int
     */
    private $granularity = 1;
    
    /**
     * @param mixed $input A string or stream resource containing input characters
     * @throws \Spl\TypeException On invalid input stream
     */
    public function __construct($input) {
        if (is_string($input)) {
            $this->input = fopen('data://text/plain;base64,' . base64_encode($input), 'r');
        } elseif (is_resource($input)) {
            $this->input = $input;
        } else {
            throw new TypeException(
                'Invalid input stream; ' . get_class($this) .'::__construct requires a string ' .
                'or stream resource at Argument 1'
            );
        }
    }
    
    /**
     * Retrieve the current token from the input stream
     * 
     * @return Symbols\Symbol Returns token or NULL if awaiting more data on the input stream
     */
    public function current() {
        if (!$this->token) {
            $this->token = ($this->granularity == 1) ? $this->getByte() : $this->getBlock();
        }
        
        return $this->token;
    }
    
    private function getByte() {
        $c = @fgetc($this->input);
        
        if (false === $c || $c === '') {
            return $this->buildSocketSafeEof();
        }
        
        if (isset($this->tokenPool[$c])) {
            return $this->tokenPool[$c];
        } else {
            $token = $this->buildByteToken($c);
            $this->tokenPool[$c] = $token;
            return $token;
        }
    }
    
    /**
     * Return EOF for real EOFs and NULL for streams that are still alive but waiting for more data
     * to become available (e.g. socket streams).
     */
    private function buildSocketSafeEof() {
        return (!is_resource($this->input) || feof($this->input)) ? new Symbols\EOF : null;
    }
    
    private function getBlock() {
        $block = @fread($this->input, $this->granularity);
        
        if (false === $block || $block === '') {
            return $this->buildSocketSafeEof();
        } else {
            return new Symbols\BLOCK($block);
        }
    }
    
    /**
     * Retrieve the current position of the input stream
     * 
     * @throws \RuntimeException On stream failure
     * @return int
     * 
     * @todo Determine appropriate Spl exception to throw on stream stat failure
     */
    public function key() {
        $pos = @ftell($this->input);
        
        if (false !== $pos) {
            return $pos;
        } else {
            // @codeCoverageIgnoreStart
            $err = error_get_last();
            throw new RuntimeException(
                'Input stream stat failure: ' . $err['message']
            );
            // @codeCoverageIgnoreEnd
        }
    }
    
    /**
     * Is the current input stream position valid?
     * 
     * @return bool
     */
    public function valid() {
        return !@feof($this->input);
    }
    
    /**
     * Advance to the next token in the input stream
     * 
     * @return void
     */
    public function next() {
        $this->token = null;
    }
    
    /**
     * Rewind the input stream to its start position
     * 
     * Requires that the input stream is seekable. This means that you can't perform `foreach`
     * iteration on the tokenizer for unseekable streams (e.g. socket streams). In such cases, 
     * manually increment the pointer inside a loop using `next()` and retrieve the current value
     * with `current()`.
     * 
     * @throws \RuntimeException On stream rewind failure
     * @return void
     * 
     * @todo Determine appropriate Spl exception to throw in stream rewind failure scenarios
     */
    public function rewind() {
        if (!@rewind($this->input)) {
            // @codeCoverageIgnoreStart
            $err = error_get_last();
            throw new RuntimeException(
                'Input stream rewind failure: ' . $err['message']
            );
            // @codeCoverageIgnoreEnd
        }
        
        $this->token = null;
    }
    
    /**
     * Generate a symbol token for the specified octet
     * 
     * OCTET          = <any 8-bit sequence of data>
     * CHAR           = <any US-ASCII character (octets 0 - 127)>
     * UPALPHA        = <any US-ASCII uppercase letter "A".."Z">
     * LOALPHA        = <any US-ASCII lowercase letter "a".."z">
     * ALPHA          = UPALPHA | LOALPHA
     * DIGIT          = <any US-ASCII digit "0".."9">
     * CTL            = <any US-ASCII control character (octets 0 - 31) and DEL (127)>
     * CR             = <US-ASCII CR, carriage return (13)>
     * LF             = <US-ASCII LF, linefeed (10)>
     * SP             = <US-ASCII SP, space (32)>
     * HT             = <US-ASCII HT, horizontal-tab (9)>
     * <">            = <US-ASCII double-quote mark (34)>
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
     */
    private function buildByteToken($c) {
        $code = ord($c);
        
        if ($code >= 128) {
            return new Symbols\OCTET($c);
        }
        
        switch ($code) {
            case 13: // CR ("\r")
                return new Symbols\CR;
                break;
            case 10: // LF ("\n")
                return new Symbols\LF;
                break;
            case 32: // SP (" ")
                return new Symbols\SP;
                break;
            case 34: // QUOTE (<">)
                return new Symbols\QUOTE;
                break;
            case 9:  // HT ("\t")
                return new Symbols\HT;
                break;
        }
        
        if ($code >= 65 && $code <= 70) {
            return new Symbols\UPALPHAHEX($c);
        } elseif ($code >= 71 && $code <= 90) {
            return new Symbols\UPALPHA($c);
        } elseif ($code >= 97 && $code <= 102) {
            return new Symbols\LOALPHAHEX($c);
        } elseif ($code >= 103 && $code <= 122) {
            return new Symbols\LOALPHA($c);
        } elseif ($code >= 48 && $code <= 57) {
            return new Symbols\DIGIT($c);
        } elseif ($code <= 31 || $code == 127) {
            return new Symbols\CTL($c);
        }
        
        return $this->isTokenSymbol($c) ? new Symbols\TOKEN($c) : new Symbols\CHAR($c);
    }
    
    /**
     * RFC 2616 describes a "token" as any character that isn't a CTL or separator. This 
     * nomenclature should not be confused with tokens generated by this lexer.
     * 
     * token      = 1*<any CHAR except CTLs or separators>
     * separators = "(" | ")" | "<" | ">" | "@"
     *            | "," | ";" | ":" | "\" | <">
     *            | "/" | "[" | "]" | "?" | "="
     *            | "{" | "}" | SP | HT
     * 
     * Note that we don't bother checking for CTL, SP or HT in the switch below because by the time
     * this method is invoked, those characters will have already been returned.
     * 
     * @link http://www.w3.org/Protocols/rfc2616/rfc2616-sec2.html#sec2.2
     */
    private function isTokenSymbol($c) {
        switch ($c) {
            case "(": return false;
            case ")": return false;
            case "<": return false;
            case ">": return false;
            case "@": return false;
            case ",": return false;
            case ";": return false;
            case ":": return false;
            case "\\": return false;
            case "\"": return false;
            case "/": return false;
            case "[": return false;
            case "]": return false;
            case "?": return false;
            case "=": return false;
            case "{": return false;
            case "}": return false;
            
            default: return true;
        }
    }
    
    /**
     * Assign the maximum number of bytes to buffer on input stream reads (default 1)
     * 
     * Start-lines and headers should be parsed one character at a time by necessity. However, such
     * fine granularity is unnecessary when reading the message entity body. Setting the context
     * flag allows us to return special BLOCK tokens holding large blocks of raw data subject to the
     * `Tokenizer::$granularity` constraint. Parsing large entity bodies character by
     * character is unnecessary even when a message utilizes chunked encoding. By raising the 
     * tokenizer's granularity as needed we can significantly improve the speed of entity body
     * parsing.
     * 
     * This setting should be manipulated by parsers after they determine that headers have been
     * fully received.
     * 
     * NOTE: the granularity setting acts as an upper bound on the returned BLOCK size. If fewer 
     * bytes are available for reading on the input stream, that's all that will be returned. This 
     * has particular implications for nonlocal (e.g. socket) streams in which data becomes 
     * available as network packets arrive.
     * 
     * @param int $bytes
     * @throws \Spl\DomainException On non-positive integer
     * @return void
     */
    public function setGranularity($bytes) {
        $bytes = (int) $bytes;
        if ($bytes < 1) {
            throw new DomainException(
                'Positive integer required at ' . get_class($this) . '::setGranularity Argument 1'
            );
        }
        
        $this->granularity = $bytes;
    }
}
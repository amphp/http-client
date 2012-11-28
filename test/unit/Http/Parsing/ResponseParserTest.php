<?php

use Artax\Http\Parsing\Tokenizer,
    Artax\Http\Parsing\ResponseParser,
    Artax\Http\Parsing\ParseException;

class ResponseParserTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @expectedException Spl\KeyException
     */
    public function testSetAttributeThrowsExceptionOnInvalidAttribute() {
        $tokenizer = new Tokenizer('stream of characters');
        $parser = new ResponseParser($tokenizer);
        
        $parser->setAttribute('SomeInvalidAttributeName', 42);
    }
    
    public function provideInvalidStartLines() {
        return array(
            array("xTTP/1.1 200 OK"),
            array("HxTP/1.1 200 OK"),
            array("HTxP/1.1 200 OK"),
            array("HTTx/1.1 200 OK"),
            array("HTTPx1.1 200 OK"),
            array("HTTP/x.1 200 OK"),
            array("HTTP/1x1.1 200 OK"),
            array("HTTP/1.1x1 200 OK"),
            array("HTTP/1.x 200 OK"),
            array("HTTP/1. 200 OK"),
            array("HTTP/1.1 x00 OK"),
            array("HTTP/1.1 2x0 OK"),
            array("HTTP/1.1 20x OK"),
            array("HTTP/1.1        20x        OK"),
            array("HTTP/1.1 200 OK\rX")
        );
    }
    
    /**
     * @dataProvider provideInvalidStartLines
     */
    public function testStrictInvalidStartLineResultsInParseError($message) {
        $parser = $this->getParserForRawMessage($message);
        
        try {
            $parser->parse();
            $this->fail('Expected ParseException not thrown');
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_START_LINE, $e->getCode());
        }
    }
    
    private function getParserForRawMessage($message) {
        $inputStream = fopen('php://memory', 'r+');
        fwrite($inputStream, $message);
        rewind($inputStream);
        $tokenizer = new Tokenizer($inputStream);
        return new ResponseParser($tokenizer);
    }
    
    public function testParse() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "woot!"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $response = $parser->parse();
        
        $this->assertEquals(5, $response->getCombinedHeader('Content-Length'));
        $this->assertEquals('woot!', $response->getBody());
    }
    
    public function testChunkedBodyParse() {
        $body1 = "When in the chronicle of wasted time";
        $body2 = "\r\ntest";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            dechex(strlen($body1)) . "\r\n" . $body1 . "\r\n" .
            dechex(strlen($body2)) . "\r\n" . $body2 . "\r\n" .
            dechex(0) . "\r\n\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $response = $parser->parse();
        
        $expected = $body1 . $body2;
        
        $this->assertEquals($expected, $response->getBody());
    }
    
    public function testParseWithEmptyChunkedEntityBody() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "00000\r\n\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $response = $parser->parse();
        
        $this->assertEquals('', $response->getBody());
    }
    
    public function testParseWithZeroContentLength() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 0\r\n" .
            "\r\n" .
            "This shouldn't be returned because the parser will stop reading after 0 bytes" .
            "of the entity body because of the `Content-Length: 0` header"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $response = $parser->parse();
        
        $this->assertEquals('', $response->getBody());
    }
    
    public function testParseCompletesOnEofIfNotChunkedAndNoContentLengthSpecified() {
        $body = "This is the entity body";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Favorite-Character: Tyrion Lannister\r\n" .
            "Favorite-Character: Arthur Dent\r\n" .
            "\r\n" .
            "$body"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $response = $parser->parse();
        
        $this->assertEquals($body, $response->getBody());
    }
    
    public function testParseThrowsExceptionOnUnexpectedEof() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 9999999\r\n" .
            "\r\n" .
            "This entity body is certainly not long enough to satisfy the Content-Length"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for premature EOF before content length reached'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_UNEXPECTED_EOF, $e->getCode());
        }
    }
    
    /**
     * @expectedException Artax\Http\Parsing\ParseException
     */
    public function testStrictParseThrowsExceptionOnLfStartLineEnding() {
        $message = '' .
            "HTTP/1.1 200 OK\n" .
            "Content-Length: 4\r\n" .
            "\r\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->parse();
    }
    
    /**
     * @expectedException Artax\Http\Parsing\ParseException
     */
    public function testStrictParseThrowsExceptionOnLfHeaderEnding() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 4\n" .
            "\r\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->parse();
    }
    
    /**
     * @expectedException Artax\Http\Parsing\ParseException
     */
    public function testStrictParseThrowsExceptionOnLfHeadersTermination() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 4\r\n" .
            "\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->parse();
    }
    
    public function testNonStrictParseAllowsLfLineEndings() {
        $message = '' .
            "HTTP/1.1 200 OK\n" .
            "Content-Length: 4\n" .
            "\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $parser->setAttribute(ResponseParser::ATTR_STRICT, false);
        $response = $parser->parse();
        
        $this->assertEquals(1.1, $response->getProtocol());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
        $this->assertEquals('4', $response->getCombinedHeader('Content-Length'));
        $this->assertEquals('test', $response->getBody());
    }
    
    /**
     * @expectedException Artax\Http\Parsing\ParseException
     */
    public function testStrictParseThrowsExceptionOnAdditionalWhitespaceBetweenStartLineTerms() {
        $message = '' .
            "HTTP/1.1                  200                   OK\n" .
            "Content-Length: 4\n" .
            "\r\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->parse();
    }
    
    public function testNonStrictParseAllowsAdditionalWhitespaceBetweenStartLineTerms() {
        $message = '' .
            "HTTP/1.1                  200                   OK\n" .
            "Content-Length: 4\n" .
            "\r\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_STRICT, false);
        $response = $parser->parse();
        
        $this->assertEquals(1.1, $response->getProtocol());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
    }
    
    public function testNonStrictParseAllowsLowerCaseHttpInStartLine() {
        $message = '' .
            "http/1.1 200 OK\r\n" .
            "Content-Length: 4\n\n" .
            "\r\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_STRICT, false);
        $response = $parser->parse();
        
        $this->assertEquals(1.1, $response->getProtocol());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('OK', $response->getReasonPhrase());
    }
    
    public function testParseDiscardsLeadingCrLfChars() {
        $message = '' .
            "\r\n\r\n\r\n\r\n" .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 4\r\n" .
            "\r\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        $parser->parse();
    }
    
    public function testParseAllowsUnusualButSyntacticallyCorrectVersionNumber() {
        $message = '' .
            "HTTP/125.126 200 OK\r\n" .
            "Content-Length: 4\r\n" .
            "\r\n" .
            "test"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        $response = $parser->parse();
        
        $this->assertEquals('125.126', $response->getProtocol());
    }
    
    public function testParseWithoutReasonPhrase() {
        $message = '' .
            "HTTP/1.1 200\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "woot!"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $response = $parser->parse();
        
        $this->assertEquals('', $response->getReasonPhrase());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('woot!', $response->getBody());
    }
    
    public function testNonStrictParseWithoutReasonPhraseAndLfStartLineEol() {
        $message = '' .
            "HTTP/1.1 200\n" .
            "Content-Length: 5\n" .
            "\n" .
            "woot!"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $parser->setAttribute(ResponseParser::ATTR_STRICT, false);
        $response = $parser->parse();
        
        $this->assertEquals('', $response->getReasonPhrase());
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('woot!', $response->getBody());
    }
    
    public function testParseIgnoresBodyWhenAppropriateAttributeSet() {
        $message = '' .
            "HTTP/1.1 200\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "woot!"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        $parser->setAttribute(ResponseParser::ATTR_IGNORE_BODY, true);
        $response = $parser->parse();
        
        $this->assertEquals('', $response->getBody());
    }
    
    public function testParseThrowsExceptionOnInvalidChunkSize() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            dechex(strlen($body)) . "X\r\n" . $body . "\r\n" .
            dechex(0) . "\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid chunk size'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_CHUNK_SIZE, $e->getCode());
        }
    }
    
    public function testParseThrowsExceptionOnInvalidChunkSizeTerminator() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            dechex(strlen($body)) . "\rX" . $body . "\r\n" .
            dechex(0) . "\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid chunk size'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_CHUNK_SIZE, $e->getCode());
        }
    }
    
    public function testParseThrowsExceptionOnInvalidChunkTerminator() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            dechex(strlen($body)) . "\r\n" . $body . "X\r\n" .
            dechex(0) . "\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid chunk terminator'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_CHUNK_TERMINAL, $e->getCode());
        }
    }
    
    public function testParseThrowsExceptionOnInvalidHeaderStartToken() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "\x05Some-Header: value\r\n" . 
            "Content-Length: ".strlen($body)."\r\n" .
            "\r\n" .
            $body
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid header start token'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_HEADER_TOKEN, $e->getCode());
        }
    }
    
    public function testParseThrowsExceptionOnInvalidHeaderFieldToken() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length\x05: ".strlen($body)."\r\n" .
            "\r\n" .
            $body
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid header start token'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_HEADER_TOKEN, $e->getCode());
        }
    }
    
    public function testParseHandlesEmptyHeaderValue() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Some-Header: \r\n" .
            "Content-Length: ".strlen($body)."\r\n" .
            "\r\n" .
            $body
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        $response = $parser->parse();
        
        $this->assertEquals('', $response->getCombinedHeader('Some-Header'));
    }
    
    public function testNonStrictParseHandlesEmptyHeaderValue() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\n" .
            "Some-Header: \n" .
            "Content-Length: ".strlen($body)."\n" .
            "\n" .
            $body
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_STRICT, false);
        
        $response = $parser->parse();
        
        $this->assertEquals('', $response->getCombinedHeader('Some-Header'));
    }
    
    public function testParseThrowsExceptionOnInvalidHeaderTerminatorAfterCr() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 0\r\n" .
            "Some-Header: my value\rx" . // <--- should be a LF after the CR
            "\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid header value'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_HEADER_VALUE, $e->getCode());
        }
    }
    
    public function testParseHandlesFoldedHeaderValueWithLwsOnNewline() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Some-Header: Line1\r\n" .
            "    Line2\r\n" .
            "Content-Length: 0\r\n" .
            "\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        $response = $parser->parse();
        
        $this->assertEquals('Line1 Line2', $response->getCombinedHeader('Some-Header'));
    }
    
    public function testParseThrowsExceptionOnMissingLfAtEndOfAllHeaders() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 0\r\n" .
            "\rx" // <--- should be an LF after the CR
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid header termination LF'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_HEADER_VALUE, $e->getCode());
        }
    }
    
    public function testParseThrowsExceptionOnInvalidChunkSizeStart() {
        $body = "When in the chronicle of wasted time";
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            dechex(strlen($body)) . "\r\n" . $body . "\r\n" .
            "Z" . // <--- should be a HEX character
            "\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid chunk size token'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_CHUNK_SIZE, $e->getCode());
        }
    }
    
    public function testParseThrowsExceptionOnInvalidChunkTerminatingLf() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "4\r\ntest\r" . "X" // <---- should be an LF
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        try {
            $parser->parse();
            $this->fail(
                'Exception not thrown for invalid chunk size token'
            );
        } catch (ParseException $e) {
            $this->assertEquals(ResponseParser::E_BAD_CHUNK_TERMINAL, $e->getCode());
        }
    }
    
    public function testParseReadsChunkSubjectToMaxAllowableGranularity() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Transfer-Encoding: chunked\r\n" .
            "\r\n" .
            "4\r\n" .
            "test" .
            "\r\n" .
            "0\r\n"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_BUFFER_BODY, true);
        $parser->setAttribute(ResponseParser::ATTR_MAX_GRANULARITY, 1);
        
        $response = $parser->parse();
        
        $this->assertEquals('test', $response->getBody());
    }
    
    public function testParseWithInMemoryBody() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "woot!"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        $parser->setAttribute(ResponseParser::ATTR_TEMP_BODY_MEMORY, 0);
        
        $response = $parser->parse();
        
        $this->assertEquals(5, $response->getCombinedHeader('Content-Length'));
        $this->assertTrue(is_resource($response->getBody()));
        $this->assertEquals('woot!', stream_get_contents($response->getBody()));
        $this->assertEquals(5, ftell($response->getBody()));
    }
    
    /**
     * @expectedException Artax\Http\Parsing\ParseException
     * @expectedExceptionCode 1500
     */
    public function testParseThrowsExceptionOnNonIntegerPrimaryContentLength() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: not an integer\r\n" .
            "\r\n" .
            "woot!"
        ;
        
        $tokenizer = new Tokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        $response = $parser->parse();
    }
    
    public function testParseReturnsNullOnTokenizerSocketWait() {
        $message = '' .
            "HTTP/1.1 200 OK\r\n" .
            "Content-Length: 5\r\n" .
            "\r\n" .
            "woot!"
        ;
        
        $tokenizer = new MockSocketNullReturnTokenizer($message);
        $parser = new ResponseParser($tokenizer);
        
        while (!$response = $parser->parse()) {
            continue;
        }
        
        $this->assertEquals(5, $response->getCombinedHeader('Content-Length'));
        $this->assertTrue(is_resource($response->getBody()));
        $this->assertEquals('woot!', stream_get_contents($response->getBody()));
        $this->assertEquals(5, ftell($response->getBody()));
    }
    
    public function provideRepsonsesThatDisallowBody() {
        return array(
            array("HTTP/1.1 204 OK\r\n\r\n"),
            array("HTTP/1.1 304 OK\r\n\r\n"),
            array("HTTP/1.1 101 OK\r\n\r\n")
        );
    }
    
    /**
     * @dataProvider provideRepsonsesThatDisallowBody
     */
    public function testParseCompletesOnStatusCodeDisallowingEntityBody($rawResponse) {
        $tokenizer = new MockSocketNullReturnTokenizer($rawResponse);
        $parser = new ResponseParser($tokenizer);
        
        while (!$response = $parser->parse()) {
            continue;
        }
        
        $this->assertEquals('', $response->getBody());
    }
}

class MockSocketNullReturnTokenizer extends Tokenizer {
    private $iteration = 0;
    public function current() {
        ++$this->iteration;
        
        return ($this->iteration % 2 == 0) ? null : parent::current();
    }
}

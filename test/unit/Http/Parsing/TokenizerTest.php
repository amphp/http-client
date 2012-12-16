<?php

use Artax\Http\Parsing\Tokenizer,
    Artax\Http\Parsing\Symbols\EOF;

class TokenizerTest extends PHPUnit_Framework_TestCase {
    
    public function provideInvalidStreamResources() {
        $deadStream = fopen('php://memory', 'r');
        fclose($deadStream);
        
        return array(
            array(42),
            array(null),
            array(array()),
            array(new \StdClass),
            array($deadStream)
        );
    }
    
    /**
     * @dataProvider provideInvalidStreamResources
     * @covers Artax\Http\Parsing\Tokenizer::__construct
     * @expectedException Ardent\TypeException
     */
    public function testConstructorThrowsExceptionOnInvalidInputStream($notAStreamResource) {
        $tokenizer = new Tokenizer($notAStreamResource);
    }
    
    public function testTokenIteration() {
        $input = "\r\n\"\t\x20\xfe\x7f\x17@=_~";
        $tokenizer = new Tokenizer($input);
        
        $output = '';
        while($tokenizer->valid()) {
            $output .= $tokenizer->current();
            $tokenizer->next();
        }
        
        $this->assertEquals($input, $output);
        
        $tokenizer->rewind();
        $firstToken = $tokenizer->current();
        $this->assertEquals("\r", $firstToken);
    }
    
    public function testTokenizerGeneratesEOF() {
        $input = '';
        $tokenizer = new Tokenizer($input);
        $token = $tokenizer->current();
        $this->assertInstanceOf('Artax\\Http\\Parsing\\Symbols\\EOF', $token);
    }
    
    public function testTokenizerReturnsCachedTokenIfItExists() {
        $input = 'XYX';
        
        $tokenizer = new Tokenizer($input);
        $token1 = $tokenizer->current();
        $tokenizer->next();
        $token2 = $tokenizer->current();
        $tokenizer->next();
        $token3 = $tokenizer->current();
        
        $this->assertEquals(3, $tokenizer->key());
        $this->assertSame($token1, $token3);
    }
    
    public function testTokenizerReturnsBlockTokenIfGranularityExceedsOne() {
        $input = 'XYX';
        
        $tokenizer = new Tokenizer($input);
        $tokenizer->setGranularity(3);
        
        $token = $tokenizer->current();
        $this->assertInstanceOf('Artax\\Http\\Parsing\\Symbols\\BLOCK', $token);
        $this->assertEquals(3, $token->getSize());
        
        $tokenizer->next();
        
        $token = $tokenizer->current();
        $this->assertInstanceOf('Artax\\Http\\Parsing\\Symbols\\EOF', $token);
    }
    
    public function provideUPALPHA() {
        $return = array();
        foreach (range('A', 'Z') as $c) {
            $return[] = array($c);
        }
        return $return;
    }
    
    /**
     * @dataProvider provideUPALPHA
     */
    public function testTokenizerGeneratesUPALPHA($c) {
        $tokenizer = new Tokenizer($c);
        $token = $tokenizer->current();
        $this->assertInstanceOf('Artax\\Http\\Parsing\\Symbols\\UPALPHA', $token);
    }
    
    public function provideLOALPHA() {
        $return = array();
        foreach (range('a', 'z') as $c) {
            $return[] = array($c);
        }
        return $return;
    }
    
    /**
     * @dataProvider provideLOALPHA
     */
    public function testTokenizerGeneratesLOALPHA($c) {
        $input = fopen('php://memory', 'r+');
        fwrite($input, $c);
        rewind($input);
        
        $tokenizer = new Tokenizer($input);
        $token = $tokenizer->current();
        $this->assertInstanceOf('Artax\\Http\\Parsing\\Symbols\\LOALPHA', $token);
    }
    
    public function provideDIGIT() {
        $return = array();
        foreach (range('0', '9') as $c) {
            $return[] = array($c);
        }
        return $return;
    }
    
    /**
     * @dataProvider provideDIGIT
     */
    public function testTokenizerGeneratesDIGIT($c) {
        $input = fopen('php://memory', 'r+');
        fwrite($input, $c);
        rewind($input);
        
        $tokenizer = new Tokenizer($input);
        $token = $tokenizer->current();
        $this->assertInstanceOf('Artax\\Http\\Parsing\\Symbols\\DIGIT', $token);
    }
    
    /**
     * @expectedException Ardent\DomainException
     */
    public function testSetGranularityThrowsExceptionOnNonPositiveInteger() {
        $input = fopen('php://memory', 'r');
        $tokenizer = new Tokenizer($input);
        $tokenizer->setGranularity(0);
    }
    
    public function testCurrentReturnsNullOnStreamWaitCondition() {
        stream_wrapper_register('testing', 'TestEmptyStreamReadWrapper');
        
        $input = fopen('testing://empty', 'r');
        $tokenizer = new Tokenizer($input);
        
        $this->assertNull($tokenizer->current());
        
        stream_wrapper_unregister('testing');
    }
}

class TestEmptyStreamReadWrapper {
    
    public $context;
    
    public function stream_open($path, $mode, $options, &$opened_path) {
        return true;
    }
    
    public function stream_read($bytes) {
        return '';
    }
    
    public function stream_eof() {
        return false;
    }
}
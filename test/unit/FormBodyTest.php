<?php

use Artax\FormBody;

class FormBodyTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @dataProvider provideFormEncodedExpectations
     */
    function testFormEncodedExpectations(array $fields, $expectedOutput) {
        $body = new FormBody;
        
        foreach ($fields as $fieldArr) {
            list($name, $value) = $fieldArr;
            $body->addField($name, $value);
        }
        
        $this->assertEquals($expectedOutput, $body->getBody());
    }
    
    function provideFormEncodedExpectations() {
        $return = [];
        
        // 0 -------------------------------------------------------------------------------------->
        
        $fields = [
            ['myField', 'some value']
        ];
        
        $expectedOutput = http_build_query(['myField' => 'some value']);
        
        $return[] = [$fields, $expectedOutput];
        
        // 1 -------------------------------------------------------------------------------------->
        
        $fields = [
            ['field1', 'some value'],
            ['field2', 'some value']
        ];
        
        $expectedOutput = http_build_query(['field1' => 'some value', 'field2' => 'some value']);
        
        $return[] = [$fields, $expectedOutput];
        
        // 2 -------------------------------------------------------------------------------------->
        
        $fields = [
            ['field[0]', 'val1'],
            ['field[1]', 'val2'],
        ];
        
        $expectedOutput = http_build_query(['field' => ['val1', 'val2']]);
        
        $return[] = [$fields, $expectedOutput];
        
        // x -------------------------------------------------------------------------------------->
        
        return $return;
    }
    
    /**
     * @dataProvider provideMultipartBodyExpectations
     */
    function testMultipartExpectations(array $fields, array $files, $boundary, $expectedResult) {
        $body = new FormBody($boundary);
        
        foreach ($fields as $fieldArr) {
            list($name, $value) = $fieldArr;
            $body->addField($name, $value);
        }
        
        foreach ($files as $fileArr) {
            list($name, $file) = $fileArr;
            $body->addFileField($name, $file);
        }
        
        $bodyIterator = $body->getBody();
        $this->assertInstanceOf('Artax\MultipartFormBodyIterator', $bodyIterator);
        
        $buffer = '';
        foreach ($bodyIterator as $part) {
            $buffer .= $part;
        }
        
        $this->assertEquals($expectedResult, $buffer);
        $this->assertEquals("multipart/form-data; boundary={$boundary}", $body->getContentType());
    }
    
    
    function provideMultipartBodyExpectations() {
        $return = [];
        
        // 0 -------------------------------------------------------------------------------------->
        
        $fields = [
            ['field1', 'val1'],
            ['field2', 'val2']
        ];
        
        $files = [
            ['file1', dirname(__DIR__) . '/fixture/lorem.txt'],
            ['file2', dirname(__DIR__) . '/fixture/answer.txt']
        ];
        
        $boundary = 'AaB03x';
        
        $expectedBuffer = '' .
            "--{$boundary}\r\n" .
            
            "Content-Disposition: form-data; name=\"field1\"\r\n" .
            "Content-Type: text/plain\r\n\r\n" .
            "val1\r\n" .
            "--{$boundary}\r\n" .
            
            "Content-Disposition: form-data; name=\"field2\"\r\n" .
            "Content-Type: text/plain\r\n\r\n" .
            "val2\r\n" .
            "--{$boundary}\r\n" .
            
            "Content-Disposition: form-data; name=\"file1\"; filename=\"lorem.txt\"\r\n" .
            "Content-Type: application/octet-stream\r\n" .
            "Content-Transfer-Encoding: binary\r\n\r\n" .
            file_get_contents($files[0][1]) . "\r\n" .
            "--{$boundary}\r\n" .
            
            "Content-Disposition: form-data; name=\"file2\"; filename=\"answer.txt\"\r\n" .
            "Content-Type: application/octet-stream\r\n" .
            "Content-Transfer-Encoding: binary\r\n\r\n" .
            file_get_contents($files[1][1]) . "\r\n" .
            
            "--{$boundary}--";
        
        $return[] = [$fields, $files, $boundary, $expectedBuffer];
        
        // x ---------------------------------------------------------------------------------------
        
        return $return;
    }
    
    /**
     * @dataProvider provideBadStrings
     * @expectedException InvalidArgumentException
     */
    function testSetFieldThrowsExceptionOnInvalidName($badName) {
        $body = new FormBody;
        $body->addField($badName, 'value');
    }
    
    function provideBadStrings() {
        return [
            [new StdClass],
            [[]]
        ];
    }
    
    /**
     * @dataProvider provideBadStrings
     * @expectedException InvalidArgumentException
     */
    function testSetFieldThrowsExceptionOnInvalidValue($badValue) {
        $body = new FormBody;
        $body->addField('field', $badValue);
    }
    
}


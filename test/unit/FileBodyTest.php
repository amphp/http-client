<?php

use Artax\FileBody;

class FileBodyTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @expectedException RuntimeException
     */
    function testConstructorThrowsOnFailureToReadSpecifiedFile() {
        $badFilePath = 'dksfja;jf;aslkfjlsad fjasklfjas doesnt exist';
        $test = new FileBody($badFilePath);
    }
    
}

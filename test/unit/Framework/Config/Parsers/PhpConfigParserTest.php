<?php

use Artax\Framework\Config\Parsers\PhpConfigParser,
    org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamWrapper;

org\bovigo\vfs\vfsStreamWrapper::register();

vfsStream::copyFromFileSystem(
    ARTAX_SYSTEM_DIR . '/test/fixture/vfs/config', vfsStream::setup('root')
);

class PhpConfigParserTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Config\Parsers\PhpConfigParser::parse
     */
    public function testParseReturnsValidCfgVariableFromPhpConfigFile() {
        $parser = new PhpConfigParser();
        
        $configFilepath = 'vfs://root/app-config.php';
        
        $expected = new StdClass;
        $expected->routes = array(
            '/' => 'MyApp\\Resources\\Index',
            '/widgets' => 'MyApp\\Resources\\Widgets'
        );
        
        $this->assertEquals(
            $expected,
            $parser->parse($configFilepath)
        );
    }
    
    /**
     * @covers Artax\Framework\Config\Parsers\PhpConfigParser::parse
     * @expectedException Artax\Framework\Config\ConfigException
     */
    public function testParseThrowsExceptionOnInvalidPhpConfigFile() {
        $parser = new PhpConfigParser();
        $configFilepath = 'vfs://root/invalid-config1.php';
        $parser->parse($configFilepath);
    }
    
}

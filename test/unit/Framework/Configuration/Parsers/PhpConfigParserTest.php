<?php

use Artax\Framework\Configuration\Parsers\PhpConfigParser,
    org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamWrapper;

org\bovigo\vfs\vfsStreamWrapper::register();

vfsStream::copyFromFileSystem(
    ARTAX_SYSTEM_DIR . '/test/fixture/vfs/config', vfsStream::setup('root')
);

class PhpConfigParserTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Configuration\Parsers\PhpConfigParser::parse
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
     * @covers Artax\Framework\Configuration\Parsers\PhpConfigParser::parse
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testParseThrowsExceptionOnMissingConfigFileIncludeFailure() {
        $parser = new PhpConfigParser();
        $configFilepath = 'vfs://root/config-file-that-definitely-doesnt-exist.php';
        $parser->parse($configFilepath);
    }
    
    /**
     * @covers Artax\Framework\Configuration\Parsers\PhpConfigParser::parse
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testParseThrowsExceptionOnInvalidPhpConfigFileCfgVariable() {
        $parser = new PhpConfigParser();
        $configFilepath = 'vfs://root/invalid-config1.php';
        $parser->parse($configFilepath);
    }
    
    /**
     * @covers Artax\Framework\Configuration\Parsers\PhpConfigParser::parse
     * @expectedException Artax\Framework\Configuration\ConfigException
     */
    public function testParseThrowsExceptionOnMissingCfgVariableInConfigFile() {
        $parser = new PhpConfigParser();
        $configFilepath = 'vfs://root/config-require.php';
        $parser->parse($configFilepath);
    }
    
}

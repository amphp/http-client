<?php

use Artax\Framework\Config\ConfigParserFactory,
    org\bovigo\vfs\vfsStream,
    org\bovigo\vfs\vfsStreamWrapper;

org\bovigo\vfs\vfsStreamWrapper::register();

vfsStream::copyFromFileSystem(
    ARTAX_SYSTEM_DIR . '/test/fixture/vfs/config', vfsStream::setup('root')
);
        
class ConfigParserFactoryTest extends PHPUnit_Framework_TestCase {
    
    /**
     * @covers Artax\Framework\Config\ConfigParserFactory::make
     * @expectedException DomainException
     */
    public function testMakeThrowsExceptionIfInvalidConfigFileTypeSpecified() {
        $parserFactory = new ConfigParserFactory();
        $parserFactory->make('invalid_config.txt');
    }
    
    /**
     * @covers Artax\Framework\Config\ConfigParserFactory::make
     */
    public function testMakeReturnsConfigParser() {
        $parserFactory = new ConfigParserFactory();        
        
        $configFilepath = 'vfs://root/app-config.php';
        $this->assertInstanceOf(
            'Artax\\Framework\\Config\\Parsers\\PhpConfigParser',
            $parserFactory->make($configFilepath)
        );
        
        $configFilepath = 'vfs://root/app-config.XML';
        $this->assertInstanceOf(
            'Artax\\Framework\\Config\\Parsers\\XmlConfigParser',
            $parserFactory->make($configFilepath)
        );
    }
}

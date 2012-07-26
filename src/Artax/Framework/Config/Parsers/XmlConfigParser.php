<?php
namespace Artax\Framework\Config\Parsers;

use DomDocument;

class XmlConfigParser implements ConfigParser {

    /**
     * @var DomDocument
     */
    private $dom;
    
    /**
     * @param string $configFilePath
     * @return void
     */
    public function __construct() {
        $this->dom = new DomDocument;
    }
    
    /**
     * 
     */
    public function parse($configFilepath) {
        $this->dom->load($configFilepath);
        $this->loadRoutes();
        $this->loadHandlers();
    }
    
    /**
     * 
     */
    private function loadHandlers() {
        $this->vals['handlers'] = array();
        
        $xpath = new DOMXpath($this->dom);
        
        if (!$handlers = $xpath->query("/handlers/handler")) {
            return;
        }
        
        foreach ($handlers as $h) {
            $type  = $r->getAttribute('type');
            $class = $r->getAttribute('class');
            $this->vals['handlers'][$type] = $class;
        }
    }
    
    /**
     * 
     */
    private function loadRoutes() {
        $this->vals['routes'] = array();
        
        $xpath = new DOMXpath($this->dom);
        
        if (!$routes = $xpath->query("/routes/route")) {
            return;
        }
        
        foreach ($routes as $r) {
            $uriPattern = $r->getAttribute('pattern');
            $className  = $r->getAttribute('class');
            $this->vals['routes'][$uriPattern] = $className;
        }
    }
}

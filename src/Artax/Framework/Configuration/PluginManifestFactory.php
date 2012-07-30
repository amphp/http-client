<?php

namespace Artax\Framework\Configuration;

class PluginManifestFactory {
    
    public function make($iterableManifestValues) {
        $manifest = new PluginManifest();
        $manifest->populate($iterableManifestValues);
        
        return $manifest;
    }
    
}

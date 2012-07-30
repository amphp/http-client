<?php

namespace Artax\Framework\Configuration;

use Exception,
    Artax\Framework\Configuration\Parsers\ConfigParser;

class PluginLoader {
    
    protected $configurator;
    protected $configParser;
    protected $pluginManifestFactory;
    protected $pluginDirectory;
    protected $currentSystemVersion;
    
    public function __construct(
        Configurator $configurator,
        ConfigParser $configParser,
        PluginManifestFactory $pluginManifestFactory,
        $pluginDirectory,
        $currentSystemVersion
    ) {
        $this->configurator = $configurator;
        $this->configParser = $configParser;
        $this->pluginManifestFactory = $pluginManifestFactory;
        $this->pluginDirectory = $pluginDirectory;
        $this->currentSystemVersion = $currentSystemVersion;
    }
    
    public function load(array $configDefinedPluginsArray) {
        $loadedPlugins = array();
        
        foreach ($configDefinedPluginsArray as $plugin => $enabledStatus) {
            if (!$enabledStatus) {
                continue;
            }
            try {
                $loadedPlugins[] = $this->applyPlugin($plugin);
            } catch (PluginException $e) {
                throw $e;
            } catch (Exception $e) {
                throw new PluginException("Plugin load failure: $plugin", null, $e);
            }
        }
        
        foreach ($loadedPlugins as $manifest) {
            $this->validateDependencies($manifest, $loadedPlugins);
        }
        
        return $loadedPlugins;
    }
    
    protected function applyPlugin($plugin) {
        $pluginManifestFile = $this->pluginDirectory . "/$plugin/manifest.php";
        $manifestCfgValues = $this->configParser->parse($pluginManifestFile);
        $manifest = $this->pluginManifestFactory->make($manifestCfgValues);
        
        $this->validateSystemVersion($manifest);
        
        $this->configurator->apply($manifest);
        
        return $manifest;
    }
    
    protected function validateSystemVersion(PluginManifest $manifest) {
        $minSystemVersion = $manifest->get('minSystemVersion');
        if ($minSystemVersion > $this->currentSystemVersion) {
            throw new PluginException(
                "Plugin load failure: $manifest requires Artax $minSystemVersion or higher"
            );
        }
    }
    
    protected function validateDependencies(PluginManifest $manifest, array $loadedPlugins) {
        $pluginDependencies = $manifest->get('pluginDependencies');
        
        foreach ($pluginDependencies as $dependency) {
            if (!in_array($dependency, $loadedPlugins)) {
                throw new PluginException(
                    "Plugin load failure: $dependency required to use $manifest"
                );
            }
        }
    }
}

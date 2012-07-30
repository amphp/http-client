<?php

$cfg = new StdClass;

$cfg->name = 'ArtaxPlugins/ResponseContentLength';
$cfg->description = 'Automatically adds a ContentLength header to outbound responses';
$cfg->version = 0.1;
$cfg->minSystemVersion = 0;
$cfg->pluginDependencies = array();

// -------------------------------------------------------------------------------------------------

$cfg->requiredFiles = array(
    __DIR__ . '/src/ResponseContentLength.php'
);

$cfg->eventListeners = array(
    '__sys.response.beforeSend' => 'ArtaxPlugins\\ResponseContentLength'
);

$cfg->injectionDefinitions = array();
$cfg->injectionImplementations = array();
$cfg->sharedClasses = array();

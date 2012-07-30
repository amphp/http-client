<?php

$cfg = new StdClass;

$cfg->name = 'ArtaxPlugins/ResponseDate';
$cfg->description = '';
$cfg->version = 0.1;
$cfg->minSystemVersion = 0;
$cfg->pluginDependencies = array();

// -------------------------------------------------------------------------------------------------

$cfg->eventListeners = array(
    '__sys.response.beforeSend' => 'ArtaxPlugins\\ResponseDate'
);

$cfg->requiredFiles = array(
    __DIR__ . '/src/ResponseDate.php'
);

$cfg->injectionDefinitions = array();
$cfg->injectionImplementations = array();
$cfg->sharedClasses = array();

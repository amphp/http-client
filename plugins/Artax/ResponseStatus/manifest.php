<?php

$cfg = new StdClass;

$cfg->name = 'Artax/ResponseStatus';
$cfg->description = '';
$cfg->version = 0.1;
$cfg->minSystemVersion = 0;
$cfg->pluginDependencies = array();

// -------------------------------------------------------------------------------------------------

$cfg->eventListeners = array(
    '__sys.response.beforeSend' => 'ArtaxPlugins\\ResponseStatus'
);

$cfg->requiredFiles = array(
    __DIR__ . '/src/ResponseStatus.php'
);

$cfg->injectionDefinitions = array();
$cfg->injectionImplementations = array();
$cfg->sharedClasses = array();

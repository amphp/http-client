<?php

$cfg = new StdClass;

$cfg->name = 'ArtaxPlugins/ResponseStatus';
$cfg->description = '';
$cfg->version = 0.1;
$cfg->minSystemVersion = 0;
$cfg->pluginDependencies = array();

// -------------------------------------------------------------------------------------------------

$cfg->eventListeners = array(
    '__sys.route.new' => 'ArtaxPlugins\\RouteShortcuts'
);

$cfg->sharedClasses = array(
    'ArtaxPlugins\\RouteShortcuts'
);

$cfg->requiredFiles = array(
    __DIR__ . '/src/RouteShortcuts.php'
);

$cfg->injectionDefinitions = array();
$cfg->injectionImplementations = array();

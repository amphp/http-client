<?php

$cfg = new StdClass;

$cfg->name = 'Artax/ResponseEncoder';
$cfg->description = '';
$cfg->version = 0.1;
$cfg->minSystemVersion = 0;
$cfg->pluginDependencies = array();

// -------------------------------------------------------------------------------------------------

$cfg->eventListeners = array(
    '__sys.response.beforeSend' =>'ArtaxPlugins\\ResponseEncoder'
);
$cfg->injectionDefinitions = array('ArtaxPlugins\\ResponseEncoder' => array(
    'request' => 'Artax\\Http\\StdRequest'
));

$cfg->requiredFiles = array(
    __DIR__ . '/src/ResponseEncoder.php'
);

$cfg->sharedClasses = array();
$cfg->injectionImplementations = array();

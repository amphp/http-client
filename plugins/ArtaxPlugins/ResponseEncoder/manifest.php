<?php

$cfg = new StdClass;

$cfg->name = 'ArtaxPlugins/ResponseEncoder';
$cfg->description = '';
$cfg->version = 0.1;
$cfg->minSystemVersion = 0;
$cfg->pluginDependencies = array();

// -------------------------------------------------------------------------------------------------

$encodableMediaRanges = array('text/*', 'application/json', 'application/xml');

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

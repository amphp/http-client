<?php

$cfg = new StdClass;

$encodableMediaRanges = array('text/*', 'application/json', 'application/xml');

$cfg->eventListeners = array(
    '__sys.response.beforeSend' =>'ArtaxPlugins\\ResponseEncode\\ResponseEncoder'
);
$cfg->injectionDefinitions = array('ArtaxPlugins\\ResponseEncode\\ResponseEncoder' => array(
    'request' => 'Artax\\Http\\StdRequest'
));

//$cfg->sharedClasses = array();
//$cfg->injectionImplementations = array();

$cfg->requiredFiles = array(
    __DIR__ . '/src/ResponseEncoder.php'
);

<?php

$cfg = new StdClass;

$cfg->eventListeners = array(
    '__sys.response.beforeSend' => 'ArtaxPlugins\\ResponseDate\\DateApplier'
);

//$cfg->injectionDefinitions = array();
//$cfg->injectionImplementations = array();
//$cfg->sharedClasses = array();

$cfg->requiredFiles = array(
    __DIR__ . '/src/DateApplier.php'
);

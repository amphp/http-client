<?php

$cfg = new StdClass;

$cfg->eventListeners = array(
    '__sys.route.new' => 'ArtaxPlugins\\RouteShortcuts\\ShortcutApplier'
);

//$cfg->injectionDefinitions = array();
//$cfg->injectionImplementations = array();

$cfg->sharedClasses = array(
    'ArtaxPlugins\\RouteShortcuts\\ShortcutApplier'
);

$cfg->requiredFiles = array(
    __DIR__ . '/src/ShortcutApplier.php'
);

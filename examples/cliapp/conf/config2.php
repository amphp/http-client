<?php

$cfg = [];

// application-wide debug output flag
$cfg['debug'] = FALSE;

// don't load HTTP app libs during boot
$cfg['httpBundle'] = FALSE;

// specify namespace paths for class autoloaders
$cfg['namespaces'] = [
  '' => AX_APP_PATH . '/src'
];

// specify event listeners
$cfg['listeners'] = [
  
  ['app.shutdown', function() {
    echo PHP_EOL . '... app.shutdown ...' . PHP_EOL . PHP_EOL;
  }],
  
  ['app.exception', function(\Exception $e) {
    $handler = $this->depProvider->make('controllers.ExHandler');
    $handler->setDebug($this->config['debug']);
    $handler->setException($e);
    $handler->exec()->getResponse()->output();
    return FALSE;
  }],
  
  ['app.ready', function() {
    echo PHP_EOL . '... app.ready ...' . PHP_EOL;
    throw new Exception('Something that breaks the application');
  }],
];

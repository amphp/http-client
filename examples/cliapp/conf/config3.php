<?php

$cfg = [];

$cfg['debug'] = TRUE;

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
  }],
];

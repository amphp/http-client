<?php

$cfg = [];

$cfg['debug'] = TRUE;

$cfg['listeners'] = [
  
  ['app.setUp', function() {
      (new Artax\ClassLoader(NULL, AX_APP_PATH . '/src'))->register();
  }],
  
  ['app.tearDown', function() {
      echo PHP_EOL . '... app.tearDown ...' . PHP_EOL . PHP_EOL;
  }],
  
  ['app.exception', function(\Exception $e) {
      $handler = $this->depProvider->make('Controllers.ExHandler');
      $handler->setDebug($this->config['debug']);
      $handler->setException($e);
      $handler->exec()->getResponse()->output();
      return FALSE;
  }],
  
  ['app.ready', function() {
      echo PHP_EOL . '... app.ready ...' . PHP_EOL;
  }],
];

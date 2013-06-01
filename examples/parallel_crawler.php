<?php

use Amp\ReactorFactory,
    Artax\AsyncClient;

require dirname(__DIR__) . '/autoload.php';
require __DIR__ . '/support/MyParallelCrawler.php'; // <-- check out the class to see how it works.

$reactor = (new ReactorFactory)->select();
$client = new AsyncClient($reactor);

$crawler = new MyParallelCrawler($reactor, $client);
$crawler->crawl('http://www.google.com');


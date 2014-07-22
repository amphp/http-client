<?php // 101_parallel_async_requests.php

//require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../src/bootstrap.php';

(new Alert\ReactorFactory)->select()->run(function($reactor) {
    $client = new Artax\Client($reactor);
    $reqsRemaining = 26;
    $startTime = microtime(true);

    foreach (range('a', 'z') as $alpha) {
        $uri = 'http://www.bing.com/search?q=' . $alpha;
        $future = $client->request($uri);
        $future->onResolution(function($future) use (&$reqsRemaining, $reactor, $uri) {
            if ($future->succeeded()) {
                $response = $future->getValue();
                printf(
                    "HTTP/%s %d %s | %s\n",
                    $response->getProtocol(),
                    $response->getStatus(),
                    $response->getReason(),
                    $uri
                );
            } else {
                echo $future->getError(), "\n";
            }

            if (--$reqsRemaining === 0) {
                printf("26 parallel HTTP requests completed in %s seconds\n", microtime(true) - $startTime);
                $reactor->stop();
            }
        });
    }
});

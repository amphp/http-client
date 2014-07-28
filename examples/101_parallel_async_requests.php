<?php // 101_parallel_async_requests.php

require __DIR__ . '/../vendor/autoload.php';

(new Alert\ReactorFactory)->select()->run(function($reactor) {
    $client = new Artax\Client($reactor);
    $startTime = microtime(true);
    $promises = [];

    foreach (range('a', 'z') as $alpha) {
        $uri = 'http://www.bing.com/search?q=' . $alpha;
        $promise = $client->request($uri);
        $promise->onResolve(function($error, $result) use ($reactor, $uri) {
            if ($error) {
                echo $error->getMessage(), "\n";
            } else {
                printf(
                    "HTTP/%s %d %s | %s\n",
                    $result->getProtocol(),
                    $result->getStatus(),
                    $result->getReason(),
                    $uri
                );
            }
        });
        $promises[$alpha] = $promise;
    }

    After\some($promises)->onResolve(function() use ($reactor, $startTime) {
        printf("26 parallel HTTP requests completed in %s seconds\n", microtime(true) - $startTime);
        $reactor->stop();
    });
});

<?php // 102_parallel_async_errors.php

require __DIR__ . '/../vendor/autoload.php';

/**
 * Async error handling is trivial. The Artax\Client::request() method always returns an instance
 * of After\Promise. This object is simply a placeholder for the future response (because it hasn't
 * been retrieved yet).
 *
 * The After\Promise accepts an "error-first" callback to be invoked when the response eventually
 * completes. If the request completes normally the first parameter passed to this callback is
 * NULL and the final Artax\Response instance is passed at parameter 2.
 *
 * In the event of an error the Exception responsible for the request failure is passed at argument
 * 1. That's all there is to it!
 *
 * The example below attempts to request a resource from a non-existent hostname. The transfer
 * should fail with an exception describing the DNS resolution error.
 */

(new Alert\ReactorFactory)->select()->run(function($reactor) {
    $client = new Artax\Client($reactor);
    $promise = $client->request('http://hopefully-this-totally-doesnt-exist.com');
    $promise->onResolve(function(Exception $error = null, Artax\Response $result = null) use ($reactor) {
        if ($error) {
            echo $error, "\n";
        } else {
            printf("HTTP/%s %d %s\n", $result->getProtocol(), $result->getStatus(), $result->getReason());
        }

        // Stop the event reactor because we're finished and we don't want to loop indefinitely
        $reactor->stop();
    });
});

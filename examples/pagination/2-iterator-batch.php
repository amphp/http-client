<?php declare(strict_types=1);

use Amp\Future;
use Amp\Http\Client\Example\GitHubApi;
use Amp\Http\Client\HttpClientBuilder;
use function Amp\async;
use function Amp\delay;

require __DIR__ . '/../.helper/functions.php';
require __DIR__ . '/GitHubApi.php';

$httpClient = HttpClientBuilder::buildDefault();
$github = new GitHubApi($httpClient);

$eventBatches = $github->getEvents('amphp');
foreach ($eventBatches as $events) {
    $futures = [];
    foreach ($events as $event) {
        $futures[] = async(static function () use ($event): void {
            // do something with $event, we just fake some delay here
            delay(random_int(1, 10));

            print $event->type . ': ' . $event->id . PHP_EOL;
        });
    }

    Future\await($futures);
}

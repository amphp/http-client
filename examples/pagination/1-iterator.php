<?php declare(strict_types=1);

use Amp\Http\Client\Example\GitHubApi;
use Amp\Http\Client\HttpClientBuilder;

require __DIR__ . '/../.helper/functions.php';
require __DIR__ . '/GitHubApi.php';

$httpClient = HttpClientBuilder::buildDefault();
$github = new GitHubApi($httpClient);

$eventBatches = $github->getEvents('amphp');
foreach ($eventBatches as $events) {
    foreach ($events as $event) {
        print $event->type . ': ' . $event->id . PHP_EOL;
    }
}

<?php

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Iterator;
use Amp\Loop;
use Amp\Producer;
use function Amp\call;
use function Amp\delay;
use function Kelunik\LinkHeaderRfc5988\parseLinks;

require __DIR__ . '/../.helper/functions.php';

class GitHubApi
{
    private $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getEvents(string $organization): Iterator
    {
        return new Producer(function (callable $emit) use ($organization) {
            $url = 'https://api.github.com/orgs/' . \urlencode($organization) . '/events';

            do {
                $request = new Request($url);

                /** @var Response $response */
                $response = yield $this->httpClient->request($request);
                $json = yield $response->getBody()->buffer();

                if ($response->getStatus() !== 200) {
                    throw new \Exception('Failed to get events from GitHub: ' . $json);
                }

                $events = \json_decode($json);
                yield $emit($events);

                $links = parseLinks($response->getHeader('link'));
                $next = $links->getByRel('next');

                if ($next) {
                    print 'Waiting 1000 ms before next request...' . PHP_EOL;
                    yield delay(1000);

                    $url = $next->getUri();
                }
            } while ($url);
        });
    }
}

Loop::run(static function () {
    $httpClient = HttpClientBuilder::buildDefault();
    $github = new GitHubApi($httpClient);

    $eventBatches = $github->getEvents('amphp');
    while (yield $eventBatches->advance()) {
        $events = $eventBatches->getCurrent();

        $promises = [];
        foreach ($events as $event) {
            $promises[] = call(static function () use ($event) {
                // do something with $event, we just fake some delay here
                yield delay(\random_int(1, 100));

                print $event->type . ': ' . $event->id . PHP_EOL;
            });
        }

        yield $promises;
    }
});

<?php declare(strict_types=1);

use Amp\Future;
use Amp\Http\Client\HttpClient;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\Request;
use function Amp\async;
use function Amp\delay;
use function Kelunik\LinkHeaderRfc5988\parseLinks;

require __DIR__ . '/../.helper/functions.php';

class GitHubApi
{
    private HttpClient $httpClient;

    public function __construct(HttpClient $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getEvents(string $organization): iterable
    {
        $url = 'https://api.github.com/orgs/' . urlencode($organization) . '/events';

        do {
            $request = new Request($url);

            $response = $this->httpClient->request($request);
            $json = $response->getBody()->buffer();

            if ($response->getStatus() !== 200) {
                throw new \Exception('Failed to get events from GitHub: ' . $json);
            }

            $events = json_decode($json);
            yield $events;

            $links = parseLinks($response->getHeader('link'));
            $next = $links->getByRel('next');

            if ($next) {
                print 'Waiting 1 s before next request...' . PHP_EOL;
                delay(1);

                $url = $next->getUri();
            }
        } while ($url);
    }
}

$httpClient = HttpClientBuilder::buildDefault();
$github = new GitHubApi($httpClient);

$eventBatches = $github->getEvents('amphp');
foreach ($eventBatches as $events) {
    $futures = [];
    foreach ($events as $event) {
        $futures[] = async(static function () use ($event): void {
            // do something with $event, we just fake some delay here
            delay(random_int(1, 100));

            print $event->type . ': ' . $event->id . PHP_EOL;
        });
    }

    Future\await($futures);
}

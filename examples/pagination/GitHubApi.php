<?php declare(strict_types=1);

namespace Amp\Http\Client\Example;

use Amp\Http\Client\HttpClient;
use Amp\Http\Client\Request;
use function Amp\delay;
use function Kelunik\LinkHeaderRfc5988\parseLinks;

final class GitHubApi
{
    public function __construct(private readonly HttpClient $httpClient)
    {
    }

    public function getEvents(string $organization): iterable
    {
        $url = 'https://api.github.com/orgs/' . \urlencode($organization) . '/events';

        do {
            $request = new Request($url);

            $response = $this->httpClient->request($request);
            $json = $response->getBody()->buffer();

            if ($response->getStatus() !== 200) {
                throw new \Exception('Failed to get events from GitHub: ' . $json);
            }

            $events = \json_decode($json);
            yield $events;

            $links = parseLinks($response->getHeader('link') ?? '');
            $next = $links->getByRel('next');

            if ($next) {
                print 'Waiting 1 s before next request...' . PHP_EOL;
                delay(1);

                $url = $next->getUri();
            }
        } while ($url);
    }
}

<?php declare(strict_types=1);

use Amp\Http\Client\EventListener\LogHttpArchive;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Interceptor\MatchOrigin;
use Amp\Http\Client\Interceptor\SetRequestHeader;
use Amp\Http\Client\Request;

require __DIR__ . '/../.helper/functions.php';

try {
    $client = (new HttpClientBuilder)
        ->listen(new LogHttpArchive(__DIR__ . '/log.har'))
        ->intercept(new MatchOrigin(['https://amphp.org' => new SetRequestHeader('x-amphp', 'true')]))
        ->followRedirects(0)
        ->retry(3)
        ->build();

    for ($i = 0; $i < 5; $i++) {
        $response = $client->request(new Request($argv[1] ?? 'https://httpbin.org/user-agent'));

        dumpRequestTrace($response->getRequest());
        dumpResponseTrace($response);

        dumpResponseBodyPreview($response->getBody()->buffer());
    }
} catch (HttpException $error) {
    echo $error;
}

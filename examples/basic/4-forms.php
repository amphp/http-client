<?php declare(strict_types=1);

use Amp\Http\Client\Form;
use Amp\Http\Client\HttpClientBuilder;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;

require __DIR__ . '/../.helper/functions.php';

try {
    // Instantiate the HTTP client
    $client = HttpClientBuilder::buildDefault();

    // Here we create a custom request object instead of simply passing an URL to request().
    // We set the method to POST and add a FormBody to submit a form.
    $body = new Form;
    $body->addText("search", "foobar");
    $body->addText("submit", "ok");
    $body->addLocalFile("foo", __DIR__ . "/small-file.txt");

    $request = new Request('https://httpbin.org/post', 'POST');
    $request->setBody($body);

    // Make an asynchronous HTTP request
    $response = $client->request($request);

    dumpRequestTrace($response->getRequest());
    dumpResponseTrace($response);

    dumpResponseBodyPreview($response->getBody()->buffer());
} catch (HttpException $error) {
    // If something goes wrong Amp will throw the exception where the promise was yielded.
    // The HttpClient::request() method itself will never throw directly, but returns a promise.
    echo $error;
}

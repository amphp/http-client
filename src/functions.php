<?php

namespace Amp\Http\Client;

use Amp\Http\Client\Internal\EventInvoker;

function events(): EventListener
{
    return EventInvoker::get();
}

function processRequest(Request $request, \Closure $requestHandler): Response
{
    if ($request->isStarted()) {
        return $requestHandler($request);
    }

    events()->requestStart($request);

    try {
        $response = $requestHandler($request);
    } catch (HttpException $exception) {
        events()->requestFailed($request, $exception);

        throw $exception;
    }

    $response->getTrailers()->map(fn () => events()->requestEnd($request, $response))->ignore();
    $response->getTrailers()->catch(fn () => events()->requestFailed($request, $response))->ignore();

    return $response;
}

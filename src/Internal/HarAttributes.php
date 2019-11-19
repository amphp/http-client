<?php

namespace Amp\Http\Client\Internal;

/** @internal */
final class HarAttributes
{
    use ForbidCloning;
    use ForbidSerialization;

    public const STARTED_DATE_TIME = 'amp.http.client.har.startedDateTime';
    public const SERVER_IP_ADDRESS = 'amp.http.client.har.serverIPAddress';

    public const TIME_START = 'amp.http.client.har.timings.start';
    public const TIME_SSL = 'amp.http.client.har.timings.ssl';
    public const TIME_CONNECT = 'amp.http.client.har.timings.connect';
    public const TIME_SEND = 'amp.http.client.har.timings.send';
    public const TIME_WAIT = 'amp.http.client.har.timings.wait';
    public const TIME_RECEIVE = 'amp.http.client.har.timings.receive';
    public const TIME_COMPLETE = 'amp.http.client.har.timings.complete';
}

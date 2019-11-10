<?php

namespace Amp\Http\Client;

final class HarAttributes
{
    public const STARTED_DATE_TIME = 'http-client.har.startedDateTime';
    public const SERVER_IP_ADDRESS = 'http-client.har.serverIPAddress';

    public const TIME_START = 'http-client.har.timings.start';
    public const TIME_SSL = 'http-client.har.timings.ssl';
    public const TIME_CONNECT = 'http-client.har.timings.connect';
    public const TIME_SEND = 'http-client.har.timings.send';
    public const TIME_WAIT = 'http-client.har.timings.wait';
    public const TIME_RECEIVE = 'http-client.har.timings.receive';
    public const TIME_COMPLETE = 'http-client.har.timings.complete';
}

<?php

namespace Amp\Http\Client;

enum Phase
{
    case Unprocessed;
    case Blocked;
    case Connect;
    case TlsHandshake;
    case RequestHeaders;
    case RequestBody;
    case ServerProcessing;
    case ResponseHeaders;
    case ResponseBody;
    case Complete;
    case Failed;
}

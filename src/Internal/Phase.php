<?php declare(strict_types=1);

namespace Amp\Http\Client\Internal;

/** @internal */
enum Phase
{
    case Unprocessed;
    case Blocked;
    case Connected;
    case Push;
    case RequestHeaders;
    case RequestBody;
    case ServerProcessing;
    case ResponseHeaders;
    case ResponseBody;
    case Complete;
    case Failed;
}

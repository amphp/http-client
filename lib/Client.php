<?php

namespace Amp\Artax;

use Amp\Promise;

interface Client {
    const OP_AUTO_ENCODING = 'amp.artax.client.auto-encoding';
    const OP_TRANSFER_TIMEOUT = 'amp.artax.client.transfer-timeout';
    const OP_MAX_REDIRECTS = 'amp.artax.client.max-redirects';
    const OP_AUTO_REFERER = 'amp.artax.client.auto-referer';
    const OP_DISCARD_BODY = 'amp.artax.client.discard-body';
    const OP_USER_AGENT = 'amp.artax.client.user-agent';
    const OP_MAX_HEADER_BYTES = Parser::OP_MAX_HEADER_BYTES;
    const OP_MAX_BODY_BYTES = Parser::OP_MAX_BODY_BYTES;

    public function request($uriOrRequest): Promise;
}

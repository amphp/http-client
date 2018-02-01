<?php

namespace Amp\Artax\Internal;

use Amp\CancellationToken;
use Amp\CancellationTokenSource;

/** @internal */
class CombinedCancellationToken implements CancellationToken {
    private $token;
    private $tokens = [];

    public function __construct(CancellationToken ...$tokens) {
        $tokenSource = new CancellationTokenSource;
        $this->token = $tokenSource->getToken();

        foreach ($tokens as $token) {
            $id = $token->subscribe(static function ($error) use ($tokenSource) {
                $tokenSource->cancel($error);
            });

            $this->tokens[] = [$token, $id];
        }
    }

    public function __destruct() {
        foreach ($this->tokens as list($token, $id)) {
            /** @var CancellationToken $token */
            $token->unsubscribe($id);
        }
    }

    /** @inheritdoc */
    public function subscribe(callable $callback): string {
        return $this->token->subscribe($callback);
    }

    /** @inheritdoc */
    public function unsubscribe(string $id) {
        $this->token->unsubscribe($id);
    }

    /** @inheritdoc */
    public function isRequested(): bool {
        return $this->token->isRequested();
    }

    /** @inheritdoc */
    public function throwIfRequested() {
        $this->token->throwIfRequested();
    }
}

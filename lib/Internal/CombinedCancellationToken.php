<?php

namespace Amp\Http\Client\Internal;

use Amp\CancellationToken;
use Amp\Coroutine;
use Amp\Loop;
use Amp\Promise;
use React\Promise\PromiseInterface as ReactPromise;
use function Amp\Promise\rethrow;

/** @internal */
class CombinedCancellationToken implements CancellationToken
{
    private $tokens = [];

    private $nextId = "a";
    private $callbacks = [];
    private $exception;

    public function __construct(CancellationToken ...$tokens)
    {
        foreach ($tokens as $token) {
            $id = $token->subscribe(static function ($exception) {
                $this->exception = $exception;

                $callbacks = $this->callbacks;
                $this->callbacks = [];

                foreach ($callbacks as $callback) {
                    $this->invokeCallback($callback);
                }
            });

            $this->tokens[] = [$token, $id];
        }
    }

    public function __destruct()
    {
        foreach ($this->tokens as [$token, $id]) {
            /** @var CancellationToken $token */
            $token->unsubscribe($id);
        }
    }

    /** @inheritdoc */
    public function subscribe(callable $callback): string
    {
        $id = $this->nextId++;

        if ($this->exception) {
            $this->invokeCallback($callback);
        } else {
            $this->callbacks[$id] = $callback;
        }

        return $id;
    }

    /** @inheritdoc */
    public function unsubscribe(string $id): void
    {
        unset($this->callbacks[$id]);
    }

    /** @inheritdoc */
    public function isRequested(): bool
    {
        foreach ($this->tokens as [$token]) {
            if ($token->isRequested()) {
                return true;
            }
        }

        return false;
    }

    /** @inheritdoc */
    public function throwIfRequested(): void
    {
        foreach ($this->tokens as [$token]) {
            $token->throwIfRequested();
        }
    }

    private function invokeCallback($callback): void
    {
        // No type declaration to prevent exception outside the try!
        try {
            $result = $callback($this->exception);

            if ($result instanceof \Generator) {
                $result = new Coroutine($result);
            }

            if ($result instanceof Promise || $result instanceof ReactPromise) {
                rethrow($result);
            }
        } catch (\Throwable $exception) {
            Loop::defer(static function () use ($exception) {
                throw $exception;
            });
        }
    }
}

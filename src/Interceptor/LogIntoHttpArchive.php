<?php

namespace Amp\Http\Client\Interceptor;

use Amp\CancellationToken;
use Amp\File;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\DelegateHttpClient;
use Amp\Http\Client\HarAttributes;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\Message;
use Amp\Promise;
use Amp\Sync\LocalMutex;
use Amp\Sync\Lock;
use function Amp\call;
use function Amp\Promise\rethrow;

final class LogIntoHttpArchive implements ApplicationInterceptor
{
    private static function getTime(Request $request, string $start, string $end): int
    {
        if (!$request->hasAttribute($start) || !$request->hasAttribute($end)) {
            return -1;
        }

        return $request->getAttribute($end) - $request->getAttribute($start);
    }

    private static function formatHeaders(Message $message): array
    {
        $headers = [];

        foreach ($message->getHeaders() as $field => $values) {
            foreach ($values as $value) {
                $headers[] = [
                    'name' => $field,
                    'value' => $value,
                ];
            }
        }

        return $headers;
    }

    private static function formatEntry(Response $response): array
    {
        $request = $response->getRequest();

        $data = [
            'startedDateTime' => $request->getAttribute(HarAttributes::STARTED_DATE_TIME)->format(\DateTimeInterface::RFC3339_EXTENDED),
            'time' => self::getTime($request, HarAttributes::TIME_START, HarAttributes::TIME_COMPLETE),
            'request' => [
                'method' => $request->getMethod(),
                'url' => (string) $request->getUri()->withUserInfo(''),
                'httpVersion' => 'http/' . $request->getProtocolVersions()[0],
                'headers' => self::formatHeaders($request),
                'queryString' => [],
                'cookies' => [],
                'headersSize' => -1,
                'bodySize' => -1,
            ],
            'response' => [
                'status' => $response->getStatus(),
                'statusText' => $response->getReason(),
                'httpVersion' => 'http/' . $response->getProtocolVersion(),
                'headers' => self::formatHeaders($response),
                'cookies' => [],
                'redirectURL' => $response->getHeader('location') ?? '',
                'headersSize' => -1,
                'bodySize' => -1,
                'content' => [
                    'size' => (int) ($response->getHeader('content-length') ?? '-1'),
                    'mimeType' => $response->getHeader('content-type') ?? '',
                ],
            ],
            'cache' => [],
            'timings' => [
                'blocked' => self::getTime(
                    $request,
                    HarAttributes::TIME_START,
                    HarAttributes::TIME_CONNECT
                ),
                'dns' => -1,
                'connect' => self::getTime(
                    $request,
                    HarAttributes::TIME_CONNECT,
                    HarAttributes::TIME_SEND
                ),
                'ssl' => self::getTime(
                    $request,
                    HarAttributes::TIME_SSL,
                    HarAttributes::TIME_SEND
                ),
                'send' => self::getTime(
                    $request,
                    HarAttributes::TIME_SEND,
                    HarAttributes::TIME_WAIT
                ),
                'wait' => self::getTime(
                    $request,
                    HarAttributes::TIME_WAIT,
                    HarAttributes::TIME_RECEIVE
                ),
                'receive' => self::getTime(
                    $request,
                    HarAttributes::TIME_RECEIVE,
                    HarAttributes::TIME_COMPLETE
                ),
            ],
        ];

        if ($request->hasAttribute(HarAttributes::SERVER_IP_ADDRESS)) {
            $data['serverIPAddress'] = $request->getAttribute(HarAttributes::SERVER_IP_ADDRESS);
        }

        return $data;
    }

    /** @var LocalMutex */
    private $fileMutex;
    /** @var File\File|null */
    private $fileHandle;
    /** @var string */
    private $filePath;
    /** @var \Throwable|null */
    private $error;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
        $this->fileMutex = new LocalMutex;
    }

    public function request(Request $request, CancellationToken $cancellation, DelegateHttpClient $next): Promise
    {
        return call(function () use ($request, $cancellation, $next) {
            if ($this->error) {
                throw $this->error;
            }

            /** @var Response $response */
            $response = yield $next->request($request, $cancellation);

            rethrow($this->writeLog($response));

            return $response;
        });
    }

    public function reset(): Promise
    {
        return call(function () {
            /** @var Lock $lock */
            $lock = yield $this->fileMutex->acquire();

            // Will automatically reopen and reset the file
            $this->fileHandle = null;
            $this->error = null;

            $lock->release();
        });
    }

    private function writeLog(Response $response): Promise
    {
        return call(function () use ($response) {
            try {
                yield $response->getTrailers();
            } catch (\Throwable $e) {
                // ignore, still log the remaining response times
            }

            try {
                /** @var Lock $lock */
                $lock = yield $this->fileMutex->acquire();

                $firstEntry = $this->fileHandle === null;

                if ($firstEntry) {
                    $this->fileHandle = yield File\open($this->filePath, 'w');

                    $header = '{"log":{"version":"1.2","creator":{"name":"amphp/http-client","version":"4.x"},"pages":[],"entries":[';

                    yield $this->fileHandle->write($header);
                } else {
                    yield $this->fileHandle->seek(-3, \SEEK_CUR);
                }

                /** @noinspection PhpComposerExtensionStubsInspection */
                $json = \json_encode(self::formatEntry($response));

                yield $this->fileHandle->write(($firstEntry ? '' : ',') . $json . ']}}');

                $lock->release();
            } catch (HttpException $e) {
                $this->error = $e;
            } catch (\Throwable $e) {
                $this->error = new HttpException('Writing HTTP archive log failed', 0, $e);
            }
        });
    }
}

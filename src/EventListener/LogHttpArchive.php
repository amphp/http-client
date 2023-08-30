<?php declare(strict_types=1);

namespace Amp\Http\Client\EventListener;

use Amp\File\File;
use Amp\File\Filesystem;
use Amp\File\Whence;
use Amp\ForbidCloning;
use Amp\ForbidSerialization;
use Amp\Http\Client\ApplicationInterceptor;
use Amp\Http\Client\Connection\Connection;
use Amp\Http\Client\Connection\Stream;
use Amp\Http\Client\EventListener;
use Amp\Http\Client\HttpException;
use Amp\Http\Client\Internal\HarAttributes;
use Amp\Http\Client\NetworkInterceptor;
use Amp\Http\Client\Request;
use Amp\Http\Client\Response;
use Amp\Http\HttpMessage;
use Amp\Socket\InternetAddress;
use Amp\Sync\LocalMutex;
use Revolt\EventLoop;
use function Amp\File\filesystem;
use function Amp\File\openFile;
use function Amp\now;

final class LogHttpArchive implements EventListener
{
    use ForbidCloning;
    use ForbidSerialization;

    /**
     * @param non-empty-string $attributeName
     */
    private static function getDuration(Request $request, string $attributeName): float
    {
        if (!$request->hasAttribute($attributeName)) {
            return -1;
        }

        return $request->getAttribute($attributeName) ?? -1;
    }

    /**
     * @param non-empty-string $start
     * @param non-empty-string ...$ends
     */
    private static function getTime(Request $request, string $start, string ...$ends): float
    {
        if (!$request->hasAttribute($start)) {
            return -1;
        }

        foreach ($ends as $end) {
            if ($request->hasAttribute($end)) {
                return $request->getAttribute($end) - $request->getAttribute($start);
            }
        }

        return -1;
    }

    private static function formatHeaders(HttpMessage $message): array
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

        $includeConnectTime = $request->hasAttribute(HarAttributes::INCLUDE_CONNECT_TIME)
            ? $request->getAttribute(HarAttributes::INCLUDE_CONNECT_TIME)
            : false;

        $connectDuration = $includeConnectTime ? self::getDuration(
            $request,
            HarAttributes::TIME_CONNECT
        ) : -1;

        $tlsHandshakeDuration = $includeConnectTime ? self::getDuration(
            $request,
            HarAttributes::TIME_SSL
        ) : -1;

        $sendDuration = self::getTime(
            $request,
            HarAttributes::TIME_SEND,
            HarAttributes::TIME_WAIT
        );

        $waitDuration = self::getTime(
            $request,
            HarAttributes::TIME_WAIT,
            HarAttributes::TIME_RECEIVE
        );

        $receiveDuration = self::getTime(
            $request,
            HarAttributes::TIME_RECEIVE,
            HarAttributes::TIME_COMPLETE
        );

        $blockedDuration = self::getTime(
            $request,
            HarAttributes::TIME_START,
            HarAttributes::TIME_COMPLETE
        ) - ($includeConnectTime ? $connectDuration : 0) - $sendDuration - $receiveDuration;

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
                'blocked' => $blockedDuration,
                'dns' => -1,
                'connect' => $connectDuration,
                'ssl' => $tlsHandshakeDuration,
                'send' => $sendDuration,
                'wait' => $waitDuration,
                'receive' => $receiveDuration,
            ],
        ];

        if ($request->hasAttribute(HarAttributes::SERVER_IP_ADDRESS)) {
            $data['serverIPAddress'] = $request->getAttribute(HarAttributes::SERVER_IP_ADDRESS);
        }

        return $data;
    }

    private LocalMutex $fileMutex;

    private Filesystem $filesystem;

    private ?File $fileHandle = null;

    private string $filePath;

    private ?\Throwable $error = null;

    public function __construct(string $filePath, ?Filesystem $filesystem = null)
    {
        if (!\class_exists(Filesystem::class)) {
            throw new \Error("File request bodies require amphp/file to be installed");
        }

        $this->filePath = $filePath;
        $this->fileMutex = new LocalMutex;
        $this->filesystem = $filesystem ?? filesystem();
    }

    public function reset(): void
    {
        $this->rotate($this->filePath);
    }

    public function rotate(string $filePath): void
    {
        $lock = $this->fileMutex->acquire();

        // Will automatically reopen and reset the file
        $this->fileHandle = null;
        $this->filePath = $filePath;
        $this->error = null;

        $lock->release();
    }

    private function writeLog(Response $response): void
    {
        try {
            $response->getTrailers()->await();
        } catch (\Throwable) {
            // ignore, still log the remaining response times
        }

        try {
            $lock = $this->fileMutex->acquire();

            $firstEntry = $this->fileHandle === null;

            if ($firstEntry) {
                $this->fileHandle = $fileHandle = openFile($this->filePath, 'w');

                $header = '{"log":{"version":"1.2","creator":{"name":"amphp/http-client","version":"4.x"},"pages":[],"entries":[';

                $fileHandle->write($header);
            } else {
                $fileHandle = $this->fileHandle;

                \assert($fileHandle !== null);

                $fileHandle->seek(-3, Whence::Current);
            }

            $json = \json_encode(self::formatEntry($response));

            $fileHandle->write(($firstEntry ? '' : ',') . $json . ']}}');

            $lock->release();
        } catch (HttpException $e) {
            $this->error = $e;
        } catch (\Throwable $e) {
            $this->error = new HttpException('Writing HTTP archive log failed', 0, $e);
        }
    }

    public function requestStart(Request $request): void
    {
        if (!$request->hasAttribute(HarAttributes::STARTED_DATE_TIME)) {
            $request->setAttribute(HarAttributes::STARTED_DATE_TIME, new \DateTimeImmutable);
        }

        $this->addTiming(HarAttributes::TIME_START, $request);
    }

    public function connectionAcquired(Request $request, Connection $connection, int $streamCount): void
    {
        $request->setAttribute(HarAttributes::INCLUDE_CONNECT_TIME, $streamCount === 1);
        $request->setAttribute(HarAttributes::TIME_CONNECT, (int) ($connection->getConnectDuration() * 1000));

        $tlsHandshakeDuration = $connection->getTlsHandshakeDuration();
        if ($tlsHandshakeDuration !== null) {
            $request->setAttribute(HarAttributes::TIME_SSL, (int) ($tlsHandshakeDuration * 1000));
        } else {
            $request->setAttribute(HarAttributes::TIME_SSL, -1);
        }
    }

    public function requestHeaderStart(Request $request, Stream $stream): void
    {
        $address = $stream->getRemoteAddress();
        $host = match (true) {
            $address instanceof InternetAddress => $address->getAddress(),
            default => $address->toString(),
        };
        if (\strrpos($host, ':')) {
            $host = '[' . $host . ']';
        }

        $request->setAttribute(HarAttributes::SERVER_IP_ADDRESS, $host);
        $this->addTiming(HarAttributes::TIME_SEND, $request);
    }

    public function requestBodyEnd(Request $request, Stream $stream): void
    {
        $this->addTiming(HarAttributes::TIME_WAIT, $request);
    }

    public function responseHeaderStart(Request $request, Stream $stream): void
    {
        $this->addTiming(HarAttributes::TIME_RECEIVE, $request);
    }

    public function requestEnd(Request $request, Response $response): void
    {
        $this->addTiming(HarAttributes::TIME_COMPLETE, $request);

        EventLoop::queue(fn () => $this->writeLog($response));
    }

    /**
     * @param non-empty-string $key
     */
    private function addTiming(string $key, Request $request): void
    {
        if (!$request->hasAttribute($key)) {
            $request->setAttribute($key, now());
        }
    }

    public function requestFailed(Request $request, \Throwable $exception): void
    {
        // TODO: Log error to archive
    }

    public function requestHeaderEnd(Request $request, Stream $stream): void
    {
        // nothing to do
    }

    public function requestBodyStart(Request $request, Stream $stream): void
    {
        // nothing to do
    }

    public function requestBodyProgress(Request $request, Stream $stream): void
    {
        // nothing to do
    }

    public function responseHeaderEnd(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }

    public function responseBodyStart(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }

    public function responseBodyProgress(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }

    public function responseBodyEnd(Request $request, Stream $stream, Response $response): void
    {
        // nothing to do
    }

    public function applicationInterceptorStart(Request $request, ApplicationInterceptor $interceptor): void
    {
        // nothing to do
    }

    public function applicationInterceptorEnd(Request $request, ApplicationInterceptor $interceptor, Response $response): void
    {
        // nothing to do
    }

    public function networkInterceptorStart(Request $request, NetworkInterceptor $interceptor): void
    {
        // nothing to do
    }

    public function networkInterceptorEnd(Request $request, NetworkInterceptor $interceptor, Response $response): void
    {
        // nothing to do
    }

    public function push(Request $request): void
    {
        // nothing to do
    }

    public function requestRejected(Request $request): void
    {
        // nothing to do
    }
}

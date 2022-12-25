<?php declare(strict_types=1);

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

class LogIntoHttpArchiveTest extends InterceptorTest
{
    public function testProducesValidJson(): void
    {
        $filePath = \tempnam(\sys_get_temp_dir(), 'amphp-http-client-test-');
        $logger = new LogHttpArchive($filePath);

        $this->givenApplicationInterceptor($logger);

        $this->whenRequestIsExecuted(new Request('http://example.com/foo/bar?test=1'));
        $logger->reset(); // awaits write because of the mutex

        $jsonLog = \file_get_contents($filePath);

        $this->assertJson($jsonLog);
    }
}

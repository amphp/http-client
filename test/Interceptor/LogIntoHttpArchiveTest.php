<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

class LogIntoHttpArchiveTest extends InterceptorTest
{
    public function testProducesValidJson(): \Generator
    {
        $filePath = \tempnam(\sys_get_temp_dir(), 'amphp-http-client-test-');
        $this->givenApplicationInterceptor(new LogIntoHttpArchive($filePath));

        yield $this->whenRequestIsExecuted(new Request('http://example.com/foo/bar?test=1'));

        $jsonLog = \file_get_contents($filePath);

        $this->assertJson($jsonLog);
    }
}

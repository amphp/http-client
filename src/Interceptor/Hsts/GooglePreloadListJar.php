<?php

namespace Amp\Http\Client\Interceptor\Hsts;

final class GooglePreloadListJar extends ReadOnlyHstsJar
{
    public function __construct()
    {
        $jar = new InMemoryHstsJar();
        $entries = \json_decode(\file_get_contents(__DIR__ . "/transport_security_state_static.json"), associative: true)["entries"];
        foreach ($entries as $entry) {
            if (($entry["mode"] ?? null) === "force-https") {
                $jar->register($entry["name"], $entry["include_subdomains"] ?? false);
            }
        }
        parent::__construct($jar);
    }
}

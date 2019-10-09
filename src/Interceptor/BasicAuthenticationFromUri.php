<?php

namespace Amp\Http\Client\Interceptor;

use Amp\Http\Client\Request;

final class BasicAuthenticationFromUri extends ModifyRequest
{
    public function __construct()
    {
        parent::__construct(static function (Request $request): Request {
            $userInfo = $request->getUri()->getUserInfo();
            if (!$request->hasHeader('authorization') && $userInfo !== '') {
                $request->setHeader('authorization', 'Basic ' . \base64_encode($userInfo));
            }

            return $request;
        });
    }
}

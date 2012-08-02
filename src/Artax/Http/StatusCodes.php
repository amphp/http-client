<?php

namespace Artax\Http;

class StatusCodes {
    
    const HTTP_ACCEPTED = 202;
    const HTTP_BAD_GATEWAY = 502;
    const HTTP_BAD_REQUEST = 400;
    const HTTP_CONFLICT = 409;
    const HTTP_CONTINUE = 100;
    const HTTP_CREATED = 201;
    const HTTP_EXPECTATION_FAILED = 417;
    const HTTP_FORBIDDEN = 403;
    const HTTP_FOUND = 302;
    const HTTP_GATEWAY_TIMEOUT = 504;
    const HTTP_GONE = 410;
    const HTTP_HTTP_VERSION_NOT_SUPPORTED = 505;
    const HTTP_INTERNAL_SERVER_ERROR = 500;
    const HTTP_LENGTH_REQUIRED = 411;
    const HTTP_METHOD_NOT_ALLOWED = 405;
    const HTTP_MOVED_PERMANENTLY = 301;
    const HTTP_MULTIPLE_CHOICES = 300;
    const HTTP_NON_AUTHORITATIVE_INFORMATION = 203;
    const HTTP_NOT_ACCEPTABLE = 406;
    const HTTP_NOT_FOUND = 404;
    const HTTP_NOT_IMPLEMENTED = 501;
    const HTTP_NOT_MODIFIED = 304;
    const HTTP_NO_CONTENT = 204;
    const HTTP_OK = 200;
    const HTTP_PARTIAL_CONTENT = 206;
    const HTTP_PAYMENT_REQUIRED = 402;
    const HTTP_PRECONDITION_FAILED = 412;
    const HTTP_PROXY_AUTHENTICATION_REQUIRED = 407;
    const HTTP_REQUESTED_RANGE_NOT_SATISFIABLE = 416;
    const HTTP_REQUEST_ENTITY_TOO_LARGE = 413;
    const HTTP_REQUEST_TIMEOUT = 408;
    const HTTP_REQUEST_URI_TOO_LONG = 414;
    const HTTP_RESET_CONTENT = 205;
    const HTTP_SEE_OTHER = 303;
    const HTTP_SERVICE_UNAVAILABLE = 503;
    const HTTP_SWITCHING_PROTOCOLS = 101;
    const HTTP_TEMPORARY_REDIRECT = 307;
    const HTTP_UNAUTHORIZED = 401;
    const HTTP_UNSUPPORTED_MEDIA_TYPE = 415;
    const HTTP_USE_PROXY = 305;
    
    const HTTP_100 = 'Continue';
    const HTTP_101 = 'Switching Protocols';
    const HTTP_200 = 'OK';
    const HTTP_201 = 'Created';
    const HTTP_202 = 'Accepted';
    const HTTP_203 = 'Non-Authoritative Information';
    const HTTP_204 = 'No Content';
    const HTTP_205 = 'Reset Content';
    const HTTP_206 = 'Partial Content';
    const HTTP_300 = 'Multiple Choices';
    const HTTP_301 = 'Moved Permanently';
    const HTTP_302 = 'Found';
    const HTTP_303 = 'See Other';
    const HTTP_304 = 'Not Modified';
    const HTTP_305 = 'Use Proxy';
    const HTTP_307 = 'Temporary Redirect';
    const HTTP_400 = 'Bad Request';
    const HTTP_401 = 'Unauthorized';
    const HTTP_402 = 'Payment Required';
    const HTTP_403 = 'Forbidden';
    const HTTP_404 = 'Not Found';
    const HTTP_405 = 'Method Not Allowed';
    const HTTP_406 = 'Not Acceptable';
    const HTTP_407 = 'Proxy Authentication Required';
    const HTTP_408 = 'Request Timeout';
    const HTTP_409 = 'Conflict';
    const HTTP_410 = 'Gone';
    const HTTP_411 = 'Length Required';
    const HTTP_412 = 'Precondition Failed';
    const HTTP_413 = 'Request Entity Too Large';
    const HTTP_414 = 'Request URI Too Long';
    const HTTP_415 = 'Unsupported Media Type';
    const HTTP_416 = 'Requested Range Not Satisfiable';
    const HTTP_417 = 'Expectation Failed';
    const HTTP_500 = 'Internal Server Error';
    const HTTP_501 = 'Not Implemented';
    const HTTP_502 = 'Bad Gateway';
    const HTTP_503 = 'Service Unavailable';
    const HTTP_504 = 'Gateway Timeout';
    const HTTP_505 = 'HTTP Version Not Supported';
    
}

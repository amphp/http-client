<?php

namespace Artax\Http;

use Spl\DomainException;

class FormEncodedBody {

    /**
     * @var array
     */
    protected $bodyParameters = array();

    /**
     * @param Request $request
     * @return void
     */
    public function __construct(Request $request) {
        $this->assignValuesFromEntityBody($request);
    }

    /**
     * @param Request $request
     * @return void
     */
    protected function assignValuesFromEntityBody(Request $request) {
        $body = $request->getBody();
        if (!empty($body) && $this->hasFormEncodedBody($request)) {
            parse_str($body, $parameters);
            $this->bodyParameters = array_map('urldecode', $parameters);
        }
    }

    /**
     * @param Request $request
     * @return bool
     */
    protected function hasFormEncodedBody(Request $request) {
        if (!$request->hasHeader('Content-Type')) {
            return false;
        }

        return !strcmp($request->getHeader('Content-Type'), 'application/x-www-form-urlencoded');
    }

    /**
     * Does the specified body parameter exist?
     *
     * @param string $parameter
     * @return bool
     */
    public function hasBodyParameter($parameterName) {
        return isset($this->bodyParameters[$parameterName]);
    }

    /**
     * Access the specified body parameter value
     *
     * @param string $parameterName
     * @throws \Spl\DomainException
     * @return string
     */
    public function getBodyParameter($parameterName) {
        if (!$this->hasBodyParameter($parameterName)) {
            throw new DomainException(
                "The specified body parameter does not exist: $parameterName"
            );
        }
        return $this->bodyParameters[$parameterName];
    }

    /**
     * Access a name-value array of all form-encoded body parameters
     *
     * @return array
     */
    public function getAllBodyParameters() {
        return $this->bodyParameters;
    }
}

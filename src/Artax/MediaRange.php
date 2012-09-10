<?php

namespace Artax;

use Spl\ValueException;

class MediaRange extends MimeType {

    /**
     * @param string $mediaRangeStr
     * @return void
     * @throws Spl\ValueException
     */
    public function __construct($mediaRangeStr) {
        // rfc3023-sec7: No */*+suffix ranges:
        // Section 14.1 of HTTP[RFC2616] does not support Accept headers of the form
        // "Accept: */*+xml" and so this header MUST NOT be used in this way.
        $this->matchPattern = (
            '{^'.
            '(' . implode('|', $this->validTopLevelTypes) . '|(?:x-[a-z0-9_.-]+)|\*)' .
            '/' .
            '((?:[a-z0-9_.-]+(?:\+([a-z0-9_.-]+))?)|\*)' .
            '$}'
        );
        $this->parse($mediaRangeStr);

        if ('*' == $this->getTopLevelType() && '*' !== $this->getSubType()) {
            throw new ValueException(
                "Invalid MIME type specified: $mediaRangeStr"
            );
        }
    }

    /**
     * @param MimeType $mimeType
     * @return bool
     */
    public function matches(MimeType $mimeType) {
        if ($this->__toString() == '*/*'
            || $this->__toString() == $mimeType->__toString()
            || ('*' == $this->getSubType()
                && $mimeType->getTopLevelType() == $this->getTopLevelType()
        )) {
            return true;
        }

        return false;
    }
}
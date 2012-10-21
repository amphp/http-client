<?php

namespace Artax\Negotiation;

use Spl\ValueException;

class MimeType {
    
    const DELIMITER = '/';
    const SUFFIX_DELIMITER = '+';
    
    /**
     * @var string
     */
    protected $topLevelType;

    /**
     * @var string
     */
    protected $subType;

    /**
     * @var string
     */
    protected $suffix;

    /**
     * @var array
     */
    protected $validTopLevelTypes = array(
        'application',
        'audio',
        'example',
        'image',
        'message',
        'model',
        'multipart',
        'text',
        'video'
    );

    /**
     * @var string
     */
    protected $matchPattern;

    /**
     * @param string $mimeType
     * @throws \Spl\ValueException
     * @return void
     */
    public function __construct($mimeType) {
        $this->matchPattern = (
            '{^'.
            '(' . implode('|', $this->validTopLevelTypes) . '|(?:x-[a-z0-9_.-]+))' .
            self::DELIMITER .
            '([a-z0-9_.-]+(?:\\'.self::SUFFIX_DELIMITER.'([a-z0-9_.-]+))?)' .
            '$}'
        );
        $this->parse($mimeType);
    }

    /**
     * @return string
     */
    public function __toString() {
        return $this->topLevelType . self::DELIMITER . $this->subType;
    }

    /**
     * @param string $mimeType
     * @throws \Spl\ValueException
     * @return void
     */
    protected function parse($mimeType) {
        $conformedMimeType = strtolower($mimeType);

        if (!preg_match($this->matchPattern, $conformedMimeType, $match)) {
            throw new ValueException(
                'Invalid MIME type specified: ' . $mimeType
            );
        }
        $this->topLevelType = $match[1];
        $this->subType = $match[2];
        $this->suffix = isset($match[3]) ? $match[3] : null;
    }

    /**
     * @return string
     */
    public function getTopLevelType() {
        return $this->topLevelType;
    }

    /**
     * @return string
     */
    public function getSubType() {
        return $this->subType;
    }

    /**
     * @return string
     */
    public function getSuffix() {
        return $this->suffix;
    }

    /**
     * http://tools.ietf.org/html/rfc2046#section-6
     *
     * @return bool
     */
    public function isExperimental() {
        return 'x-' == substr($this->topLevelType, 0, 2)
            || 'x-' == substr($this->subType, 0, 2);
    }
}
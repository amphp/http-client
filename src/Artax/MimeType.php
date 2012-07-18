<?php
/**
 * MIME Type MediaRange Class File
 * 
 * PHP 5.3+
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 * @license      All code subject to the terms of the LICENSE file in the base package directory
 * @version      ${project.version}
 */
namespace Artax;

use InvalidArgumentException;

/**
 * 
 * 
 * @category     Artax
 * @author       Daniel Lowrey <rdlowrey@gmail.com>
 */
class MimeType {
    
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
     * @return void
     * @throws InvalidArgumentException
     */
    public function __construct($mimeType) {
        $this->matchPattern = (
            '{^'.
            '(' . implode('|', $this->validTopLevelTypes) . '|(?:x-[a-z0-9_.-]+))' .
            '/' .
            '([a-z0-9_.-]+(?:\+([a-z0-9_.-]+))?)' .
            '$}'
        );
        $this->parse($mimeType);
    }
    
    /**
     * @return string
     */
    public function __toString() {
        return $this->topLevelType . '/' . $this->subType;
    }
    
    /**
     * @param string $mimeType
     * @return void
     * @throws InvalidArgumentException
     */
    protected function parse($mimeType) {
        $conformedMimeType = strtolower($mimeType);
        
        if (!preg_match($this->matchPattern, $conformedMimeType, $match)) {
            throw new InvalidArgumentException("Invalid MIME type specified: $mimeType");
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
     * @return bool
     */
    public function isExperimental() {
        // http://tools.ietf.org/html/rfc2046#section-6
        return 'x-' == substr($this->topLevelType, 0, 2)
            || 'x-' == substr($this->subType, 0, 2);
    }
}

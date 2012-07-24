<?php
/**
 * HTTP ParameterizedRequest Interface File
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 * @license     All code subject to the terms of the LICENSE file in the project root
 * @version     ${project.version}
 */
namespace Artax\Http;

/**
 * Adds parameterized request body accessor methods to the Request interface
 * 
 * @category    Artax
 * @package     Http
 * @author      Daniel Lowrey <rdlowrey@gmail.com>
 */
interface FormEncodableRequest extends Request {
    
    /**
     * @param string $parameter
     * @return bool
     */
    public function hasBodyParameter($parameter);
    
    /**
     * @param string $parameter
     */
    public function getBodyParameter($parameter);
    
    /**
     * @return array
     */
    public function getAllBodyParameters();
}

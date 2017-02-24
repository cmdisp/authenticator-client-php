<?php
namespace CM\Secure\Authenticator\Exceptions;

use Exception;

/**
 * Exception thrown when an HTTP request fails
 * @package CM\Secure\Authenticator\Exceptions
 */
class HttpRequestException extends Exception
{
    public function __construct($message = '', $code = 0)
    {
        parent::__construct($message, $code);
    }
}

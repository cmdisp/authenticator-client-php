<?php
namespace CM\Secure\Authenticator\Exceptions;

use Exception;

/**
 * Exception thrown when the instant JWT token was not valid
 * @package CM\Secure\Authenticator\Exceptions
 */
class AuthenticationTokenException extends Exception
{
    public function __construct($message = '', $code = 0, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

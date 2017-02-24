<?php
namespace CM\Secure\Authenticator;

use CM\Secure\Authenticator\Exceptions\AuthenticationTokenException;
use CM\Secure\Authenticator\Exceptions\HttpRequestException;
use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\SignatureInvalidException;
use InvalidArgumentException;

/**
 * Class Authenticator
 * @package CM\Secure\Authenticator
 */
class Authenticator
{
    private static $BASE_URL = 'https://api.auth.cmtelecom.com/auth/v1.0';

    const AUTH_TYPE_INSTANT = 'instant';
    const AUTH_TYPE_OTP = 'otp';

    const MSG_TYPE_AUTO = 'auto';
    const MSG_TYPE_PUSH = 'push';
    const MSG_TYPE_SMS = 'sms';

    const STATUS_OPEN = 'open';
    const STATUS_APPROVED = 'approved';
    const STATUS_DENIED = 'denied';
    const STATUS_EXPIRED = 'expired';
    const STATUS_FAILED = 'failed';

    private $envId;
    private $envSecret;

    /**
     *
     * @param $environmentId String The ID of the environment
     * @param $environmentSecret String The secret key of the environment
     * @throws InvalidArgumentException If one of the parameters is null
     */
    public function __construct($environmentId, $environmentSecret)
    {
        if (is_null($environmentId)) {
            throw new InvalidArgumentException('argument environmentId may not be null');
        }
        if (is_null($environmentSecret)) {
            throw new InvalidArgumentException('argument environmentSecret may not be null');
        }
        $this->envId = $environmentId;
        $this->envSecret = $environmentSecret;
    }

    /**
     * Create an authentication request
     * @param string $phoneNumber The phone number in international format E.164
     * @param string $authType The authentication type, either 'instant' or 'otp'
     * @param string $messageType The message type, either 'push' or 'sms'
     * @param int $expiry The time this attempt will be valid
     * @param string $ip The ip of the user
     * @return object The authentication request information
     * @throws InvalidArgumentException If one of the required parameters is null
     */
    public function requestAuthentication($phoneNumber, $authType, $messageType = Authenticator::MSG_TYPE_AUTO, $expiry = 60, $ip = null)
    {
        if (is_null($phoneNumber)) {
            throw new InvalidArgumentException('argument phoneNumber may not be null');
        }
        if (is_null($authType)) {
            throw new InvalidArgumentException('argument authType may not be null');
        }

        if ($ip == null) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        $body = json_encode([
            'environment_id' => $this->envId,
            'to' => $phoneNumber,
            'expiry' => $expiry,
            'ip' => $ip,
            'message_type' => $messageType
        ]);

        $time = time();
        $jwt = JWT::encode([
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + $expiry,
            'sig' => hash_hmac('sha256', $body, $this->envSecret)
        ], $this->envSecret);

        $ch = curl_init(self::$BASE_URL . '/' . $authType);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $jwt
        ]);

        $response = self::executeCall($ch);
        return json_decode($response);
    }

    /**
     * Get all current information of an existing authentication request
     * @param string $authId The ID of the authentication
     * @param string $authType The authentication type, either 'instant' or 'otp'
     * @return object Current authentication request information
     * @throws InvalidArgumentException If one of the parameters is null
     */
    public function getAuthentication($authId, $authType)
    {
        if (is_null($authType)) {
            throw new InvalidArgumentException('argument authType may not be null');
        }
        if (is_null($authId)) {
            throw new InvalidArgumentException('argument authId may not be null');
        }

        $time = time();
        $jwt = JWT::encode([
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 60,
            'auth_id' => $authId
        ], $this->envSecret);

        $ch = curl_init(self::$BASE_URL . '/' . $authType . '/' . $authId);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $jwt
        ]);

        $response = self::executeCall($ch);
        return json_decode($response);
    }

    /**
     * Get the authentication status from the token obtained from the WebSocket
     * @param string $token The retrieved token
     * @return string the status of the authentication
     * @throws AuthenticationTokenException Failed to validate the token
     * @throws InvalidArgumentException If the token parameter is null
     */
    public function verifyInstantToken($token)
    {
        if (is_null($token)) {
            throw new InvalidArgumentException('argument token may not be null');
        }

        JWT::$leeway = 60;

        try {
            $payload = JWT::decode($token, $this->envSecret, ['HS256']);
        } catch (SignatureInvalidException $e) {
            throw new AuthenticationTokenException('Authentication failed, invalid token signature', 0, $e);
        } catch (ExpiredException $e) {
            throw new AuthenticationTokenException('Authentication failed, token expired', 0, $e);
        } catch (Exception $e) {
            throw new AuthenticationTokenException('Authentication failed', 0, $e);
        }

        return $payload->auth_status;
    }

    /**
     * Verify an One Time Password
     * @param string $authId The ID of the authentication
     * @param string $otp The OTP
     * @return string The status of the authentication
     * @throws InvalidArgumentException If one of the parameters is null
     */
    public function verifyOTP($authId, $otp)
    {
        if (is_null($authId)) {
            throw new InvalidArgumentException('argument authId may not be null');
        }
        if (is_null($otp)) {
            throw new InvalidArgumentException('argument otp may not be null');
        }

        $body = json_encode(['pin' => $otp]);

        $time = time();
        $jwt = JWT::encode([
            'iat' => $time,
            'nbf' => $time,
            'exp' => $time + 60,
            'auth_id' => $authId,
            'sig' => hash_hmac('sha256', $body, $this->envSecret)
        ], $this->envSecret);

        $ch = curl_init(self::$BASE_URL . '/otp/' . $authId);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json; charset=utf-8',
            'Authorization: Bearer ' . $jwt
        ]);

        $response = self::executeCall($ch);
        return json_decode($response)->auth_status;
    }

    /**
     * Execute a cURL request and check the response
     * @param resource $ch The cURL handle
     * @return object The response body
     * @throws HttpRequestException The HTTP request failed
     */
    private function executeCall($ch) {
        $response = curl_exec($ch);

        if (!$response) {
            $ex = new HttpRequestException(curl_error($ch), curl_errno($ch));
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode < 200 || $httpCode >= 300) {
                $ex = new HttpRequestException($response, $httpCode);
            }
        }
        curl_close($ch);

        if (isset($ex)) {
            throw $ex;
        }

        return $response;
    }
}

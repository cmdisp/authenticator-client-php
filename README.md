# CM Authenticator PHP Client Library

## Introduction

[CM Authenticator](https://www.cmtelecom.com/products/security/authenticator) is an easy to use authentication product that ensures the identity of your online users by adding an extra factor of authentication via the mobile phone.

When a user tries to login on your environment, the extra authentication will be presented to verify their attempt. The user can approve or deny this request instantly and securely via the CM Authenticator app on their smartphone. If there is no app available, an SMS can be received instead. Once the attempt has been approved, the user can then safely proceed.

For more in-depth information, check the [API documentation](https://docs.cmtelecom.com/cm_authenticator/v1.0).

### Prerequisites

- Environment credentials (can be registered at and obtained from the [Authenticator dashboard](https://dashboard.auth.cmtelecom.com/))

### Authentication flow

**Instant:**

1. Create an instant authentication request
   - User has the app installed, but not your environment added? A QR code image will be returned.
2. The API will deliver it to the user via SMS or push
3. Check the authentication status:
   - Listen for changes using the WebSocket and verify the JWT token
   - Checking the status manually
4. If approved, grant the user access

**OTP:**

1. Create an OTP authentication request
2. The API will deliver it to the user via SMS or push
3. The user enters the OTP
4. Verify the OTP
5. If correct, grant the user access

## Installation

Use composer to manage your dependencies and download [the library](https://packagist.org/packages/cmsecure/authenticator-client):

```
$ composer require cmsecure/authenticator-client
```

## Usage

### Instantiation

Use [your environment credentials](#prerequisites) to create an `Authenticator` instance.

```php
$authenticator = new Authenticator($environmentId, $environmentSecret);
```

### Create an authentication request

```php
$auth = $authenticator->requestAuthentication($phoneNumber, $authType, $messageType, $expiry, $ip);
if (isset($auth->qr_url)) {
    echo "<img src='$auth->qr_url'>";
}
```

`$phoneNumber`: the phone number in the international [E.164](https://www.cmtelecom.com/newsroom/how-to-format-international-telephone-numbers) format

`$authType`: the [authentication type](#authentication-types)

`$messageType` (optional): the [message type](#message-types) (default: auto)

`$expiry` (optional): the amount of time in seconds the authentication request is valid (default: 60)

`$ip` (optional): the IP-address of the requesting user (default: `$_SERVER['REMOTE_ADDR'])`

`$auth`: the created authentication, parsed from the JSON response returned by the server

> If `$auth` contains the field `qr_url`, this means the user hasn't currently configured this environment in the app. The QR code image should be shown, it's used to transfer a secret key to the device. This only applies to instant verification.

### Get authentication request info manually

```php
$auth = $authenticator->getAuthentication($authId, $authType);
```

`$authId`: the ID of the authentication request, returned when the request was created

`$authType`: the [authentication type](#authentication-types)

`$authStatus`: the current [authentication status](#authentication-states)

For example, to check the authentication status:

```php
$authStatus = $auth->auth_status;
```

### Verify instant token

```php
try {
    $authStatus = $authenticator->verifyInstantToken($instantToken);
    if ($authStatus == Authenticator::STATUS_APPROVED) {
        // authentication approved
    } else {
        // authentication denied
    }
} catch (AuthenticationTokenException $e) {
    // authentication failed
}
```

`$instantToken`: the JWT token obtained from the WebSocket

`$authStatus`: the current [authentication status](#authentication-states)

> An `AuthenticationTokenException` will be thrown if the token is invalid, for example when the contents don't match the signature.

### Verify OTP

```php
$authStatus = $authenticator->verifyOTP($authId, $otp);
if ($authStatus == Authenticator::STATUS_APPROVED) {
    // authentication approved, correct OTP
} else {
    // authentication denied, incorrect OTP
}
```

`$authId`: the ID of the authentication request, returned when the request was created

`$otp`: the One-Time Password entered by the user

`$authStatus`: the current [authentication status](#authentication-states), `approved` in case the OTP was correct, `denied` if it was incorrect

## Types and states

### Authentication types

| Type    | Description                              | String value | Constant                           |
| ------- | ---------------------------------------- | ------------ | ---------------------------------- |
| Instant | Send a push notification or SMS link where the user can approve or deny the authentication request. | `instant`    | `Authenticator::AUTH_TYPE_INSTANT` |
| OTP     | Send a One-Time Password code which the user has to enter on your website. | `otp`        | `Authenticator::AUTH_TYPE_OTP`     |

### Message types

| Type           | Description                              | String value | Constant                       |
| -------------- | ---------------------------------------- | ------------ | ------------------------------ |
| Auto (default) | It will automatically determine the message type. When the authentication request cannot be delivered as a push message, it will fallback to SMS. | `auto`       | `Authenticator::MSG_TYPE_AUTO` |
| Push           | Only sent the authentication using a push message, no fallback to SMS. | `push`       | `Authenticator::MSG_TYPE_PUSH` |
| SMS            | Only sent the authentication request using SMS. | `sms`        | `Authenticator::MSG_TYPE_SMS`  |

### Authentication states

| Status   | Description                              | String value | Constant                         |
| -------- | ---------------------------------------- | ------------ | -------------------------------- |
| Open     | The user did not respond to the request yet. | `open`       | `Authenticator::STATUS_OPEN`     |
| Approved | The user approved the request.           | `approved`   | `Authenticator::STATUS_APPROVED` |
| Denied   | The user denied the the request.         | `denied`     | `Authenticator::STATUS_DENIED`   |
| Expired  | The user did not respond to the request in time. | `expired`    | `Authenticator::STATUS_EXPIRED`  |
| Failed   | The API was unable to deliver the request to the user. For example when the message type is set to push, but the user does not have the app installed. | `failed`     | `Authenticator::STATUS_FAILED`   |
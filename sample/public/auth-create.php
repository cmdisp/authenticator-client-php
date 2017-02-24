<?php
use CM\Secure\Authenticator\Authenticator;

require_once "../../vendor/autoload.php";
require_once '../config.php';

$authenticator = new Authenticator(ENVIRONMENT_ID, ENVIRONMENT_SECRET);
$auth = $authenticator->requestAuthentication(PHONE_NUMBER, Authenticator::AUTH_TYPE_INSTANT, Authenticator::MSG_TYPE_AUTO, EXPIRY, '203.0.113.1');
print("<pre>");
print_r($auth);
print("</pre>");

print("<br />");
print("<br />");

print("<a href=\"auth-get.php?id=$auth->id\">Get authentication</a>");
//$auth = $authenticator->getAuthentication($auth->id, $auth->auth_type);
//print("<pre>");
//print_r($auth);
//print("</pre>");

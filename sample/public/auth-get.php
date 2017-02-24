<?php
use CM\Secure\Authenticator\Authenticator;

require_once "../../vendor/autoload.php";
require_once '../config.php';

$authenticator = new Authenticator(ENVIRONMENT_ID, ENVIRONMENT_SECRET);
$auth = $authenticator->getAuthentication($_GET['id'], Authenticator::AUTH_TYPE_INSTANT);
print("<pre>");
print_r($auth);
print("</pre>");

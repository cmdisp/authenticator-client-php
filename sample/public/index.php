<?php
use CM\Secure\Authenticator\AuthenticationTokenException;
use CM\Secure\Authenticator\Authenticator;

require_once "../../vendor/autoload.php";
require_once '../config.php';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticator Example</title>
</head>
<body style="font-family: sans-serif;">
<?php
$authenticator = new Authenticator(ENVIRONMENT_ID, ENVIRONMENT_SECRET);

// STEP 3a: check instant result from token
if (isset($_POST['instant_token'])) {
    try {
        $authStatus = $authenticator->verifyInstantToken($_POST['instant_token']);
        if ($authStatus == Authenticator::STATUS_APPROVED) {
            echo 'Authentication approved, you are logged in';
        } else {
            echo 'Authentication denied';
        }
    } catch (AuthenticationTokenException $e) {
        echo 'Authentication failed: ' . $e->getMessage();
    }
}
// STEP 3b: verify one time password
elseif (isset($_POST['otp']) && isset($_POST['auth_id'])) {
    try {
        $authStatus = $authenticator->verifyOTP($_POST['auth_id'], $_POST['otp']);
        if ($authStatus == Authenticator::STATUS_APPROVED) {
            echo 'Authentication approved, you are logged in';
        } else {
            echo 'Authentication denied';
        }
    } catch (Exception $e) {
        echo 'Authentication failed: ' . $e->getMessage();
    }
}
// STEP 3c: expired
elseif (isset($_GET['expired'])) {
    echo 'Authentication expired';
}

// STEP 2: check credentials and request authentication
elseif (isset($_POST['username']) && isset($_POST['password']) && isset($_POST['auth_type'])) {
    if (USERNAME == $_POST['username'] && PASSWORD == $_POST['password']) {
        try {
            $authType = $_POST['auth_type'];
            $auth = $authenticator->requestAuthentication(PHONE_NUMBER, $authType);
            if ($authType == Authenticator::AUTH_TYPE_INSTANT): ?>
                <!-- display the QR code if there is one -->
                <?php if (isset($auth->qr_url))
                    echo "<p>Please scan the QR code.</p><img src='$auth->qr_url'>";
                else
                    echo '<p>Awaiting instant verification...</p>';
                ?>

                <!-- hidden form to post instant token -->
                <form method="POST" id="form_instant">
                    <input type="hidden" id="instant_token" name="instant_token" value="" />
                </form>

                <script src="//cdn.jsdelivr.net/sockjs/1/sockjs.min.js"></script>
                <script src="js/authenticator_lib.js"></script>
                <script type="application/javascript">
                    // subscribe for changes and post them back
                    var authListener = Authenticator.listen("<?php echo $auth->id ?>", <?php echo EXPIRY ?>);

                    authListener.onresponse = function (response) {
                        document.getElementsByName("instant_token")[0].value = response.token;
                        document.getElementById("form_instant").submit();
                    };

                    authListener.onexpired = function() {
                        window.location.href = window.location.href + "?expired";
                    };

                    authListener.onerror = function (code, reason) {
                        console.error("Authentication listener error. Close code: %d, close reason: %s", code, reason);
                        window.alert("An error occurred");
                    }
                </script>
            <?php elseif ($authType == Authenticator::AUTH_TYPE_OTP): ?>
                <form method="POST" id="form_instant">
                    <label for="otp">One Time Password:</label><br/>
                    <input type="text" id="otp" name="otp" value="" />
                    <input type="hidden" id="auth_id" name="auth_id" value="<?php echo $auth->id ?>" />
                    <input type="submit" value="Submit" />
                </form>

                <script>
                    setTimeout(function() {
                        // expired
                        window.location.href = window.location.href + "?expired";
                    }, <?php echo EXPIRY*1000 ?>);
                </script>
            <?php endif;
        } catch (Exception $e) {
            echo 'Requesting authentication failed: ' . $e->getMessage();
        }
    } else {
        echo '<p>Wrong username or password.</p>';
        echo '<button onclick="location.href=\'/\'" type="button">Restart</button>';
    }
}

// STEP 1: provide login credentials and choose authentication method
else { ?>
    <form method="post" class="login">
        <p>
            <label for="username"><b>Username:</b></label><br/>
            <input type="text" name="username" id="username">
        </p>
        <p>
            <label for="password"><b>Password:</b></label><br/>
            <input type="password" name="password" id="password">
        </p>
        <p>
            <label><b>Authentication type:</b></label><br/>
            <input type="radio" name="auth_type" id="instant" value="instant" checked>
            <label for="instant"> Instant authentication</label><br/>
            <input type="radio" name="auth_type" id="otp" value="otp">
            <label for="otp"> One time password</label>
        </p>
        <p>
            <input type="submit" value="Login">
        </p>
    </form>
<?php } ?>
</body>
</html>

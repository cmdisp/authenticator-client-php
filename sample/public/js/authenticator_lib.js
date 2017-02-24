var Authenticator = {};

Authenticator.listen = function (authId, expiry) {
    var statusListenerUrl = 'https://status.auth.cmtelecom.com/auth/v1.0/ws';
    var sock = new SockJS(statusListenerUrl);

    var callbacks = {
        onresponse: new Function,
        onexpired: new Function,
        onerror: new Function
    };

    var expiredTimer = setTimeout(function() {
        sock.close();
        callbacks.onexpired();
    }, expiry * 1000);

    sock.onopen = function() {
        sock.send(JSON.stringify({
            type: 'subscribe',
            auth_id: authId
        }));
    };

    sock.onmessage = function(e) {
        sock.close();
        callbacks.onresponse(JSON.parse(e.data));
    };

    sock.onerror = function(e) {
        console.error(e.toString());
    };

    sock.onclose = function(e) {
        clearInterval(expiredTimer);
        if (e.code !== 1000) {
            callbacks.onerror(e.code, e.reason);
        }
    };

    return callbacks;
};

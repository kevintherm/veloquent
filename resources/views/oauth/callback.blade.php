<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Authenticating…</title>
  <style>
    body { font-family: system-ui, sans-serif; display: flex; align-items: center;
           justify-content: center; height: 100vh; margin: 0; color: #555; }
  </style>
</head>
<body>
  <p>Completing sign-in…</p>
  <script>
    (function () {
      var code = @json($exchange_code);
      var redirectUri = @json($redirect_uri);

      if (window.opener && !window.opener.closed) {
        // Web popup flow: send exchange code back to the parent window
        window.opener.postMessage({ veloquent_oauth_code: code }, '*');
        window.close();
      } else if (redirectUri) {
        // Mobile / in-app browser flow: redirect to the registered deep link
        var sep = redirectUri.indexOf('?') !== -1 ? '&' : '?';
        window.location.href = redirectUri + sep + 'code=' + encodeURIComponent(code);
      } else {
        // Fallback: no popup, no redirect URI configured
        document.querySelector('p').textContent =
          'Authentication complete. You may close this window.';
      }
    })();
  </script>
</body>
</html>

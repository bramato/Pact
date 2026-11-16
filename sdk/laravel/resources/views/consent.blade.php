<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Authorize connection</title></head>
<body><main><h1>Authorize connection</h1><p>Allow {{ $client->master_url }} to connect your account to its PACT federation?</p>
<form method="post" action="{{ route('pact.oauth.approve') }}">@csrf<input type="hidden" name="auth_token" value="{{ $authToken }}"><button type="submit">Authorize</button></form>
<form method="post" action="{{ route('pact.oauth.deny') }}">@csrf<input type="hidden" name="auth_token" value="{{ $authToken }}"><button type="submit">Cancel</button></form>
</main></body></html>

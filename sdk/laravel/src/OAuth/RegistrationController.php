<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use PactLaravel\Support\UrlPolicy;

final class RegistrationController
{
    public function __invoke(Request $request, ConnectionService $connections): mixed
    {
        abort_if(strlen($request->getContent()) > 8192, 413);
        $data = $request->validate(['client_uri' => 'required|string|max:2048', 'redirect_uris' => 'required|array|size:1', 'redirect_uris.0' => 'required|string|max:2048', 'grant_types' => 'required|array', 'response_types' => 'required|array', 'token_endpoint_auth_method' => 'required|in:none']);
        abort_unless($data['grant_types'] === ['authorization_code', 'refresh_token'] && $data['response_types'] === ['code'], 422);
        try {
            $origin = UrlPolicy::origin($data['client_uri']);
            $callback = $origin.'/'.trim((string) config('pact.prefix', 'pact'), '/').'/oauth/callback';
            abort_unless($data['redirect_uris'] === [$callback], 422);
            $client = $connections->registerMaster($origin);
        } catch (\InvalidArgumentException|\DomainException $e) {
            return response()->json(['error' => 'invalid_client_metadata', 'error_description' => $e->getMessage()], 400);
        }

        return response()->json(['client_id' => $client->oauth_client_id, 'client_uri' => $client->master_url, 'redirect_uris' => [$client->callback_url], 'grant_types' => ['authorization_code', 'refresh_token'], 'response_types' => ['code'], 'token_endpoint_auth_method' => 'none'], 201)->header('Cache-Control', 'no-store');
    }
}

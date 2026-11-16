<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use League\OAuth2\Server\ResourceServer;
use PactLaravel\Models\OAuthClient;
use Psr\Http\Message\ServerRequestInterface;

final class Access
{
    /** @return array{user_id:string,client:OAuthClient,token_id:string} */
    public static function authenticate(ServerRequestInterface $request, ResourceServer $server): array
    {
        try {
            $request = $server->validateAuthenticatedRequest($request);
        } catch (\Throwable) {
            abort(401);
        }
        $client = OAuthClient::query()->where('oauth_client_id', $request->getAttribute('oauth_client_id'))->first();
        $userId = $request->getAttribute('oauth_user_id');
        $tokenId = $request->getAttribute('oauth_access_token_id');
        abort_unless($client && is_string($userId) && $userId !== '' && is_string($tokenId) && $tokenId !== '', 401);

        return ['user_id' => $userId, 'client' => $client, 'token_id' => $tokenId];
    }
}

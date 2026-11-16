<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use Laravel\Passport\Http\Controllers\AccessTokenController;
use PactLaravel\Models\OAuthClient;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

final class TokenController
{
    public function issue(Request $request, ServerRequestInterface $psr, ResponseInterface $response, AccessTokenController $passport): Response
    {
        $clientId = $request->input('client_id');
        abort_unless(is_string($clientId) && OAuthClient::query()->where('oauth_client_id', $clientId)->exists(), 403);
        abort_unless(in_array($request->input('grant_type'), ['authorization_code', 'refresh_token'], true), 400);

        return $passport->issueToken($psr, $response);
    }
}

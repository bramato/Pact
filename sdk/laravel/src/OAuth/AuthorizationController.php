<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Bridge\User;
use Laravel\Passport\Http\Controllers\ConvertsPsrResponses;
use Laravel\Passport\Http\Controllers\HandlesOAuthErrors;
use League\OAuth2\Server\AuthorizationServer;
use PactLaravel\Models\OAuthClient;
use Psr\Http\Message\ServerRequestInterface;

final class AuthorizationController
{
    use ConvertsPsrResponses, HandlesOAuthErrors;

    public function authorize(Request $request, ServerRequestInterface $psr, AuthorizationServer $server): mixed
    {
        $clientId = $request->query('client_id');
        abort_unless(is_string($clientId) && OAuthClient::query()->where('oauth_client_id', $clientId)->exists(), 403);
        abort_unless($request->query('response_type') === 'code' && $request->query('code_challenge_method') === 'S256'
            && is_string($request->query('code_challenge')) && preg_match('/^[A-Za-z0-9_-]{43}$/', $request->query('code_challenge')) === 1, 400);
        $auth = $this->withErrorHandling(fn () => $server->validateAuthorizationRequest($psr));
        $user = auth()->guard((string) config('pact.auth.guard', 'web'))->user();
        abort_unless($user !== null, 401);
        $auth->setUser(new User((string) $user->getAuthIdentifier()));
        $request->session()->put('authToken', $token = Str::random());
        $request->session()->put('authRequest', serialize($auth));

        return response()->view('pact::consent', ['authToken' => $token, 'client' => OAuthClient::query()->where('oauth_client_id', $clientId)->firstOrFail()]);
    }
}

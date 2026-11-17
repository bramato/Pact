<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use Pact\Federation\Policy;
use PactLaravel\Contracts\IdentityResolver;
use PactLaravel\Models\Application;
use PactLaravel\Models\LinkRequest;
use PactLaravel\Models\OAuthState;
use PactLaravel\Runtime\Store;
use PactLaravel\Support\HttpTransport;
use PactLaravel\Support\UrlPolicy;

final class CallbackController
{
    public function __invoke(Request $request, Store $store, HttpTransport $http): mixed
    {
        $state = $request->query('state');
        $code = $request->query('code');
        abort_unless(is_string($state) && $state !== '', 400);
        $identities = app(IdentityResolver::class);
        $user = $identities->current();
        abort_unless($user !== null, 401);
        $userId = $identities->id($user);
        $oauthState = $store->atomic(function () use ($state, $userId) {
            $row = OAuthState::query()->where('state_hash', hash('sha256', $state))->lockForUpdate()->first();
            abort_unless($row && (string) $row->user_id === $userId && $row->consumed_at === null && $row->expires_at->isFuture(), 403);
            $row->consumed_at = now();
            $row->save();

            return $row;
        });
        if ($request->query('error') && $oauthState->choose_team) {
            return redirect()->route('pact.connect')->withErrors(['domain' => 'Autorizzazione annullata sul server dell’agente.'])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        }
        abort_unless(is_string($code) && $code !== '' && ! $request->query('error'), 400, 'Authorization was denied.');
        $application = Application::query()->findOrFail($oauthState->application_id);
        $url = rtrim(UrlPolicy::assert((string) $application->url), '/');
        $prefix = trim((string) config('pact.prefix', 'pact'), '/');
        $form = ['grant_type' => 'authorization_code', 'client_id' => $application->client_id, 'redirect_uri' => rtrim((string) config('pact.url'), '/').'/'.$prefix.'/oauth/callback', 'code' => $code, 'code_verifier' => $oauthState->verifier];
        $token = $http->request('POST', $url.'/'.$prefix.'/oauth/token', ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query($form, '', '&', PHP_QUERY_RFC3986));
        abort_unless($token['status'] === 200 && is_string($token['body']['access_token'] ?? null) && is_string($token['body']['refresh_token'] ?? null), 502);
        $subject = $http->request('GET', $url.'/'.$prefix.'/oauth/subject', ['Authorization' => 'Bearer '.$token['body']['access_token'], 'Accept' => 'application/json']);
        $data = $subject['body'];
        abort_unless($subject['status'] === 200 && is_array($data) && is_string($data['product_id'] ?? null) && $data['product_id'] !== '' && is_string($data['user_id'] ?? null) && $data['user_id'] !== '' && strlen($data['user_id']) <= 191 && is_string($data['agent_id'] ?? null) && $data['agent_id'] === Policy::externalAgentId($data['product_id'], $data['user_id']) && is_array($data['manifest'] ?? null), 502);
        $tokens = $token['body'];
        $tokens['expires_at'] = time() + (int) ($tokens['expires_in'] ?? 3600);
        if ($oauthState->choose_team) {
            $link = LinkRequest::query()->create(['user_id' => $userId, 'application_id' => $application->id, 'payload' => ['subject' => $data, 'tokens' => $tokens], 'expires_at' => now()->addMinutes(10)]);

            return redirect()->route('pact.connect.team', ['link' => $link->id])->header('Cache-Control', 'no-store')->header('Referrer-Policy', 'no-referrer');
        }
        app(ConnectionService::class)->queueConnection($application, $userId, $data, $tokens, $store->federationFor($userId)->id);

        return response('Connection queued for activation.', 202)->header('Cache-Control', 'no-store');
    }
}

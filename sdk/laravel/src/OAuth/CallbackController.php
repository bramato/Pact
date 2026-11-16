<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Pact\Federation\Policy;
use PactLaravel\Models\Application;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
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
        $user = auth()->guard((string) config('pact.auth.guard', 'web'))->user();
        abort_unless($user !== null, 401);
        $oauthState = $store->atomic(function () use ($state, $user) {
            $row = OAuthState::query()->where('state_hash', hash('sha256', $state))->lockForUpdate()->first();
            abort_unless($row && (string) $row->user_id === (string) $user->getAuthIdentifier() && $row->consumed_at === null && $row->expires_at->isFuture(), 403);
            $row->consumed_at = now();
            $row->save();

            return $row;
        });
        abort_unless(is_string($code) && $code !== '' && ! $request->query('error'), 400, 'Authorization was denied.');
        $application = Application::query()->findOrFail($oauthState->application_id);
        $url = rtrim(UrlPolicy::assert((string) $application->url), '/');
        $prefix = trim((string) config('pact.prefix', 'pact'), '/');
        $form = ['grant_type' => 'authorization_code', 'client_id' => $application->client_id, 'redirect_uri' => rtrim((string) config('pact.url'), '/').'/'.$prefix.'/oauth/callback', 'code' => $code, 'code_verifier' => $oauthState->verifier];
        $token = $http->request('POST', $url.'/'.$prefix.'/oauth/token', ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query($form, '', '&', PHP_QUERY_RFC3986));
        abort_unless($token['status'] === 200 && is_string($token['body']['access_token'] ?? null) && is_string($token['body']['refresh_token'] ?? null), 502);
        $subject = $http->request('GET', $url.'/'.$prefix.'/oauth/subject', ['Authorization' => 'Bearer '.$token['body']['access_token'], 'Accept' => 'application/json']);
        $data = $subject['body'];
        abort_unless($subject['status'] === 200 && is_array($data) && is_string($data['product_id'] ?? null) && $data['product_id'] !== '' && is_string($data['user_id'] ?? null) && $data['user_id'] !== '' && is_string($data['agent_id'] ?? null) && $data['agent_id'] === Policy::externalAgentId($data['product_id'], $data['user_id']) && is_array($data['manifest'] ?? null), 502);
        $federation = $store->federationFor((string) $user->getAuthIdentifier());
        $store->atomic(function () use ($store, $federation, $application, $data, $token, $user, $url) {
            $connection = Connection::query()->where('user_id', (string) $user->getAuthIdentifier())->where('federation_id', $federation->id)->where('member_id', $data['agent_id'])->lockForUpdate()->first();
            $generation = (int) ($connection?->metadata['generation'] ?? 0) + 1;
            if ($connection && $connection->status === 'active') {
                $store->bump($federation->id);
            }
            if (! $connection) {
                $connection = new Connection(['id' => (string) Str::uuid(), 'user_id' => (string) $user->getAuthIdentifier(), 'federation_id' => $federation->id, 'member_id' => $data['agent_id']]);
            }
            $connection->fill(['application_id' => $application->id, 'remote_url' => $url, 'remote_user_id' => $data['user_id'], 'status' => 'pending', 'credentials' => ['access_token' => $token['body']['access_token'], 'refresh_token' => $token['body']['refresh_token'], 'expires_at' => time() + (int) ($token['body']['expires_in'] ?? 3600)], 'metadata' => ['product_id' => $data['product_id'], 'generation' => $generation]])->save();
            $member = Member::query()->firstOrNew(['federation_id' => $federation->id, 'member_id' => $data['agent_id']]);
            if (! $member->exists) {
                $member->id = (string) Str::uuid();
            }
            $member->fill(['user_id' => (string) $user->getAuthIdentifier(), 'connection_id' => $connection->id, 'active' => false, 'manifest' => $data['manifest']])->save();
            $store->enqueue('activate', null, ['connection_id' => $connection->id, 'generation' => $generation], (string) Str::uuid());
        });

        return response('Connection queued for activation.', 202)->header('Cache-Control', 'no-store');
    }
}

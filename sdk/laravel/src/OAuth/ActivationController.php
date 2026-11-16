<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;
use Pact\Federation\Policy;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\OAuthClient;
use PactLaravel\Models\Receipt;
use PactLaravel\Runtime\Store;
use PactLaravel\Support\HttpTransport;
use Psr\Http\Message\ServerRequestInterface;

final class ActivationController
{
    public function __invoke(Request $request, ServerRequestInterface $psr, ResourceServer $server, Store $store, HttpTransport $http): mixed
    {
        $identity = Access::authenticate($psr, $server);
        $data = $request->validate(['id' => 'required|uuid', 'generation' => 'required|integer|min:1', 'federation_id' => 'required|string|max:100', 'member_id' => 'required|string|max:100', 'coordinator_url' => 'required|string', 'public_key' => 'required|string|max:8192', 'credentials' => 'required|array', 'credentials.roster' => 'required|string', 'credentials.mcp' => 'required|string', 'credentials.renewal' => 'required|string', 'credentials.disconnect' => 'required|string']);
        $master = rtrim((string) $identity['client']->master_url, '/');
        abort_unless($data['coordinator_url'] === $master && $data['member_id'] === Policy::externalAgentId((string) config('pact.product_id'), $identity['user_id']), 403);
        $prefix = trim((string) config('pact.prefix', 'pact'), '/');
        $published = $http->request('GET', $master.'/'.$prefix.'/oauth/public-key');
        $publishedKey = is_array($published['body'] ?? null) ? ($published['body']['public_key'] ?? null) : null;
        $pemPattern = '/\A\s*-----BEGIN PUBLIC KEY-----\s+[A-Za-z0-9+\/=\s]+-----END PUBLIC KEY-----\s*\z/';
        $publishedParsed = is_string($publishedKey) && preg_match($pemPattern, $publishedKey) ? @openssl_pkey_get_public($publishedKey) : false;
        $submittedParsed = preg_match($pemPattern, $data['public_key']) ? @openssl_pkey_get_public($data['public_key']) : false;
        $publishedDetails = $publishedParsed ? openssl_pkey_get_details($publishedParsed) : false;
        $submittedDetails = $submittedParsed ? openssl_pkey_get_details($submittedParsed) : false;
        abort_unless($published['status'] === 200 && is_array($publishedDetails) && is_array($submittedDetails) && hash_equals($publishedDetails['key'], $submittedDetails['key']), 403);
        foreach (['roster' => 'roster', 'mcp' => 'mcp', 'renewal' => 'renew', 'disconnect' => 'disconnect'] as $key => $audience) {
            try {
                $claims = Credentials::verify($data['credentials'][$key], $master, $master.'/'.$prefix.'/'.$audience, $data['public_key']);
            } catch (\InvalidArgumentException) {
                abort(403);
            }
            abort_unless($claims['sub'] === $data['member_id'] && ($claims['federation_id'] ?? null) === $data['federation_id'] && ($claims['generation'] ?? null) === (int) $data['generation'], 403);
        }
        $connection = $store->atomic(function () use ($data, $identity, $master) {
            OAuthClient::query()->whereKey($identity['client']->getKey())->lockForUpdate()->firstOrFail();
            $fence = hash('sha256', $identity['client']->getKey()."\0".$identity['user_id']."\0".$data['id']."\0".$data['generation']);
            abort_if(Receipt::query()->where('scope', 'oauth:disconnect-fence')->where('key', $fence)->exists(), 409);
            $conflict = Connection::query()->where('user_id', $identity['user_id'])->where('remote_url', $master)->where('status', 'active')->where('federation_id', '!=', $data['federation_id'])->exists();
            abort_if($conflict, 409);
            $connection = Connection::query()->where('user_id', $identity['user_id'])->where('federation_id', $data['federation_id'])->where('member_id', $data['member_id'])->lockForUpdate()->first();
            if ($connection) {
                abort_unless($connection->remote_url === $master && ($connection->metadata['master_connection_id'] ?? null) === $data['id'], 409);
                $previous = (int) ($connection->metadata['generation'] ?? 0);
                abort_if($data['generation'] < $previous || ($connection->status === 'revoked' && $data['generation'] <= $previous), 409);
                if ($data['generation'] > $previous) {
                    $oldTokens = Passport::token()->newQuery()->where('client_id', $identity['client']->oauth_client_id)->where('user_id', $identity['user_id'])->where('id', '!=', $identity['token_id'])->pluck('id');
                    Passport::refreshToken()->newQuery()->whereIn('access_token_id', $oldTokens)->update(['revoked' => true]);
                    Passport::token()->newQuery()->whereIn('id', $oldTokens)->update(['revoked' => true]);
                }
            } else {
                $connection = new Connection(['id' => (string) Str::uuid(), 'user_id' => $identity['user_id'], 'federation_id' => $data['federation_id'], 'member_id' => $data['member_id'], 'application_id' => null, 'remote_url' => $master, 'remote_user_id' => $identity['user_id'], 'metadata' => ['master_connection_id' => $data['id']]]);
            }
            $metadata = $connection->metadata ?? [];
            $metadata['credential_renew_after'] = now()->addMinutes(30)->toISOString();
            $metadata['generation'] = $data['generation'];
            $connection->metadata = $metadata;
            $connection->credentials = $data['credentials'] + ['issuer' => $master, 'public_key' => $data['public_key']];
            $connection->status = 'active';
            $connection->save();
            $member = Member::query()->firstOrNew(['federation_id' => $data['federation_id'], 'member_id' => $data['member_id']]);
            if (! $member->exists) {
                $member->id = (string) Str::uuid();
            }
            $member->fill(['user_id' => $identity['user_id'], 'connection_id' => $connection->id, 'active' => true, 'manifest' => []])->save();

            return $connection;
        });

        return response()->json(['status' => 'active', 'connection_id' => $connection->id]);
    }
}

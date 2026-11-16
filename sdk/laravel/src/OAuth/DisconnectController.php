<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use League\OAuth2\Server\ResourceServer;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\OAuthClient;
use PactLaravel\Models\Receipt;
use PactLaravel\Runtime\Store;
use Psr\Http\Message\ServerRequestInterface;

final class DisconnectController
{
    public function __invoke(Request $request, ServerRequestInterface $psr, Store $store): mixed
    {
        $token = $request->bearerToken();
        abort_unless(is_string($token) && $token !== '', 401);
        $key = hash('sha256', $token);
        $digest = hash('sha256', $request->getContent());
        $receipt = Receipt::query()->where('scope', 'oauth:disconnect')->where('key', $key)->first();
        if ($receipt) {
            abort_unless(hash_equals($receipt->digest, $digest), 409);

            return response()->json($receipt->response);
        }
        $identity = Access::authenticate($psr, app(ResourceServer::class));
        $data = $request->validate(['master_connection_id' => 'required|uuid', 'generation' => 'required|integer|min:1']);
        $store->atomic(function () use ($data, $identity, $key, $digest): void {
            OAuthClient::query()->whereKey($identity['client']->getKey())->lockForUpdate()->firstOrFail();
            $connection = Connection::query()->where('user_id', $identity['user_id'])->where('remote_url', rtrim((string) $identity['client']->master_url, '/'))->lockForUpdate()->get()->first(fn ($item) => ($item->metadata['master_connection_id'] ?? null) === $data['master_connection_id']);
            if ($connection) {
                abort_unless((int) ($connection->metadata['generation'] ?? 0) === (int) $data['generation'], 409);
                if ($connection->status !== 'revoked') {
                    $connection->status = 'revoked';
                    $connection->credentials = [];
                    $connection->save();
                    Member::query()->where('federation_id', $connection->federation_id)->where('member_id', $connection->member_id)->update(['active' => false]);
                }
            }
            $fence = hash('sha256', $identity['client']->getKey()."\0".$identity['user_id']."\0".$data['master_connection_id']."\0".$data['generation']);
            Receipt::query()->firstOrCreate(['scope' => 'oauth:disconnect-fence', 'key' => $fence], ['id' => (string) Str::uuid(), 'digest' => $digest, 'response' => ['status' => 'revoked']]);
            Receipt::query()->create(['id' => (string) Str::uuid(), 'scope' => 'oauth:disconnect', 'key' => $key, 'digest' => $digest, 'response' => ['status' => 'revoked']]);
            Passport::refreshToken()->newQuery()->where('access_token_id', $identity['token_id'])->update(['revoked' => true]);
            Passport::token()->newQuery()->whereKey($identity['token_id'])->update(['revoked' => true]);
        });

        return response()->json(['status' => 'revoked']);
    }
}

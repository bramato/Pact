<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Runtime\Store;

final class ClientDisconnectController
{
    public function __invoke(Request $request, Store $store): mixed
    {
        $data = $request->validate(['connection_id' => 'required|uuid', 'generation' => 'required|integer|min:1']);
        $token = $request->bearerToken();
        $issuer = rtrim((string) config('pact.url'), '/');
        $audience = $issuer.'/'.trim((string) config('pact.prefix', 'pact'), '/').'/disconnect';
        $key = @file_get_contents(storage_path('app/pact/public.pem'));
        abort_unless(is_string($token) && is_string($key), 401);
        try {
            $claims = Credentials::verify($token, $issuer, $audience, $key);
        } catch (\InvalidArgumentException) {
            abort(401);
        }
        $store->atomic(function () use ($store, $data, $claims): void {
            $connection = Connection::query()->whereKey($data['connection_id'])->lockForUpdate()->firstOrFail();
            abort_unless($claims['sub'] === $connection->member_id && ($claims['federation_id'] ?? null) === $connection->federation_id && ($claims['generation'] ?? null) === (int) $data['generation'] && (int) ($connection->metadata['generation'] ?? 0) === (int) $data['generation'], 403);
            if ($connection->status !== 'revoked') {
                $connection->status = 'revoked';
                $connection->credentials = [];
                $connection->save();
                Member::query()->where('federation_id', $connection->federation_id)->where('member_id', $connection->member_id)->update(['active' => false]);
                $store->bump($connection->federation_id);
            }
        });

        return response()->json(['status' => 'revoked']);
    }
}

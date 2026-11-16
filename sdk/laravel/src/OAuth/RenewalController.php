<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Http\Request;

final class RenewalController
{
    public function __invoke(Request $request): mixed
    {
        $connection = $request->attributes->get('pact.connection');
        $base = rtrim((string) config('pact.url'), '/').'/'.trim((string) config('pact.prefix', 'pact'), '/');

        return response()->json(['roster' => Credentials::issue($connection->member_id, $base.'/roster', 3600, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1)), 'mcp' => Credentials::issue($connection->member_id, $base.'/mcp', 3600, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1)), 'renewal' => Credentials::issue($connection->member_id, $base.'/renew', 86400, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1)), 'disconnect' => Credentials::issue($connection->member_id, $base.'/disconnect', 86400, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1))])->header('Cache-Control', 'no-store');
    }
}

<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Closure;
use Illuminate\Http\Request;
use League\OAuth2\Server\ResourceServer;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\HttpFoundation\Response;

/** Validate the signature and current membership on every request. */
final class PeerMiddleware
{
    public function handle(Request $request, Closure $next, string $audience): Response
    {
        if (! in_array($audience, ['a2a', 'roster', 'mcp', 'renew'], true)) {
            abort(403);
        }
        if ($audience === 'a2a') {
            if (config('pact.role') !== 'client') {
                abort(404);
            }
            $identity = Access::authenticate(app(ServerRequestInterface::class), app(ResourceServer::class));
            $connections = Connection::query()->where('user_id', $identity['user_id'])->where('remote_url', rtrim((string) $identity['client']->master_url, '/'))->where('status', 'active')->get();
            if ($connections->count() !== 1) {
                abort(403);
            }
            foreach ($connections as $connection) {
                if ($request->route('agent') !== $connection->member_id) {
                    continue;
                }
                if (! Member::query()->where('federation_id', $connection->federation_id)->where('member_id', $connection->member_id)->where('connection_id', $connection->id)->where('active', true)->exists()) {
                    continue;
                }
                $request->attributes->set('pact.connection', $connection);
                $request->attributes->set('pact.member_id', $connection->member_id);
                $request->attributes->set('pact.producer_id', (string) $identity['client']->oauth_client_id);

                return $next($request);
            }
            abort(403);
        }
        if (config('pact.role') !== 'master') {
            abort(404);
        }
        $token = $request->bearerToken();
        if (! is_string($token) || strlen($token) > 16384) {
            abort(401);
        }
        $parts = explode('.', $token);
        $raw = count($parts) === 3 ? base64_decode(strtr($parts[1], '-_', '+/'), true) : false;
        $untrusted = $raw === false ? null : json_decode($raw, true);
        $subject = is_array($untrusted) ? ($untrusted['sub'] ?? null) : null;
        $federationId = is_array($untrusted) ? ($untrusted['federation_id'] ?? null) : null;
        if (! is_string($subject) || $subject === '' || ! is_string($federationId) || $federationId === '') {
            abort(401);
        }
        $members = Member::query()->where('member_id', $subject)->where('federation_id', $federationId)->where('active', true)->get();
        $expectedAudience = rtrim((string) config('pact.url'), '/').'/'.trim((string) config('pact.prefix', 'pact'), '/').'/'.$audience;
        foreach ($members as $member) {
            $connections = Connection::query()->where('federation_id', $member->federation_id)->where('status', 'active')->get();
            foreach ($connections as $connection) {
                $credentials = $connection->credentials ?? [];
                if (config('pact.role') === 'master') {
                    if ((string) $member->connection_id !== (string) $connection->id) {
                        continue;
                    }
                    $credentials['issuer'] = rtrim((string) config('pact.url'), '/');
                    $credentials['public_key'] = @file_get_contents(storage_path('app/pact/public.pem'));
                }
                if (! is_string($credentials['issuer'] ?? null) || ! is_string($credentials['public_key'] ?? null)) {
                    continue;
                }
                try {
                    $claims = Credentials::verify($token, $credentials['issuer'], $expectedAudience, $credentials['public_key']);
                    if (($claims['generation'] ?? null) !== (int) ($connection->metadata['generation'] ?? 0)) {
                        continue;
                    }
                    $request->attributes->set('pact.connection', $connection);
                    $request->attributes->set('pact.member_id', $subject);

                    return $next($request);
                } catch (\InvalidArgumentException) {
                    continue;
                }
            }
        }
        abort(401);
    }
}

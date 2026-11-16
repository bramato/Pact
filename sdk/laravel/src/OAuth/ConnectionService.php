<?php

declare(strict_types=1);

namespace PactLaravel\OAuth;

use Illuminate\Support\Str;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use PactLaravel\Models\Application;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\OAuthClient;
use PactLaravel\Models\OAuthState;
use PactLaravel\Runtime\Store;
use PactLaravel\Support\HttpTransport;
use PactLaravel\Support\UrlPolicy;

final class ConnectionService
{
    public function __construct(private readonly Store $store, private readonly ClientRepository $clients, private readonly HttpTransport $http) {}

    public function registerMaster(string $url): OAuthClient
    {
        if (config('pact.role') !== 'client') {
            throw new \DomainException('Master registration requires client role.');
        }
        $url = rtrim(UrlPolicy::assert($url), '/');
        $callback = $url.'/'.trim((string) config('pact.prefix', 'pact'), '/').'/oauth/callback';

        return $this->store->atomic(function () use ($url, $callback): OAuthClient {
            $existing = OAuthClient::query()->where('master_url', $url)->first();
            if ($existing) {
                return $existing;
            }
            $client = $this->clients->createAuthorizationCodeGrantClient('PACT '.$url, [$callback], false);

            return OAuthClient::query()->create(['id' => (string) Str::uuid(), 'oauth_client_id' => (string) $client->getKey(), 'master_url' => $url, 'callback_url' => $callback]);
        });
    }

    public function addApplication(string $url, string $clientId, ?string $name = null): Application
    {
        if (config('pact.role') !== 'master') {
            throw new \DomainException('Application registration requires master role.');
        }
        $url = rtrim(UrlPolicy::assert($url), '/');
        if ($clientId === '') {
            throw new \InvalidArgumentException('Client ID is required.');
        }
        $application = Application::query()->firstOrNew(['url' => $url]);
        if (! $application->exists) {
            $application->id = (string) Str::uuid();
        }
        $application->fill(['client_id' => $clientId, 'name' => $name ?: $url])->save();

        return $application;
    }

    public function connectUrl(Application|string $application, string $userId): string
    {
        if (config('pact.role') !== 'master') {
            throw new \DomainException('Connect requires master role.');
        }
        if (is_string($application)) {
            $application = Application::query()->where('id', $application)->orWhere('name', $application)->firstOrFail();
        }
        if ($userId === '') {
            throw new \InvalidArgumentException('User ID is required.');
        }
        $url = rtrim(UrlPolicy::assert((string) $application->url), '/');
        $state = self::base64(random_bytes(32));
        $verifier = self::base64(random_bytes(64));
        OAuthState::query()->create(['id' => (string) Str::uuid(), 'user_id' => $userId, 'application_id' => $application->id, 'state_hash' => hash('sha256', $state), 'verifier' => $verifier, 'expires_at' => now()->addMinutes(10)]);
        $prefix = trim((string) config('pact.prefix', 'pact'), '/');

        return $url.'/'.$prefix.'/oauth/authorize?'.http_build_query(['response_type' => 'code', 'client_id' => $application->client_id, 'redirect_uri' => rtrim((string) config('pact.url'), '/').'/'.$prefix.'/oauth/callback', 'scope' => '', 'state' => $state, 'code_challenge' => self::base64(hash('sha256', $verifier, true)), 'code_challenge_method' => 'S256'], '', '&', PHP_QUERY_RFC3986);
    }

    public function refresh(Connection $connection): Connection
    {
        $application = Application::query()->findOrFail($connection->application_id);
        $credentials = $connection->credentials ?? [];
        if (! is_string($credentials['refresh_token'] ?? null)) {
            throw new \RuntimeException('Refresh token unavailable.');
        }
        $result = $this->http->request('POST', rtrim(UrlPolicy::assert($connection->remote_url), '/').'/'.trim((string) config('pact.prefix', 'pact'), '/').'/oauth/token', ['Content-Type' => 'application/x-www-form-urlencoded', 'Accept' => 'application/json'], http_build_query(['grant_type' => 'refresh_token', 'refresh_token' => $credentials['refresh_token'], 'client_id' => $application->client_id], '', '&', PHP_QUERY_RFC3986));
        if ($result['status'] !== 200 || ! is_string($result['body']['access_token'] ?? null) || ! is_string($result['body']['refresh_token'] ?? null)) {
            throw new \RuntimeException('Could not refresh remote authorization.');
        }

        return $this->store->atomic(function () use ($connection, $credentials, $result): Connection {
            $locked = Connection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (! $this->matchesSnapshot($locked, $connection, $credentials)) {
                return $locked;
            }
            $credentials['access_token'] = $result['body']['access_token'];
            $credentials['refresh_token'] = $result['body']['refresh_token'];
            $credentials['expires_at'] = time() + (int) ($result['body']['expires_in'] ?? 3600);
            $locked->credentials = $credentials;
            $locked->save();

            return $locked;
        });
    }

    public function activate(Connection $connection, ?int $generation = null): void
    {
        if ($generation !== null && (int) ($connection->metadata['generation'] ?? 0) !== $generation) {
            return;
        }
        $activationGeneration = (int) ($connection->metadata['generation'] ?? 1);
        if ($connection->status === 'active') {
            return;
        }
        if ($connection->status !== 'pending') {
            throw new \DomainException('Connection is not pending.');
        }
        $credentials = $connection->credentials ?? [];
        if (($credentials['expires_at'] ?? 0) <= time() + 30) {
            $connection = $this->refresh($connection);
            $credentials = $connection->credentials;
        }
        if ($connection->status !== 'pending' || (int) ($connection->metadata['generation'] ?? 1) !== $activationGeneration) {
            return;
        }
        $master = rtrim((string) config('pact.url'), '/');
        $prefix = trim((string) config('pact.prefix', 'pact'), '/');
        $public = @file_get_contents(storage_path('app/pact/public.pem'));
        if ($public === false) {
            throw new \RuntimeException('PACT public key unavailable.');
        }
        $body = ['id' => $connection->id, 'generation' => (int) ($connection->metadata['generation'] ?? 1), 'federation_id' => $connection->federation_id, 'member_id' => $connection->member_id, 'coordinator_url' => $master, 'public_key' => $public, 'credentials' => ['roster' => Credentials::issue($connection->member_id, $master.'/'.$prefix.'/roster', 3600, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1)), 'mcp' => Credentials::issue($connection->member_id, $master.'/'.$prefix.'/mcp', 3600, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1)), 'renewal' => Credentials::issue($connection->member_id, $master.'/'.$prefix.'/renew', 86400, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1)), 'disconnect' => Credentials::issue($connection->member_id, $master.'/'.$prefix.'/disconnect', 86400, $connection->federation_id, (int) ($connection->metadata['generation'] ?? 1))]];
        $response = $this->http->request('POST', rtrim(UrlPolicy::assert($connection->remote_url), '/').'/'.$prefix.'/oauth/activate', ['Authorization' => 'Bearer '.$credentials['access_token'], 'Accept' => 'application/json'], $body);
        if ($response['status'] !== 200 || ($response['body']['status'] ?? null) !== 'active' || ! is_string($response['body']['connection_id'] ?? null)) {
            throw new \RuntimeException('Remote activation not confirmed.');
        }
        $this->store->atomic(function () use ($connection, $response, $activationGeneration): void {
            $locked = Connection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if ((int) ($locked->metadata['generation'] ?? 1) !== $activationGeneration) {
                return;
            }
            if ($locked->status === 'active') {
                return;
            }
            if ($locked->status === 'revoked') {
                $metadata = $locked->metadata ?? [];
                $metadata['remote_connection_id'] = $response['body']['connection_id'];
                $locked->metadata = $metadata;
                $locked->save();
                $this->store->enqueue('disconnect', null, ['connection_id' => $locked->id, 'generation' => $activationGeneration], (string) Str::uuid());

                return;
            }
            if ($locked->status !== 'pending') {
                return;
            }
            $metadata = $locked->metadata ?? [];
            $metadata['remote_connection_id'] = $response['body']['connection_id'];
            $locked->metadata = $metadata;
            $locked->status = 'active';
            $locked->save();
            Member::query()->where('federation_id', $locked->federation_id)->where('member_id', $locked->member_id)->update(['active' => true]);
            $this->store->bump($locked->federation_id);
        });
    }

    /** Renew short-lived federation credentials using the dedicated renewal audience. */
    public function renewPeerCredentials(Connection $connection): Connection
    {
        if ($connection->status !== 'active') {
            throw new \DomainException('Connection is not active.');
        }
        $credentials = $connection->credentials ?? [];
        if (! is_string($credentials['renewal'] ?? null) || ! is_string($credentials['public_key'] ?? null) || ! is_string($credentials['issuer'] ?? null)) {
            throw new \RuntimeException('Renewal credential unavailable.');
        }
        $originalCredentials = $credentials;
        $master = rtrim(UrlPolicy::assert($credentials['issuer']), '/');
        if ($master !== rtrim((string) $connection->remote_url, '/')) {
            throw new \DomainException('Unexpected coordinator.');
        }
        $base = $master.'/'.trim((string) config('pact.prefix', 'pact'), '/');
        $result = $this->http->request('POST', $base.'/renew', ['Authorization' => 'Bearer '.$credentials['renewal'], 'Accept' => 'application/json']);
        if ($result['status'] !== 200 || ! is_array($result['body'])) {
            throw new \RuntimeException('Credential renewal failed.');
        }
        foreach (['roster' => 'roster', 'mcp' => 'mcp', 'renewal' => 'renew', 'disconnect' => 'disconnect'] as $key => $audience) {
            $token = $result['body'][$key] ?? null;
            if (! is_string($token)) {
                throw new \RuntimeException('Invalid credential renewal response.');
            }
            $claims = Credentials::verify($token, $master, $base.'/'.$audience, $credentials['public_key']);
            if ($claims['sub'] !== $connection->member_id || ($claims['federation_id'] ?? null) !== $connection->federation_id || ($claims['generation'] ?? null) !== (int) ($connection->metadata['generation'] ?? 0)) {
                throw new \RuntimeException('Invalid credential renewal identity.');
            }
            $credentials[$key] = $token;
        }

        return $this->store->atomic(function () use ($connection, $credentials, $originalCredentials): Connection {
            $locked = Connection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (! $this->matchesSnapshot($locked, $connection, $originalCredentials)) {
                return $locked;
            }
            $locked->credentials = $credentials;
            $metadata = $locked->metadata ?? [];
            $metadata['credential_renew_after'] = now()->addMinutes(30)->toISOString();
            $locked->metadata = $metadata;
            $locked->save();

            return $locked;
        });
    }

    public function disconnect(string $connectionId, string $userId): void
    {
        $this->store->atomic(function () use ($connectionId, $userId): void {
            $connection = Connection::query()->whereKey($connectionId)->where('user_id', $userId)->lockForUpdate()->firstOrFail();
            if ($connection->status === 'revoked') {
                return;
            }
            $connection->status = 'revoked';
            $connection->save();
            Member::query()->where('federation_id', $connection->federation_id)->where('member_id', $connection->member_id)->update(['active' => false]);
            if (config('pact.role') === 'client') {
                $client = OAuthClient::query()->where('master_url', $connection->remote_url)->firstOrFail();
                $tokens = Passport::token()->newQuery()->where('client_id', $client->oauth_client_id)->where('user_id', $userId)->pluck('id');
                Passport::refreshToken()->newQuery()->whereIn('access_token_id', $tokens)->update(['revoked' => true]);
                Passport::token()->newQuery()->whereIn('id', $tokens)->update(['revoked' => true]);
                $kind = 'disconnect_client';
            } else {
                $this->store->bump($connection->federation_id);
                $kind = 'disconnect';
            }
            $this->store->enqueue($kind, null, ['connection_id' => $connection->id, 'generation' => (int) ($connection->metadata['generation'] ?? 1)], (string) Str::uuid());
        });
    }

    public function deliverDisconnect(Connection $connection, ?int $generation = null): void
    {
        $metadata = $connection->metadata ?? [];
        if ($generation !== null && (int) ($metadata['generation'] ?? 0) !== $generation) {
            return;
        }
        $credentials = $connection->credentials ?? [];
        $response = $this->http->request('POST', rtrim(UrlPolicy::assert($connection->remote_url), '/').'/'.trim((string) config('pact.prefix', 'pact'), '/').'/oauth/disconnect', ['Authorization' => 'Bearer '.$credentials['access_token'], 'Accept' => 'application/json'], ['master_connection_id' => $connection->id, 'generation' => (int) ($metadata['generation'] ?? 1)]);
        if ($response['status'] === 401 && ($credentials['expires_at'] ?? 0) <= time()) {
            $original = $connection;
            $connection = $this->refresh($connection);
            $credentials = $connection->credentials;
            if ($connection->status !== $original->status || (int) ($connection->metadata['generation'] ?? 1) !== (int) ($metadata['generation'] ?? 1) || ($connection->metadata['remote_connection_id'] ?? null) !== ($metadata['remote_connection_id'] ?? null)) {
                return;
            }
            $response = $this->http->request('POST', rtrim(UrlPolicy::assert($connection->remote_url), '/').'/'.trim((string) config('pact.prefix', 'pact'), '/').'/oauth/disconnect', ['Authorization' => 'Bearer '.$credentials['access_token'], 'Accept' => 'application/json'], ['master_connection_id' => $connection->id, 'generation' => (int) ($metadata['generation'] ?? 1)]);
        }
        if ($response['status'] !== 200 || ($response['body']['status'] ?? null) !== 'revoked') {
            throw new \RuntimeException('Remote disconnect not confirmed.');
        }
        $this->clearCredentialsIfUnchanged($connection, $credentials);
    }

    public function deliverClientDisconnect(Connection $connection, ?int $generation = null): void
    {
        $metadata = $connection->metadata ?? [];
        if ($generation !== null && (int) ($metadata['generation'] ?? 0) !== $generation) {
            return;
        }
        $credentials = $connection->credentials ?? [];
        if (! is_string($credentials['disconnect'] ?? null) || ! is_string($metadata['master_connection_id'] ?? null)) {
            throw new \RuntimeException('Disconnect credential unavailable.');
        }
        $url = rtrim(UrlPolicy::assert($connection->remote_url), '/').'/'.trim((string) config('pact.prefix', 'pact'), '/').'/disconnect';
        $result = $this->http->request('POST', $url, ['Authorization' => 'Bearer '.$credentials['disconnect'], 'Accept' => 'application/json'], ['connection_id' => $metadata['master_connection_id'], 'generation' => (int) ($metadata['generation'] ?? 1)]);
        if ($result['status'] !== 200 || ($result['body']['status'] ?? null) !== 'revoked') {
            throw new \RuntimeException('Coordinator disconnect not confirmed.');
        }
        $this->clearCredentialsIfUnchanged($connection, $credentials);
    }

    private function matchesSnapshot(Connection $current, Connection $snapshot, array $credentials): bool
    {
        return $current->status === $snapshot->status
            && (int) ($current->metadata['generation'] ?? 1) === (int) ($snapshot->metadata['generation'] ?? 1)
            && ($current->credentials ?? []) === $credentials;
    }

    private function clearCredentialsIfUnchanged(Connection $connection, array $credentials): void
    {
        $this->store->atomic(function () use ($connection, $credentials): void {
            $locked = Connection::query()->whereKey($connection->id)->lockForUpdate()->firstOrFail();
            if (! $this->matchesSnapshot($locked, $connection, $credentials)) {
                return;
            }
            $locked->credentials = [];
            $locked->save();
        });
    }

    private static function base64(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

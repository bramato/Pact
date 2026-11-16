<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use PactLaravel\Models\Connection;
use PactLaravel\Models\OAuthState;
use PactLaravel\Models\Outbox;
use PactLaravel\OAuth\CallbackController;
use PactLaravel\OAuth\ConnectionService;
use PactLaravel\Runtime\Store;
use PactLaravel\Support\HttpTransport;
use PactLaravel\Support\Installation;
use Symfony\Component\HttpKernel\Exception\HttpException;

final class OAuthTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migratePact();
        config(['pact.role' => 'master']);
    }

    public function test_connect_creates_independent_random_state_and_s256_pkce_for_each_user(): void
    {
        $service = new ConnectionService(new Store, $this->createStub(ClientRepository::class), new HttpTransport);
        $app = $service->addApplication('https://client.example', 'client-id', 'Client');
        $aliceUrl = $service->connectUrl($app, 'alice');
        $bobUrl = $service->connectUrl($app, 'bob');
        parse_str(parse_url($aliceUrl, PHP_URL_QUERY), $alice);
        parse_str(parse_url($bobUrl, PHP_URL_QUERY), $bob);
        self::assertNotSame($alice['state'], $bob['state']);
        self::assertSame('S256', $alice['code_challenge_method']);
        self::assertSame('https://master.example/pact/oauth/callback', $alice['redirect_uri']);
        $record = OAuthState::query()->where('state_hash', hash('sha256', $alice['state']))->firstOrFail();
        self::assertSame('alice', $record->user_id);
        self::assertSame(rtrim(strtr(base64_encode(hash('sha256', $record->verifier, true)), '+/', '-_'), '='), $alice['code_challenge']);
        self::assertStringNotContainsString($record->verifier, (string) \DB::table('pact_oauth_states')->where('id', $record->id)->value('verifier'));
        self::assertTrue($record->expires_at->isFuture());
        self::assertNull($record->consumed_at);
    }

    public function test_callback_rejects_altered_state_and_prevents_replay_after_token_failure(): void
    {
        $service = new ConnectionService(new Store, $this->createStub(ClientRepository::class), new HttpTransport);
        $app = $service->addApplication('https://client.example', 'client-id', 'Client');
        parse_str(parse_url($service->connectUrl($app, 'alice'), PHP_URL_QUERY), $query);
        $this->actingAs(new GenericUser(['id' => 'alice']));
        Http::fake(['*' => Http::response([], 400)]);
        $controller = new CallbackController;
        foreach ([[$query['state'].'altered', 403], [$query['state'], 502], [$query['state'], 403]] as [$state, $status]) {
            $request = Request::create('/pact/oauth/callback', 'GET', ['state' => $state, 'code' => 'code']);
            try {
                $controller($request, new Store, new HttpTransport);
                self::fail('Invalid or consumed state should be rejected.');
            } catch (HttpException $e) {
                self::assertSame($status, $e->getStatusCode());
            }
        }
        self::assertNotNull(OAuthState::query()->firstOrFail()->consumed_at);
        Http::assertSentCount(1);
    }

    public function test_callback_state_cannot_be_used_by_another_user(): void
    {
        $service = new ConnectionService(new Store, $this->createStub(ClientRepository::class), new HttpTransport);
        $app = $service->addApplication('https://client.example', 'client-id', 'Client');
        parse_str(parse_url($service->connectUrl($app, 'alice'), PHP_URL_QUERY), $query);
        $this->actingAs(new GenericUser(['id' => 'bob']));
        $request = Request::create('/pact/oauth/callback', 'GET', ['state' => $query['state'], 'code' => 'code']);
        try {
            (new CallbackController)($request, new Store, new HttpTransport);
            self::fail('Another user must not consume the state.');
        } catch (HttpException $e) {
            self::assertSame(403, $e->getStatusCode());
        }
        self::assertNull(OAuthState::query()->firstOrFail()->consumed_at);
    }

    public function test_late_refresh_does_not_overwrite_newer_connection_credentials(): void
    {
        $service = new ConnectionService(new Store, $this->createStub(ClientRepository::class), new HttpTransport);
        $application = $service->addApplication('https://client.example', 'client-id', 'Client');
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'agent', 'application_id' => $application->id, 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active', 'metadata' => ['generation' => 1], 'credentials' => ['access_token' => 'old', 'refresh_token' => 'old-refresh']]);
        Http::fake(function () use ($connection) {
            $current = $connection->fresh();
            $current->metadata = ['generation' => 2];
            $current->credentials = ['access_token' => 'new', 'refresh_token' => 'new-refresh'];
            $current->save();

            return Http::response(['access_token' => 'late', 'refresh_token' => 'late-refresh', 'expires_in' => 3600]);
        });
        $service->refresh($connection);
        self::assertSame(2, $connection->fresh()->metadata['generation']);
        self::assertSame('new', $connection->fresh()->credentials['access_token']);
        self::assertSame('new-refresh', $connection->fresh()->credentials['refresh_token']);
    }

    public function test_late_activation_ack_after_revocation_queues_compensating_disconnect(): void
    {
        (new Installation)->install('master', ['url' => 'https://master.example']);
        $store = new Store;
        $federation = $store->federationFor('alice');
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => $federation->id, 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'pending', 'metadata' => ['generation' => 1], 'credentials' => ['access_token' => 'token', 'expires_at' => time() + 3600]]);
        Http::fake(function () use ($connection) {
            $connection->fresh()->update(['status' => 'revoked']);

            return Http::response(['status' => 'active', 'connection_id' => 'remote-connection']);
        });
        (new ConnectionService($store, $this->createStub(ClientRepository::class), new HttpTransport))->activate($connection, 1);
        self::assertSame('revoked', $connection->fresh()->status);
        self::assertSame('remote-connection', $connection->fresh()->metadata['remote_connection_id']);
        $job = Outbox::query()->where('kind', 'disconnect')->firstOrFail();
        self::assertSame(1, $job->payload['generation']);
    }

    public function test_old_activation_ack_cannot_mutate_new_generation(): void
    {
        (new Installation)->install('master', ['url' => 'https://master.example']);
        $store = new Store;
        $federation = $store->federationFor('alice');
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => $federation->id, 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'pending', 'metadata' => ['generation' => 1], 'credentials' => ['access_token' => 'token', 'expires_at' => time() + 3600]]);
        Http::fake(function () use ($connection) {
            $connection->fresh()->update(['metadata' => ['generation' => 2], 'status' => 'pending']);

            return Http::response(['status' => 'active', 'connection_id' => 'stale']);
        });
        (new ConnectionService($store, $this->createStub(ClientRepository::class), new HttpTransport))->activate($connection, 1);
        self::assertSame('pending', $connection->fresh()->status);
        self::assertSame(['generation' => 2], $connection->fresh()->metadata);
        self::assertSame(0, Outbox::query()->count());
    }

    public function test_late_disconnect_ack_does_not_clear_new_generation_credentials(): void
    {
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed', 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'revoked', 'metadata' => ['generation' => 1, 'remote_connection_id' => 'old-id'], 'credentials' => ['access_token' => 'old', 'expires_at' => time() + 3600]]);
        Http::fake(function () use ($connection) {
            $connection->fresh()->update(['status' => 'active', 'metadata' => ['generation' => 2, 'remote_connection_id' => 'new-id'], 'credentials' => ['access_token' => 'new']]);

            return Http::response(['status' => 'revoked']);
        });
        (new ConnectionService(new Store, $this->createStub(ClientRepository::class), new HttpTransport))->deliverDisconnect($connection, 1);
        self::assertSame('active', $connection->fresh()->status);
        self::assertSame('new', $connection->fresh()->credentials['access_token']);
        self::assertSame(2, $connection->fresh()->metadata['generation']);
    }
}

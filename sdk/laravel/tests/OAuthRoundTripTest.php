<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\ClientRepository;
use Laravel\Passport\Passport;
use Laravel\Passport\PassportServiceProvider;
use Pact\Federation\Policy;
use Pact\Pact;
use PactLaravel\Mcp\Coordinator;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\Outbox;
use PactLaravel\Models\Task;
use PactLaravel\OAuth\CallbackController;
use PactLaravel\OAuth\ConnectionService;
use PactLaravel\OAuth\Credentials;
use PactLaravel\PactServiceProvider;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\TaskService;
use PactLaravel\Runtime\Worker;
use PactLaravel\Support\HttpTransport;
use PactLaravel\Support\Installation;

final class OAuthRoundTripTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(PassportServiceProvider::class);
        $this->app['config']->set('pact.role', 'client');
        $this->app['config']->set('pact.url', 'https://client.example');
        (new Installation)->install('client', ['url' => 'https://client.example']);
        $this->migratePact();
        $passport = dirname((new \ReflectionClass(Passport::class))->getFileName(), 2).'/database/migrations';
        Artisan::call('migrate', ['--path' => [$passport], '--realpath' => true, '--force' => true]);
        $this->app->register(PactServiceProvider::class);
        $this->actingAs(new GenericUser(['id' => 'remote-user']));
    }

    public function test_public_client_requires_consent_and_pkce_for_code_exchange(): void
    {
        $client = app(ConnectionService::class)->registerMaster('https://master.example');
        $verifier = str_repeat('v', 64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $query = ['client_id' => $client->oauth_client_id, 'response_type' => 'code', 'redirect_uri' => $client->callback_url, 'state' => 'state-123', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256'];
        $response = $this->get('/pact/oauth/authorize?'.http_build_query($query));
        $response->assertOk();
        $token = session('authToken');
        self::assertNotEmpty($token);
        $approval = $this->post('/pact/oauth/authorize', ['auth_token' => $token]);
        $approval->assertRedirect();
        parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $result);
        self::assertSame('state-123', $result['state']);
        self::assertNotEmpty($result['code']);
        $form = ['grant_type' => 'authorization_code', 'client_id' => $client->oauth_client_id, 'redirect_uri' => $client->callback_url, 'code' => $result['code'], 'code_verifier' => $verifier];
        $exchange = $this->post('/pact/oauth/token', $form, ['Accept' => 'application/json']);
        $exchange->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        $this->post('/pact/oauth/token', $form, ['Accept' => 'application/json'])->assertStatus(400);
        $refresh = ['grant_type' => 'refresh_token', 'client_id' => $client->oauth_client_id, 'refresh_token' => $exchange->json('refresh_token')];
        $rotated = $this->post('/pact/oauth/token', $refresh, ['Accept' => 'application/json']);
        $rotated->assertOk()->assertJsonStructure(['access_token', 'refresh_token']);
        self::assertNotSame($refresh['refresh_token'], $rotated->json('refresh_token'));
        $this->post('/pact/oauth/token', $refresh, ['Accept' => 'application/json'])->assertStatus(400);

        $clientStorage = storage_path();
        mkdir($this->isolatedStorage.'/master-storage/app', 0700, true);
        $this->app->useStoragePath($this->isolatedStorage.'/master-storage');
        config(['pact.url' => 'https://master.example']);
        (new Installation)->install('master', ['url' => 'https://master.example']);
        $public = file_get_contents(storage_path('app/pact/public.pem'));
        $member = Policy::externalAgentId((string) config('pact.product_id'), 'remote-user');
        $credentials = [];
        foreach (['roster' => 'roster', 'mcp' => 'mcp', 'renewal' => 'renew', 'disconnect' => 'disconnect'] as $key => $audience) {
            $credentials[$key] = Credentials::issue($member, 'https://master.example/pact/'.$audience, 3600, 'federation', 2);
        }
        $this->app->useStoragePath($clientStorage);
        config(['pact.url' => 'https://client.example']);
        Http::fake(['https://master.example/pact/oauth/public-key' => Http::response(['public_key' => $public])]);
        $headers = ['Authorization' => 'Bearer '.$rotated->json('access_token'), 'Accept' => 'application/json'];
        $activation = ['id' => '11111111-1111-4111-8111-111111111111', 'generation' => 2, 'federation_id' => 'federation', 'member_id' => $member, 'coordinator_url' => 'https://master.example', 'public_key' => $public, 'credentials' => $credentials];
        $this->postJson('/pact/oauth/activate', $activation, $headers)->assertOk()->assertJson(['status' => 'active']);
        $this->postJson('/pact/oauth/activate', $activation, $headers)->assertOk();
        $connection = Connection::query()->where('member_id', $member)->firstOrFail();
        $taskService = app(TaskService::class);
        $rpc = ['jsonrpc' => '2.0', 'id' => 'rpc-1', 'method' => 'SendMessage', 'params' => ['message' => ['role' => 'ROLE_USER', 'messageId' => 'm-1', 'parts' => [['data' => ['message' => 'hello']]], 'metadata' => [Pact::CORE_URI => ['version' => Pact::VERSION, 'contract' => $taskService->contract(), 'idempotency_key' => 'rpc-1']]]]];
        $a2aHeaders = $headers + ['A2A-Version' => '1.0', 'A2A-Extensions' => Pact::CORE_URI];
        $this->postJson('/pact/agents/'.rawurlencode($member).'/a2a', $rpc, $a2aHeaders)->assertOk()->assertJsonPath('result.task.status.state', 'TASK_STATE_WORKING');
        $this->postJson('/pact/agents/another-member/a2a', $rpc, $a2aHeaders)->assertStatus(403);

        $freshToken = function () use ($query, $client, $verifier): string {
            $this->get('/pact/oauth/authorize?'.http_build_query($query))->assertOk();
            $approved = $this->post('/pact/oauth/authorize', ['auth_token' => session('authToken')]);
            parse_str((string) parse_url($approved->headers->get('Location'), PHP_URL_QUERY), $code);
            $response = $this->post('/pact/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $client->oauth_client_id, 'redirect_uri' => $client->callback_url, 'code' => $code['code'], 'code_verifier' => $verifier], ['Accept' => 'application/json']);
            $response->assertOk();

            return $response->json('access_token');
        };
        $disconnectToken = $freshToken();
        $delayedToken = $freshToken();
        $this->postJson('/pact/oauth/disconnect', ['master_connection_id' => $activation['id'], 'generation' => 1], $headers)->assertStatus(409);
        self::assertSame('active', $connection->fresh()->status);
        $body = ['master_connection_id' => $activation['id'], 'generation' => 2];
        $this->postJson('/pact/oauth/disconnect', $body, $headers)->assertOk()->assertJson(['status' => 'revoked']);
        self::assertSame('revoked', $connection->fresh()->status);
        $this->postJson('/pact/oauth/disconnect', $body, $headers)->assertOk();
        $this->postJson('/pact/oauth/disconnect', ['master_connection_id' => $activation['id'], 'generation' => 3], $headers)->assertStatus(409);
        $unknown = '22222222-2222-4222-8222-222222222222';
        $this->postJson('/pact/oauth/disconnect', ['master_connection_id' => $unknown, 'generation' => 2], ['Authorization' => 'Bearer '.$disconnectToken, 'Accept' => 'application/json'])->assertOk();
        $delayed = $activation;
        $delayed['id'] = $unknown;
        $delayed['federation_id'] = 'different-federation';
        $this->app->useStoragePath($this->isolatedStorage.'/master-storage');
        config(['pact.url' => 'https://master.example']);
        foreach (['roster' => 'roster', 'mcp' => 'mcp', 'renewal' => 'renew', 'disconnect' => 'disconnect'] as $key => $audience) {
            $delayed['credentials'][$key] = Credentials::issue($member, 'https://master.example/pact/'.$audience, 3600, 'different-federation', 2);
        }
        $this->app->useStoragePath($clientStorage);
        config(['pact.url' => 'https://client.example']);
        $this->postJson('/pact/oauth/activate', $delayed, ['Authorization' => 'Bearer '.$delayedToken, 'Accept' => 'application/json'])->assertStatus(409);

    }

    public function test_denied_consent_returns_no_code_and_invalid_challenge_is_rejected(): void
    {
        $client = app(ConnectionService::class)->registerMaster('https://master.example');
        $query = ['client_id' => $client->oauth_client_id, 'response_type' => 'code', 'redirect_uri' => $client->callback_url, 'state' => 'denied-state', 'code_challenge' => str_repeat('a', 43), 'code_challenge_method' => 'plain'];
        $this->get('/pact/oauth/authorize?'.http_build_query($query))->assertStatus(400);
        $query['code_challenge_method'] = 'S256';
        $this->get('/pact/oauth/authorize?'.http_build_query($query))->assertOk();
        $denied = $this->post('/pact/oauth/deny', ['auth_token' => session('authToken')]);
        $denied->assertRedirect();
        parse_str((string) parse_url($denied->headers->get('Location'), PHP_URL_QUERY), $result);
        self::assertSame('access_denied', $result['error']);
        self::assertSame('denied-state', $result['state']);
        self::assertArrayNotHasKey('code', $result);
    }

    public function test_wrong_redirect_and_wrong_pkce_verifier_cannot_exchange_code(): void
    {
        $client = app(ConnectionService::class)->registerMaster('https://master.example');
        $verifier = str_repeat('v', 64);
        $query = ['client_id' => $client->oauth_client_id, 'response_type' => 'code', 'redirect_uri' => $client->callback_url, 'state' => 'state', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'];
        $this->get('/pact/oauth/authorize?'.http_build_query($query))->assertOk();
        $approved = $this->post('/pact/oauth/authorize', ['auth_token' => session('authToken')]);
        parse_str((string) parse_url($approved->headers->get('Location'), PHP_URL_QUERY), $result);
        $form = ['grant_type' => 'authorization_code', 'client_id' => $client->oauth_client_id, 'redirect_uri' => 'https://attacker.example/callback', 'code' => $result['code'], 'code_verifier' => $verifier];
        $this->post('/pact/oauth/token', $form, ['Accept' => 'application/json'])->assertStatus(400);
        $form['redirect_uri'] = $client->callback_url;
        $form['code_verifier'] = str_repeat('x', 64);
        $this->post('/pact/oauth/token', $form, ['Accept' => 'application/json'])->assertStatus(400);
    }

    public function test_master_callback_queues_and_confirms_real_client_activation(): void
    {
        $clientStorage = storage_path();
        $clientProduct = config('pact.product_id');
        $registration = app(ConnectionService::class)->registerMaster('https://master.example');
        $verifier = str_repeat('z', 64);
        $query = ['client_id' => $registration->oauth_client_id, 'response_type' => 'code', 'redirect_uri' => $registration->callback_url, 'state' => 'client-state', 'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='), 'code_challenge_method' => 'S256'];
        $this->get('/pact/oauth/authorize?'.http_build_query($query))->assertOk();
        $approval = $this->post('/pact/oauth/authorize', ['auth_token' => session('authToken')]);
        parse_str((string) parse_url($approval->headers->get('Location'), PHP_URL_QUERY), $result);
        $exchange = $this->post('/pact/oauth/token', ['grant_type' => 'authorization_code', 'client_id' => $registration->oauth_client_id, 'redirect_uri' => $registration->callback_url, 'code' => $result['code'], 'code_verifier' => $verifier], ['Accept' => 'application/json']);
        $exchange->assertOk();
        $token = $exchange->json();
        $subject = $this->getJson('/pact/oauth/subject', ['Authorization' => 'Bearer '.$token['access_token']]);
        $subject->assertOk();
        $subjectData = $subject->json();

        config(['database.connections.master_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'pact.database_connection' => 'master_test', 'pact.role' => 'master', 'pact.url' => 'https://master.example']);
        mkdir($this->isolatedStorage.'/master-storage/app', 0700, true);
        $this->app->useStoragePath($this->isolatedStorage.'/master-storage');
        (new Installation)->install('master', ['url' => 'https://master.example']);
        $public = file_get_contents(storage_path('app/pact/public.pem'));
        $this->migratePact();
        $store = new Store;
        $service = new ConnectionService($store, app(ClientRepository::class), new HttpTransport);
        $application = $service->addApplication('https://client.example', $registration->oauth_client_id, 'client');
        parse_str(parse_url($service->connectUrl($application, 'alice'), PHP_URL_QUERY), $masterQuery);
        $this->actingAs(new GenericUser(['id' => 'alice']));
        Http::fake(function ($request) use ($token, $subjectData, $public, $clientStorage, $clientProduct) {
            if (str_ends_with($request->url(), '/oauth/token')) {
                return Http::response($token);
            }
            if (str_ends_with($request->url(), '/oauth/subject')) {
                return Http::response($subjectData);
            }
            if (str_ends_with($request->url(), '/oauth/public-key')) {
                return Http::response(['public_key' => $public]);
            }
            if (str_ends_with($request->url(), '/oauth/activate') || str_ends_with($request->url(), '/a2a')) {
                $masterStorage = storage_path();
                $this->app->useStoragePath($clientStorage);
                config(['pact.database_connection' => null, 'pact.role' => 'client', 'pact.url' => 'https://client.example', 'pact.product_id' => $clientProduct]);
                try {
                    $headers = ['Authorization' => $request->header('Authorization')[0], 'Accept' => 'application/json'];
                    $path = parse_url($request->url(), PHP_URL_PATH);
                    if (str_ends_with($path, '/a2a')) {
                        $headers['A2A-Version'] = '1.0';
                        $headers['A2A-Extensions'] = Pact::CORE_URI;
                        $payload = json_decode($request->body(), true, 512, JSON_THROW_ON_ERROR);
                    } else {
                        $payload = $request->data();
                    }
                    $response = $this->postJson($path, $payload, $headers);

                    return Http::response($response->json(), $response->getStatusCode(), ['A2A-Extensions' => $response->headers->get('A2A-Extensions', '')]);
                } finally {
                    $this->app->useStoragePath($masterStorage);
                    config(['pact.database_connection' => 'master_test', 'pact.role' => 'master', 'pact.url' => 'https://master.example']);
                }
            }
            throw new \RuntimeException('Unexpected request: '.$request->url());
        });
        $request = Request::create('/pact/oauth/callback', 'GET', ['state' => $masterQuery['state'], 'code' => 'pre-exchanged-code']);
        $callback = (new CallbackController)($request, $store, new HttpTransport);
        self::assertSame(202, $callback->getStatusCode());
        $connection = Connection::query()->firstOrFail();
        self::assertSame('pending', $connection->status);
        self::assertTrue((new Worker($store))->workOnce());
        self::assertSame('active', $connection->fresh()->status);
        self::assertTrue(Member::query()->where('connection_id', $connection->id)->firstOrFail()->active);
        $dispatch = (new Coordinator($store))->dispatch($connection, 'alice', 'send_message', ['id' => 'diagnostic-1', 'recipient_id' => $connection->member_id, 'text' => 'hello']);
        self::assertTrue((new Worker($store))->workOnce());
        self::assertSame('done', Outbox::query()->where('kind', 'peer_send')->firstOrFail()->state, (string) Outbox::query()->where('kind', 'peer_send')->firstOrFail()->error);
        $this->app->useStoragePath($clientStorage);
        config(['pact.database_connection' => null, 'pact.role' => 'client', 'pact.url' => 'https://client.example', 'pact.product_id' => $clientProduct]);
        self::assertTrue((new Worker(new Store))->workOnce());
        $this->app->useStoragePath($this->isolatedStorage.'/master-storage');
        config(['pact.database_connection' => 'master_test', 'pact.role' => 'master', 'pact.url' => 'https://master.example']);
        $this->travel(3)->seconds();
        self::assertTrue((new Worker($store))->workOnce());
        self::assertSame('TASK_STATE_COMPLETED', Task::query()->findOrFail($dispatch['task_id'])->state);

    }
}

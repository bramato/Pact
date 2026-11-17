<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Http;
use Laravel\Passport\PassportServiceProvider;
use Pact\Federation\Policy;
use PactLaravel\Models\Application;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Federation;
use PactLaravel\Models\LinkRequest;
use PactLaravel\Models\Member;
use PactLaravel\Models\OAuthState;
use PactLaravel\Models\Outbox;
use PactLaravel\OAuth\ConnectionService;
use PactLaravel\PactManager;
use PactLaravel\PactServiceProvider;
use PactLaravel\Runtime\Store;
use PactLaravel\Support\PeerResolver;
use PactLaravel\Support\UrlPolicy;

final class AgentLinkTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->register(PassportServiceProvider::class);
        config(['pact.role' => 'master']);
        $this->migratePact();
        $this->app->register(PactServiceProvider::class);
        $this->actingAs(new GenericUser(['id' => 'alice', 'remember_token' => null]));
    }

    private function discovery(array $overrides = []): array
    {
        return $overrides + ['pact_version' => '0.1', 'role' => 'client', 'issuer' => 'https://client.example', 'pact_endpoint' => 'https://client.example/pact', 'authorization_endpoint' => 'https://client.example/pact/oauth/authorize', 'token_endpoint' => 'https://client.example/pact/oauth/token', 'registration_endpoint' => 'https://client.example/pact/oauth/register', 'code_challenge_methods_supported' => ['S256']];
    }

    private function fakePeer(array $metadata = []): void
    {
        Http::fake([
            'https://client.example/.well-known/pact' => Http::response($this->discovery($metadata)),
            'https://client.example/pact/oauth/register' => Http::response(['client_id' => 'public-client', 'client_uri' => 'https://master.example', 'redirect_uris' => ['https://master.example/pact/oauth/callback'], 'token_endpoint_auth_method' => 'none'], 201),
            'https://client.example/pact/oauth/token' => Http::response(['access_token' => 'secret-access', 'refresh_token' => 'secret-refresh', 'expires_in' => 3600]),
            'https://client.example/pact/oauth/subject' => Http::response(['product_id' => 'product', 'user_id' => 'tenant:remote', 'agent_id' => Policy::externalAgentId('product', 'tenant:remote'), 'manifest' => []]),
        ]);
    }

    private function beginAndReturn(): LinkRequest
    {
        $this->fakePeer();
        $start = $this->post('/pact/connect', ['domain' => 'client.example']);
        $start->assertRedirect();
        parse_str(parse_url($start->headers->get('Location'), PHP_URL_QUERY), $query);
        $callback = $this->get('/pact/oauth/callback?'.http_build_query(['state' => $query['state'], 'code' => 'once']));
        $callback->assertRedirect();
        preg_match('~/connect/([^/]+)/team~', $callback->headers->get('Location'), $match);

        return LinkRequest::query()->findOrFail($match[1]);
    }

    public function test_domain_only_starts_registration_and_pkce_from_the_master(): void
    {
        $this->get('/pact/agents')->assertOk()->assertSee('Collega agente');
        $this->get('/pact/connect')->assertOk()->assertSee('Dominio del server');
        $this->fakePeer();
        $start = $this->post('/pact/connect', ['domain' => 'client.example']);
        $start->assertRedirect();
        self::assertStringStartsWith('https://client.example/pact/oauth/authorize?', $start->headers->get('Location'));
        $state = OAuthState::query()->firstOrFail();
        self::assertTrue((bool) $state->choose_team);
        self::assertSame('alice', $state->user_id);
        self::assertSame('https://client.example', Application::query()->firstOrFail()->url);
        Http::assertSent(fn ($r) => $r->url() === 'https://client.example/pact/oauth/register' && $r['client_uri'] === 'https://master.example' && $r['redirect_uris'] === ['https://master.example/pact/oauth/callback'] && ! $r->hasHeader('Authorization'));
        self::assertSame(0, Connection::query()->count());
    }

    public function test_callback_waits_for_team_choice_and_encrypts_temporary_credentials(): void
    {
        $link = $this->beginAndReturn();
        self::assertSame(0, Connection::query()->count());
        self::assertSame(0, Outbox::query()->count());
        self::assertStringNotContainsString('secret-access', (string) $link->getRawOriginal('payload'));
        self::assertStringNotContainsString('secret-refresh', json_encode($link));
        $this->get('/pact/connect/'.$link->id.'/team')->assertOk()->assertSee('Dove mettiamo')->assertDontSee('secret-access')->assertDontSee('secret-refresh');
        $team = (new Store)->createTeam('alice', 'Documenti');
        $response = $this->post('/pact/connect/'.$link->id.'/team', ['team_id' => $team->id]);
        $response->assertRedirect(route('pact.agents', ['team' => $team->id]));
        $connection = Connection::query()->firstOrFail();
        self::assertSame($team->id, $connection->federation_id);
        self::assertSame('tenant:remote', $connection->remote_user_id);
        self::assertSame('pending', $connection->status);
        self::assertFalse(Member::query()->where('connection_id', $connection->id)->firstOrFail()->active);
        self::assertNull($link->fresh()->payload);
        self::assertSame(1, Outbox::query()->where('kind', 'activate')->count());
        $this->post('/pact/connect/'.$link->id.'/team', ['team_id' => $team->id])->assertRedirect();
        self::assertSame(1, Outbox::query()->count());
        $this->get('/pact/agents?team='.$team->id)->assertOk()->assertSee('In attesa della conferma')->assertDontSee('secret-access');
        self::assertCount(2, app(PactManager::class)->members($team->id));
        self::assertCount(1, app(PactManager::class)->members());
    }

    public function test_new_team_is_created_once_and_isolated_from_another_user(): void
    {
        $link = $this->beginAndReturn();
        $path = '/pact/connect/'.$link->id.'/team';
        $this->post($path, ['new_team' => 'Ricerca'])->assertRedirect();
        $this->post($path, ['new_team' => 'Ricerca'])->assertRedirect();
        self::assertSame(1, Federation::query()->where('name', 'Ricerca')->count());
        self::assertSame(1, Connection::query()->count());
        $teamId = Connection::query()->firstOrFail()->federation_id;
        $this->post($path, ['new_team' => 'Diversa'])->assertStatus(409);
        $this->actingAs(new GenericUser(['id' => 'bob']));
        $this->get($path)->assertNotFound();
        $this->post($path, ['team_id' => $teamId])->assertNotFound();
        $this->get('/pact/agents?team='.$teamId)->assertNotFound();
        $this->post('/pact/connections/'.Connection::query()->firstOrFail()->id.'/disconnect')->assertNotFound();
    }

    public function test_another_users_team_cannot_be_selected_or_receive_activation(): void
    {
        $link = $this->beginAndReturn();
        $team = (new Store)->createTeam('bob', 'Privata');
        $this->post('/pact/connect/'.$link->id.'/team', ['team_id' => $team->id])->assertNotFound();
        self::assertSame(0, Connection::query()->count());
        self::assertNotNull($link->fresh()->payload);
    }

    public function test_expired_and_cancelled_links_cannot_be_activated(): void
    {
        $link = $this->beginAndReturn();
        $link->update(['expires_at' => now()->subSecond()]);
        $team = (new Store)->federationFor('alice');
        $this->get('/pact/connect/'.$link->id.'/team')->assertStatus(410);
        $this->post('/pact/connect/'.$link->id.'/team', ['team_id' => $team->id])->assertStatus(410);
        $link->update(['expires_at' => now()->addMinute()]);
        $this->post('/pact/connect/'.$link->id.'/cancel')->assertRedirect();
        self::assertNull($link->fresh()->payload);
        $this->post('/pact/connect/'.$link->id.'/team', ['team_id' => $team->id])->assertStatus(410);
        self::assertSame(0, Outbox::query()->count());
    }

    public function test_oauth_denial_and_replayed_callback_never_create_a_membership(): void
    {
        $this->fakePeer();
        $url = app(ConnectionService::class)->connectDomain('client.example', 'alice');
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $callback = '/pact/oauth/callback?'.http_build_query(['state' => $query['state'], 'error' => 'access_denied']);
        $this->get($callback)->assertRedirect(route('pact.connect'));
        $this->get($callback)->assertForbidden();
        self::assertSame(0, LinkRequest::query()->count());
        self::assertSame(0, Connection::query()->count());
        Http::assertSentCount(2);
    }

    public function test_discovery_rejects_foreign_endpoints_and_does_not_register_them(): void
    {
        $this->fakePeer(['token_endpoint' => 'https://attacker.example/token']);
        $this->from('/pact/connect')->post('/pact/connect', ['domain' => 'client.example'])->assertRedirect('/pact/connect')->assertSessionHasErrors('domain');
        Http::assertSentCount(1);
        self::assertSame(0, Application::query()->count());
    }

    public function test_domain_input_rejects_paths_credentials_and_private_dns_addresses(): void
    {
        foreach (['https://client.example/path', 'https://user:secret@client.example', 'https://client.example?next=evil', 'http://client.example'] as $domain) {
            try {
                UrlPolicy::origin($domain);
                self::fail('Invalid domain accepted');
            } catch (\InvalidArgumentException) {
            }
        }
        $this->app->bind(PeerResolver::class, fn () => new class extends PeerResolver {
            public function addresses(string $host): array { return ['93.184.216.34', '127.0.0.1']; }
        });
        Http::fake();
        $this->from('/pact/connect')->post('/pact/connect', ['domain' => 'private.example'])->assertSessionHasErrors('domain');
        Http::assertNothingSent();
    }

    public function test_transport_pins_dns_and_rejects_discovery_redirects(): void
    {
        Http::fake(function ($request, $options) {
            self::assertSame(['client.example:443:93.184.216.34'], $options['curl'][CURLOPT_RESOLVE]);
            self::assertFalse($options['allow_redirects']);
            self::assertSame('', $options['proxy']);

            return Http::response([], 302, ['Location' => 'https://attacker.example/metadata']);
        });
        $this->from('/pact/connect')->post('/pact/connect', ['domain' => 'client.example'])->assertSessionHasErrors('domain');
        Http::assertSentCount(1);
        self::assertSame(0, OAuthState::query()->count());
    }

    public function test_registration_response_must_bind_the_master_callback(): void
    {
        Http::fake([
            'https://client.example/.well-known/pact' => Http::response($this->discovery()),
            'https://client.example/pact/oauth/register' => Http::response(['client_id' => 'public-client', 'client_uri' => 'https://master.example', 'redirect_uris' => ['https://attacker.example/callback'], 'token_endpoint_auth_method' => 'none'], 201),
        ]);
        $this->from('/pact/connect')->post('/pact/connect', ['domain' => 'client.example'])->assertSessionHasErrors('domain');
        self::assertSame(0, OAuthState::query()->count());
        self::assertSame(0, Application::query()->count());
    }

    public function test_worker_erases_expired_staged_credentials(): void
    {
        $link = $this->beginAndReturn();
        $link->update(['expires_at' => now()->subSecond()]);
        (new \PactLaravel\Runtime\Worker(new Store))->workOnce();
        self::assertNull($link->fresh()->payload);
        self::assertSame(0, Connection::query()->count());
    }

    public function test_unauthenticated_users_cannot_start_or_finish_linking(): void
    {
        $this->app['router']->get('/login', fn () => 'login')->name('login');
        auth()->logout();
        $this->get('/pact/connect')->assertRedirect('/login');
        $this->post('/pact/connect', ['domain' => 'client.example'])->assertRedirect('/login');
        Http::assertNothingSent();
    }

    public function test_reauthorization_preserves_identity_and_does_not_silently_move_the_agent(): void
    {
        $link = $this->beginAndReturn();
        $team = (new Store)->createTeam('alice', 'Prima');
        $this->post('/pact/connect/'.$link->id.'/team', ['team_id' => $team->id])->assertRedirect();
        $connection = Connection::query()->firstOrFail();
        $second = $this->beginAndReturn();
        $other = (new Store)->createTeam('alice', 'Seconda');
        $this->post('/pact/connect/'.$second->id.'/team', ['team_id' => $other->id])->assertStatus(409);
        $this->post('/pact/connect/'.$second->id.'/team', ['team_id' => $team->id])->assertRedirect();
        self::assertSame(1, Connection::query()->count());
        self::assertSame($connection->id, Connection::query()->firstOrFail()->id);
        self::assertSame(2, $connection->fresh()->metadata['generation']);
    }
}

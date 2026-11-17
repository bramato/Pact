<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PactLaravel\Models\Connection;
use PactLaravel\Runtime\Store;

final class LinkMigrationTest extends TestCase
{
    public function test_upgrade_preserves_original_federation_connections_and_oauth_clients(): void
    {
        (require __DIR__.'/../database/migrations/2026_11_16_000001_create_pact_runtime.php')->up();
        DB::table('pact_federations')->insert(['id' => 'old-team', 'user_id' => 'alice', 'revision' => 7]);
        DB::table('pact_oauth_clients')->insert(['id' => 'old-client', 'oauth_client_id' => 'public-id', 'master_url' => 'https://master.example', 'callback_url' => 'https://master.example/pact/oauth/callback']);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'old-team', 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'remote', 'status' => 'active', 'credentials' => ['access_token' => 'preserved']]);
        $migration = require __DIR__.'/../database/migrations/2026_11_17_000002_add_agent_linking.php';
        $migration->up();
        self::assertSame('old-team', (new Store)->federationFor('alice')->id);
        self::assertSame('Squadra personale', (new Store)->federationFor('alice')->name);
        self::assertSame('preserved', $connection->fresh()->credentials['access_token']);
        self::assertSame('active', $connection->fresh()->status);
        self::assertSame(hash('sha256', 'https://master.example'), DB::table('pact_oauth_clients')->value('origin_hash'));
        $team = (new Store)->createTeam('alice', 'Nuova');
        try {
            $migration->down();
            self::fail('Rollback must not destroy additional teams');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('additional teams', $e->getMessage());
        }
        self::assertTrue(Schema::hasTable('pact_link_requests'));
        $team->delete();
        $migration->down();
        self::assertFalse(Schema::hasColumn('pact_federations', 'default_user_id'));
        self::assertSame('preserved', $connection->fresh()->credentials['access_token']);
    }
}

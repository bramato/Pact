<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('pact.database_connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $schema->table('pact_federations', function (Blueprint $t): void {
            $t->dropUnique(['user_id']);
            $t->string('name')->default('Squadra personale');
            $t->string('default_user_id', 191)->nullable()->unique();
            $t->index('user_id');
        });
        DB::connection($this->getConnection())->table('pact_federations')->update(['default_user_id' => DB::raw('user_id')]);
        $schema->table('pact_oauth_states', fn (Blueprint $t) => $t->boolean('choose_team')->default(false));
        $schema->table('pact_oauth_clients', fn (Blueprint $t) => $t->char('origin_hash', 64)->nullable()->unique());
        foreach (DB::connection($this->getConnection())->table('pact_oauth_clients')->get() as $client) {
            DB::connection($this->getConnection())->table('pact_oauth_clients')->where('id', $client->id)->update(['origin_hash' => hash('sha256', $client->master_url)]);
        }
        $schema->create('pact_link_requests', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('user_id', 191)->index();
            $t->uuid('application_id');
            $t->text('payload')->nullable();
            $t->timestamp('expires_at')->index();
            $t->uuid('connection_id')->nullable();
            $t->char('selection_hash', 64)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        // Refuse rollback rather than discard teams created after the upgrade.
        $db = DB::connection($this->getConnection());
        if ($db->table('pact_federations')->select('user_id')->groupBy('user_id')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Remove additional teams before rolling back agent linking.');
        }
        $schema = Schema::connection($this->getConnection());
        $schema->dropIfExists('pact_link_requests');
        $schema->table('pact_oauth_clients', function (Blueprint $t): void {
            $t->dropUnique(['origin_hash']);
            $t->dropColumn('origin_hash');
        });
        $schema->table('pact_oauth_states', fn (Blueprint $t) => $t->dropColumn('choose_team'));
        $schema->table('pact_federations', function (Blueprint $t): void {
            $t->dropUnique(['default_user_id']);
            $t->dropIndex(['user_id']);
            $t->dropColumn(['name', 'default_user_id']);
            $t->unique('user_id');
        });
    }
};

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
        $schema->create('pact_applications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('name')->unique();
            $t->text('url');
            $t->string('client_id');
            $t->timestamps();
        });
        $schema->create('pact_connections', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('user_id', 191);
            $t->string('federation_id', 100);
            $t->string('member_id', 100);
            $t->uuid('application_id')->nullable();
            $t->text('remote_url');
            $t->string('remote_user_id', 191);
            $t->string('status')->default('pending')->index();
            $t->text('credentials')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
            $t->unique(['user_id', 'federation_id', 'member_id'], 'pact_connection_identity');
        });
        $schema->create('pact_federations', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('user_id', 191)->unique();
            $t->unsignedInteger('revision')->default(1);
            $t->json('state')->nullable();
            $t->timestamps();
        });
        $schema->create('pact_members', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('federation_id', 100);
            $t->string('member_id', 100);
            $t->string('user_id', 191);
            $t->uuid('connection_id')->nullable();
            $t->boolean('active')->default(false);
            $t->json('manifest')->nullable();
            $t->timestamps();
            $t->unique(['federation_id', 'member_id']);
        });
        $schema->create('pact_tasks', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->uuid('connection_id')->nullable();
            $t->string('user_id', 191);
            $t->string('federation_id', 100);
            $t->string('root_id', 100)->index();
            $t->string('parent_id', 100)->nullable()->index();
            $t->string('delegation_id', 100)->nullable();
            $t->string('caller_id', 100);
            $t->string('state');
            $t->json('value');
            $t->json('checkpoint')->nullable();
            $t->unsignedInteger('version')->default(0);
            $t->timestamps();
            $t->index(['federation_id', 'user_id']);
        });
        $schema->create('pact_receipts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('scope', 191);
            $t->string('key', 191);
            $t->char('digest', 64);
            $t->json('response');
            $t->timestamps();
            $t->unique(['scope', 'key']);
        });
        $schema->create('pact_outbox', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('kind');
            $t->uuid('task_id')->nullable()->index();
            $t->text('payload');
            $t->string('key', 191)->nullable()->unique();
            $t->string('state')->default('pending');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('available_at')->nullable();
            $t->timestamp('lease_until')->nullable();
            $t->string('owner')->nullable();
            $t->text('error')->nullable();
            $t->timestamps();
            $t->index(['state', 'available_at', 'lease_until']);
        });
        $schema->create('pact_event_receipts', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('scope', 191);
            $t->string('event_id', 191);
            $t->uuid('task_id')->index();
            $t->string('disposition');
            $t->unsignedBigInteger('sequence');
            $t->char('digest', 64);
            $t->text('raw');
            $t->json('payload');
            $t->timestamps();
            $t->unique(['scope', 'event_id']);
        });
        $schema->create('pact_oauth_states', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('user_id', 191);
            $t->uuid('application_id');
            $t->char('state_hash', 64)->unique();
            $t->text('verifier');
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
            $t->timestamps();
        });
        $schema->create('pact_oauth_clients', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('oauth_client_id')->unique();
            $t->text('master_url');
            $t->text('callback_url');
            $t->timestamps();
        });
        $schema->create('pact_heartbeats', function (Blueprint $t): void {
            $t->string('id')->primary();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['pact_heartbeats', 'pact_oauth_clients', 'pact_oauth_states', 'pact_event_receipts', 'pact_outbox', 'pact_receipts', 'pact_tasks', 'pact_members', 'pact_federations', 'pact_connections', 'pact_applications'] as $table) {
            Schema::connection($this->getConnection())->dropIfExists($table);
        }
    }
};

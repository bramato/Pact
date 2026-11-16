<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Laravel\Mcp\Server\McpServiceProvider;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Federation;
use PactLaravel\Models\Member;
use PactLaravel\OAuth\Credentials;
use PactLaravel\PactServiceProvider;
use PactLaravel\Support\Installation;

final class McpHttpTest extends TestCase
{
    public function test_authenticated_mcp_initialize_and_tool_discovery(): void
    {
        $this->migratePact();
        config(['pact.role' => 'master']);
        (new Installation)->install('master', ['url' => 'https://master.example']);
        Federation::query()->create(['id' => 'federation', 'user_id' => 'alice', 'revision' => 1, 'state' => []]);
        $connection = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'federation', 'member_id' => 'agent', 'remote_url' => 'https://client.example', 'remote_user_id' => 'bob', 'status' => 'active', 'metadata' => ['generation' => 1]]);
        Member::query()->create(['federation_id' => 'federation', 'member_id' => 'agent', 'user_id' => 'alice', 'connection_id' => $connection->id, 'active' => true]);
        $this->app->register(McpServiceProvider::class);
        $this->app->register(PactServiceProvider::class);
        $token = Credentials::issue('agent', 'https://master.example/pact/mcp', 3600, 'federation', 1);
        $headers = ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json, text/event-stream'];
        $initialize = ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2025-03-26', 'capabilities' => (object) [], 'clientInfo' => ['name' => 'test', 'version' => '1']]];
        $response = $this->postJson('/pact/mcp', $initialize, $headers);
        $response->assertOk();
        self::assertStringContainsString('pact-federation', $response->getContent());
        $response = $this->postJson('/pact/mcp', ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $headers);
        $response->assertOk();
        self::assertStringContainsString('list_members', $response->getContent());
        $result = $this->postJson('/pact/mcp', ['jsonrpc' => '2.0', 'id' => 3, 'method' => 'tools/call', 'params' => ['name' => 'list_members', 'arguments' => (object) []]], $headers);
        $result->assertOk();
        self::assertStringContainsString('agent', $result->getContent());
        $this->postJson('/pact/mcp', $initialize, ['Accept' => 'application/json, text/event-stream'])->assertStatus(401);
    }
}

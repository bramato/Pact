<?php

declare(strict_types=1);

namespace PactLaravel\Tests;

use Illuminate\Support\Facades\Http;
use Pact\Pact;
use PactLaravel\Contracts\AgentHandler;
use PactLaravel\Execution\AgentContext;
use PactLaravel\Execution\AgentResult;
use PactLaravel\Mcp\Coordinator;
use PactLaravel\Models\Connection;
use PactLaravel\Models\Member;
use PactLaravel\Models\Task;
use PactLaravel\Runtime\Store;
use PactLaravel\Runtime\TaskService;
use PactLaravel\Runtime\Worker;

final class MultiDatabasePeerTest extends TestCase
{
    public function test_delegation_transits_two_isolated_client_databases_and_resumes_parent(): void
    {
        foreach (['master', 'client_a', 'client_b'] as $name) {
            config(["database.connections.$name" => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'pact.database_connection' => $name]);
            $this->migratePact();
        }
        $dir = __DIR__.'/../resources/contracts/agent-chat';
        $contract = json_decode(file_get_contents($dir.'/contract.json'), true, 512, JSON_THROW_ON_ERROR);
        file_put_contents($this->isolatedStorage.'/contract.json', json_encode($contract, JSON_THROW_ON_ERROR));
        config(['pact.contract_path' => $this->isolatedStorage.'/contract.json', 'pact.contract_registry' => "$dir/registry.json", 'pact.handler' => MultiDatabaseHandler::class]);
        $clientConnections = [];
        foreach (['client_a' => 'agent-a', 'client_b' => 'agent-b'] as $db => $member) {
            config(['pact.database_connection' => $db]);
            $connection = Connection::query()->create(['user_id' => 'remote-user', 'federation_id' => 'fed-a', 'member_id' => $member, 'remote_url' => 'https://master.example', 'remote_user_id' => 'alice', 'status' => 'active']);
            $clientConnections[$db] = $connection->id;
        }
        config(['pact.database_connection' => 'master', 'pact.role' => 'master']);
        $connections = [];
        foreach (['client_a' => 'agent-a', 'client_b' => 'agent-b'] as $db => $member) {
            $connections[$db] = Connection::query()->create(['user_id' => 'alice', 'federation_id' => 'fed-a', 'member_id' => $member, 'remote_url' => 'https://'.str_replace('_', '-', $db).'.example', 'remote_user_id' => 'remote-user', 'status' => 'active', 'credentials' => ['access_token' => 'token', 'expires_at' => time() + 3600]]);
            Member::query()->create(['federation_id' => 'fed-a', 'member_id' => $member, 'user_id' => 'alice', 'connection_id' => $connections[$db]->id, 'active' => true, 'manifest' => ['capabilities' => ['extensions' => [['uri' => Pact::CORE_URI, 'params' => ['contracts' => [$contract]]]]]]]);
        }
        Http::fake(function ($request) use ($clientConnections) {
            $db = str_contains($request->url(), 'client-a.example') ? 'client_a' : 'client_b';
            $previous = config('pact.database_connection');
            config(['pact.database_connection' => $db, 'pact.role' => 'client']);
            try {
                $rpc = json_decode($request->body(), false, 512, JSON_THROW_ON_ERROR);
                $result = app(TaskService::class)->rpc(Connection::query()->findOrFail($clientConnections[$db]), $db === 'client_a' ? 'agent-a' : 'agent-b', $rpc, $request->body());

                return Http::response(['jsonrpc' => '2.0', 'id' => $rpc->id, 'result' => $result], 200, ['A2A-Extensions' => Pact::CORE_URI]);
            } finally {
                config(['pact.database_connection' => $previous, 'pact.role' => 'master']);
            }
        });
        $root = Task::query()->create(['id' => '11111111-1111-4111-8111-111111111111', 'connection_id' => $connections['client_a']->id, 'user_id' => 'alice', 'federation_id' => 'fed-a', 'root_id' => '11111111-1111-4111-8111-111111111111', 'caller_id' => 'agent-a', 'state' => 'TASK_STATE_WORKING', 'value' => [], 'checkpoint' => [], 'version' => 1]);
        $result = (new Coordinator(new Store))->dispatch($connections['client_a'], 'agent-a', 'request_delegation', ['id' => 'delegation', 'recipient_id' => 'agent-b', 'task_id' => $root->id, 'input' => ['message' => 'hello']]);
        self::assertTrue((new Worker(new Store))->workOnce());
        config(['pact.database_connection' => 'client_b', 'pact.role' => 'client']);
        self::assertSame(1, Task::query()->count());
        self::assertTrue((new Worker(new Store))->workOnce());
        config(['pact.database_connection' => 'client_a']);
        self::assertSame(0, Task::query()->count());
        config(['pact.database_connection' => 'master', 'pact.role' => 'master']);
        $this->travel(3)->seconds();
        self::assertTrue((new Worker(new Store))->workOnce());
        self::assertSame('TASK_STATE_COMPLETED', Task::query()->findOrFail($result['task_id'])->state);
        self::assertArrayHasKey('delegation_result', $root->fresh()->checkpoint);
    }
}

final class MultiDatabaseHandler implements AgentHandler
{
    public function handle(AgentContext $context): AgentResult
    {
        return AgentResult::completed(['text' => 'answer: '.$context->input['message']]);
    }
}

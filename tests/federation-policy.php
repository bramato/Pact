<?php

declare(strict_types=1);
require __DIR__.'/../sdk/php/vendor/autoload.php';
use Pact\Federation\Policy;
use Pact\Federation\Runtime;
use Pact\Federation\McpDispatcher;
use Pact\PactError;

function rejected(callable $f): void { try { $f(); } catch (PactError $e) { return; } throw new LogicException('Expected rejection'); }
$p = new Policy;
$r = json_decode('{"federation_id":"f","coordinator_url":"https://coordinator.example/","revision":1,"mcp_endpoint":"https://coordinator.example/mcp","members":[{"id":"alice","kind":"user","active":true},{"id":"bot","kind":"agent","active":true},{"id":"blocked","kind":"user","active":false}]}');
$tag = Policy::etag($r);
if ($p->refresh(null, $r, $r->coordinator_url, $tag, 200) !== $r || $p->refresh($r, null, $r->coordinator_url, $tag, 304) !== $r) throw new LogicException('refresh');
$changed = clone $r; $changed->mcp_endpoint = 'https://evil.example/mcp';
rejected(fn () => $p->refresh($r, $changed, $r->coordinator_url, Policy::etag($changed), 200));
$old = clone $r; $old->revision = 0;
rejected(fn () => $p->refresh($r, $old, $r->coordinator_url, Policy::etag($old), 200));
$claims = (object) ['iss' => 'issuer', 'aud' => 'mcp', 'sub' => 'alice', 'exp' => 101];
if ($p->authenticate($claims, 'issuer', 'mcp', 100) !== 'alice') throw new LogicException('auth');
rejected(fn () => $p->authenticate($claims, 'issuer', 'peer', 100));
rejected(fn () => $p->authenticate($claims, 'issuer', 'mcp', 101));
rejected(fn () => $p->member($r, 'blocked'));
rejected(fn () => $p->conversation($r, 'alice', ['bot']));
if (Policy::externalAgentId('ab', 'c') === Policy::externalAgentId('a', 'bc')) throw new LogicException('identity collision');
$runtime = new class implements Runtime {
    public array $records = []; public int $calls = 0;
    public function transaction(callable $work): mixed { return $work(); }
    public function appendExchange(string $federation, string $actor, string $id, string $destination, string $payload): bool {
        if (isset($this->records[$id])) { if ($this->records[$id]['payload'] !== $payload) throw new PactError('CONFLICT', 'conflict'); return false; }
        $this->records[$id] = ['payload' => $payload]; return true;
    }
    public function replayResult(string $federation, string $actor, string $id): mixed { return $this->records[$id]['result']; }
    public function saveResult(string $federation, string $actor, string $id, mixed $result): void { $this->records[$id]['result'] = $result; }
    public function checkpoint(string $federation, string $task, array $state): void {}
    public function invoke(string $method, string $actor, array $arguments): mixed { ++$this->calls; return ['accepted' => true]; }
};
$d = new McpDispatcher($runtime);
$args = ['id' => 'one', 'recipient_id' => 'bot', 'conversation_id' => 'private', 'text' => 'hello'];
$d->dispatch($r, 'alice', 'send_message', $args);
$d->dispatch($r, 'alice', 'send_message', $args);
if ($runtime->calls !== 1) throw new LogicException('duplicate invoked');
$args['text'] = 'different'; rejected(fn () => $d->dispatch($r, 'alice', 'send_message', $args));
rejected(fn () => $d->dispatch($r, 'blocked', 'list_members', []));
rejected(fn () => $d->dispatch($r, 'alice', 'send_message', ['extra' => 'x']));
echo "Federation policy and MCP passed\n";
$fixture = json_decode(file_get_contents(__DIR__.'/../conformance/federation/fixtures/roster.json'));
$expected = json_decode(file_get_contents(__DIR__.'/../conformance/federation/fixtures/expected.json'));
if (Policy::etag($fixture) !== $expected->etag || Policy::externalAgentId('prodotto', 'utente') !== $expected->agent_id) throw new LogicException('Cross-SDK fixture mismatch');
$local = clone $r; $local->coordinator_url = 'http://octodorabot.test/'; $local->mcp_endpoint = 'http://octodorabot.test/mcp';
rejected(fn () => $p->refresh(null, $local, $local->coordinator_url, Policy::etag($local), 200));
$localPolicy = new Policy(new Pact\Pact, ['octodorabot.test']);
if ($localPolicy->refresh(null, $local, $local->coordinator_url, Policy::etag($local), 200) !== $local) throw new LogicException('allowed local host');
$public = clone $local; $public->coordinator_url = 'http://example.com/';
rejected(fn () => (new Policy(new Pact\Pact, ['example.com', 'octodorabot.test']))->refresh(null, $public, $public->coordinator_url, Policy::etag($public), 200));

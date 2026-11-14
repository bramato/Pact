<?php

declare(strict_types=1);
require __DIR__.'/../sdk/php/vendor/autoload.php';
use Pact\Federation\SqliteRuntime;
use Pact\Federation\McpDispatcher;

final class TestRuntime extends SqliteRuntime {
    public int $calls = 0;
    public bool $fail = false;
    public function invoke(string $method, string $actor, array $arguments): mixed {
        ++$this->calls;
        $this->checkpoint('personal-1', 'task', ['waiting' => true]);
        if ($this->fail) throw new RuntimeException('failure');
        return (object) ['receipt' => $arguments['id']];
    }
}
$file = tempnam(sys_get_temp_dir(), 'pact-runtime-');
try {
    $r = json_decode(file_get_contents(__DIR__.'/../conformance/federation/fixtures/roster.json'));
    $runtime = new TestRuntime(new PDO('sqlite:'.$file));
    $dispatcher = new McpDispatcher($runtime);
    $args = ['id' => 'one', 'recipient_id' => 'bot', 'conversation_id' => 'private', 'text' => 'hello'];
    $runtime->fail = true;
    try { $dispatcher->dispatch($r, 'alice', 'send_message', $args); throw new LogicException('failure swallowed'); } catch (RuntimeException $e) {}
    if ($runtime->pending() !== [] || $runtime->loadCheckpoint('personal-1', 'task') !== null) throw new LogicException('rollback failed');
    $runtime->fail = false;
    $first = $dispatcher->dispatch($r, 'alice', 'send_message', $args);
    unset($dispatcher, $runtime);
    $runtime = new TestRuntime(new PDO('sqlite:'.$file));
    $dispatcher = new McpDispatcher($runtime);
    if ($dispatcher->dispatch($r, 'alice', 'send_message', $args) !== $first || $runtime->calls !== 0) throw new LogicException('replay failed');
    if (count($runtime->pending()) !== 1 || $runtime->loadCheckpoint('personal-1', 'task') !== ['waiting' => true]) throw new LogicException('restart failed');
    $runtime->acknowledge('personal-1', 'alice', 'one', 'accepted');
    $runtime->acknowledge('personal-1', 'alice', 'one', 'accepted');
    if ($runtime->pending() !== []) throw new LogicException('receipt failed');
    try { $runtime->acknowledge('personal-1', 'alice', 'one', 'other'); throw new LogicException('receipt conflict accepted'); } catch (RuntimeException $e) {}
    echo "Federation runtime passed\n";
} finally { unlink($file); }

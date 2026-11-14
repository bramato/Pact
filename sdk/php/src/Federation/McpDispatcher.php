<?php

declare(strict_types=1);

namespace Pact\Federation;

use Pact\Pact;
use stdClass;

/** Adapter-neutral MCP tool dispatcher; the host authenticates tokens and supplies the roster. */
final class McpDispatcher
{
    private const METHODS = ['list_members', 'get_manifest', 'send_message', 'request_delegation', 'get_task', 'read_conversation', 'reserve_step'];

    public function __construct(private readonly Runtime $runtime, private readonly Policy $policy = new Policy) {}

    public static function definitions(): array
    {
        $string = ['type' => 'string', 'minLength' => 1];
        $fields = [
            'list_members' => [], 'get_manifest' => ['member_id' => $string],
            'send_message' => ['id' => $string, 'recipient_id' => $string, 'text' => $string, 'conversation_id' => $string, 'kind' => ['type'=>'string','enum'=>['request','reply','notification']], 'task_id' => $string],
            'request_delegation' => ['id' => $string, 'recipient_id' => $string, 'task_id' => $string, 'input' => ['type' => 'object']],
            'get_task' => ['task_id' => $string], 'read_conversation' => ['conversation_id' => $string], 'reserve_step' => ['task_id'=>$string,'step_id'=>$string],
        ];
        $required = ['send_message'=>['id','recipient_id','text']];
        $definitions = [];
        foreach ($fields as $name => $properties) {
            $definitions[] = ['name' => $name, 'inputSchema' => ['type' => 'object', 'properties' => (object) $properties, 'required' => $required[$name] ?? array_keys($properties), 'additionalProperties' => false]];
        }
        return $definitions;
    }

    public function dispatch(stdClass $roster, string $actor, string $method, array $arguments): mixed
    {
        $this->policy->member($roster, $actor);
        Pact::ensure(in_array($method, self::METHODS, true), 'FEDERATION_METHOD', 'Unknown MCP method');
        $definition = array_values(array_filter(self::definitions(), fn ($item) => $item['name'] === $method))[0];
        $properties = (array) $definition['inputSchema']['properties'];
        Pact::ensure(count(array_diff(array_keys($arguments), array_keys($properties))) === 0 && count(array_diff($definition['inputSchema']['required'], array_keys($arguments))) === 0, 'FEDERATION_ARGUMENTS', 'Invalid MCP arguments');
        foreach ($properties as $name => $schema) {
            if (! array_key_exists($name, $arguments)) continue;
            $value = $arguments[$name];
            Pact::ensure($schema['type'] === 'string' ? is_string($value) && $value !== '' : $value instanceof stdClass, 'FEDERATION_ARGUMENTS', 'Invalid MCP argument '.$name);
            if (isset($schema['enum'])) Pact::ensure(in_array($value,$schema['enum'],true), 'FEDERATION_ARGUMENTS', 'Invalid MCP argument '.$name);
        }
        if ($method === 'send_message') {
            $arguments['kind'] ??= 'request';
            Pact::ensure($arguments['kind'] === 'request' || isset($arguments['conversation_id']), 'FEDERATION_ARGUMENTS', 'Replies require a conversation');
        }
        if (isset($arguments['recipient_id'])) $this->policy->member($roster, $arguments['recipient_id']);
        if (isset($arguments['member_id'])) $this->policy->member($roster, $arguments['member_id']);
        // Runtime owns resource-level authorization; reads never create a mirrored session.
        return $this->runtime->transaction(function () use ($roster, $actor, $method, $arguments) {
            if (! in_array($method, ['send_message', 'request_delegation'], true)) {
                return $this->runtime->invoke($method, $actor, $arguments);
            }
            $payload = Pact::canonical((object) ['method' => $method, 'arguments' => (object) $arguments]);
            $id = $arguments['id'];
            if (! $this->runtime->appendExchange($roster->federation_id, $actor, $id, $arguments['recipient_id'], $payload)) {
                return $this->runtime->replayResult($roster->federation_id, $actor, $id);
            }
            $result = json_decode(json_encode($this->runtime->invoke($method, $actor, $arguments), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            $this->runtime->saveResult($roster->federation_id, $actor, $id, $result);
            return $result;
        });
    }
}

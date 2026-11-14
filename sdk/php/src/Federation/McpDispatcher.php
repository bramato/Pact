<?php

declare(strict_types=1);

namespace Pact\Federation;

use Pact\Pact;
use stdClass;

/** Adapter-neutral MCP tool dispatcher; the host authenticates tokens and supplies the roster. */
final class McpDispatcher
{
    private const METHODS = ['list_members', 'get_manifest', 'send_message', 'request_delegation', 'get_task', 'read_conversation'];

    public function __construct(private readonly Runtime $runtime, private readonly Policy $policy = new Policy) {}

    public static function definitions(): array
    {
        $string = ['type' => 'string', 'minLength' => 1];
        $fields = [
            'list_members' => [], 'get_manifest' => ['member_id' => $string],
            'send_message' => ['id' => $string, 'recipient_id' => $string, 'conversation_id' => $string, 'text' => $string],
            'request_delegation' => ['id' => $string, 'recipient_id' => $string, 'task_id' => $string, 'input' => ['type' => 'object']],
            'get_task' => ['task_id' => $string], 'read_conversation' => ['conversation_id' => $string],
        ];
        $definitions = [];
        foreach ($fields as $name => $properties) {
            $definitions[] = ['name' => $name, 'inputSchema' => ['type' => 'object', 'properties' => (object) $properties, 'required' => array_keys($properties), 'additionalProperties' => false]];
        }
        return $definitions;
    }

    public function dispatch(stdClass $roster, string $actor, string $method, array $arguments): mixed
    {
        $this->policy->member($roster, $actor);
        Pact::ensure(in_array($method, self::METHODS, true), 'FEDERATION_METHOD', 'Unknown MCP method');
        $definition = array_values(array_filter(self::definitions(), fn ($item) => $item['name'] === $method))[0];
        $properties = (array) $definition['inputSchema']['properties'];
        Pact::ensure(count(array_diff(array_keys($arguments), array_keys($properties))) === 0 && count(array_diff(array_keys($properties), array_keys($arguments))) === 0, 'FEDERATION_ARGUMENTS', 'Invalid MCP arguments');
        foreach ($properties as $name => $schema) {
            $value = $arguments[$name];
            Pact::ensure($schema['type'] === 'string' ? is_string($value) && $value !== '' : $value instanceof stdClass, 'FEDERATION_ARGUMENTS', 'Invalid MCP argument '.$name);
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
            $result = $this->runtime->invoke($method, $actor, $arguments);
            $this->runtime->saveResult($roster->federation_id, $actor, $id, $result);
            return $result;
        });
    }
}

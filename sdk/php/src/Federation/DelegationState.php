<?php

declare(strict_types=1);

namespace Pact\Federation;

use Pact\Pact;

/** Pure root-scoped transitions. Persist the returned state with outbox work in one transaction. */
final class DelegationState
{
    public static function start(string $root, string $member, int $now): array
    {
        Pact::ensure($root !== '' && $member !== '', 'FEDERATION_DELEGATION', 'Root and member required');
        return ['root' => $root, 'created_at' => $now, 'deadline' => $now + 300, 'steps' => 0, 'delegations' => 0,
            'tasks' => [$root => ['parent' => null, 'delegation' => null, 'path' => [$member], 'status' => 'active', 'observations' => []]]];
    }

    public static function step(array $state, int $now): array
    {
        self::budget($state, $now);
        Pact::ensure($state['steps'] < 16, 'FEDERATION_BUDGET', 'Root step budget exhausted');
        ++$state['steps'];
        return $state;
    }

    public static function reserve(array $state, string $parent, string $task, string $delegation, string $member, int $now, bool $nestedSwarm = false): array
    {
        self::budget($state, $now);
        $tasks = $state['tasks'];
        Pact::ensure(! $nestedSwarm, 'FEDERATION_SWARM', 'Nested Swarm is prohibited');
        Pact::ensure(isset($tasks[$parent]) && $tasks[$parent]['status'] === 'active', 'FEDERATION_DELEGATION', 'Active parent required');
        Pact::ensure($task !== '' && $delegation !== '' && $member !== '' && ! isset($tasks[$task]) && $delegation !== $task && ! isset($tasks[$delegation]), 'FEDERATION_DELEGATION', 'Distinct task and delegation IDs required');
        foreach ($tasks as $existing) Pact::ensure($existing['delegation'] !== $delegation && $existing['delegation'] !== $task, 'FEDERATION_DELEGATION', 'Task or delegation ID already used');
        $path = $tasks[$parent]['path'];
        Pact::ensure(count($path) < 4 && ! in_array($member, $path, true), 'FEDERATION_DELEGATION', 'Depth or cycle limit exceeded');
        Pact::ensure($state['delegations'] < 8, 'FEDERATION_BUDGET', 'Root delegation budget exhausted');
        $path[] = $member;
        $state['tasks'][$task] = ['parent' => $parent, 'delegation' => $delegation, 'path' => $path, 'status' => 'active', 'observations' => []];
        ++$state['delegations'];
        return $state;
    }

    public static function cancel(array $state, string $task): array
    {
        Pact::ensure(isset($state['tasks'][$task]), 'FEDERATION_DELEGATION', 'Unknown task');
        $pending = [$task];
        while ($pending !== []) {
            $id = array_pop($pending);
            if ($state['tasks'][$id]['status'] === 'active') $state['tasks'][$id]['status'] = 'canceled';
            foreach ($state['tasks'] as $child => $record) if ($record['parent'] === $id) $pending[] = $child;
        }
        return $state;
    }

    /** Late reports are retained, but cannot reactivate or complete a canceled task. */
    public static function observe(array $state, string $task, string $status): array
    {
        Pact::ensure(isset($state['tasks'][$task]) && in_array($status, ['working', 'completed', 'failed', 'canceled'], true), 'FEDERATION_DELEGATION', 'Invalid observation');
        $state['tasks'][$task]['observations'][] = $status;
        if ($state['tasks'][$task]['status'] === 'active' && $status !== 'working') $state['tasks'][$task]['status'] = $status;
        return $state;
    }

    private static function budget(array $state, int $now): void
    {
        Pact::ensure($now >= $state['created_at'] && $now < $state['deadline'], 'FEDERATION_BUDGET', 'Root deadline expired');
    }
}

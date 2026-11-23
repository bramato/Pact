<?php

declare(strict_types=1);

namespace PactLaravel\Execution;

use Illuminate\Support\Str;
use stdClass;

/** Reads external execution briefs carried in the agent-chat message field. */
final class StructuredAgentCall
{
    public function decode(string $message): ?stdClass
    {
        if (! str_starts_with(ltrim($message), '{') || mb_strlen($message) > 10000) {
            return null;
        }

        $call = json_decode($message, false, 16);
        if (! $call instanceof stdClass
            || ! is_string($call->task_id ?? null) || ! Str::isUuid($call->task_id)
            || ! is_string($call->run_id ?? null) || ! Str::isUuid($call->run_id)
            || ! is_int($call->revision ?? null) || $call->revision < 1
            || ($call->kind ?? null) !== 'execution'
            || ! ($call->work ?? null) instanceof stdClass
            || array_diff(array_keys(get_object_vars($call)), ['task_id', 'run_id', 'revision', 'kind', 'work']) !== []) {
            return null;
        }

        $work = $call->work;
        $task = $work->task ?? null;
        if (! $task instanceof stdClass || ! is_string($task->title ?? null) || trim($task->title) === ''
            || ! is_string($task->description ?? null) || trim($task->description) === ''
            || (isset($task->specifications) && ! is_string($task->specifications))
            || (isset($task->revision) && ! is_int($task->revision))
            || array_diff(array_keys(get_object_vars($task)), ['title', 'description', 'specifications', 'revision']) !== []
            || ($work->attachments ?? null) !== []
            || ! is_array($work->handoffs ?? null)
            || array_diff(array_keys(get_object_vars($work)), ['task', 'attachments', 'handoffs']) !== []) {
            return null;
        }

        foreach ($work->handoffs as $handoff) {
            if (! $handoff instanceof stdClass || ! is_string($handoff->title ?? null)
                || (isset($handoff->note) && ! is_string($handoff->note))
                || array_diff(array_keys(get_object_vars($handoff)), ['title', 'note']) !== []) {
                return null;
            }
        }

        return $call;
    }

    public function readable(string $message): string
    {
        $call = $this->decode($message);
        if ($call === null) {
            return $message;
        }

        $task = $call->work->task;
        $sections = [trim($task->title), trim($task->description)];
        $specifications = trim($task->specifications ?? '');
        if ($specifications !== '') {
            $sections[] = "Specifiche\n".$specifications;
        }
        if ($call->work->handoffs !== []) {
            $handoffs = array_map(
                fn (stdClass $handoff): string => trim($handoff->title."\n".($handoff->note ?? '')),
                $call->work->handoffs,
            );
            $sections[] = "Contributi precedenti\n\n".implode("\n\n", $handoffs);
        }

        return implode("\n\n", $sections);
    }
}

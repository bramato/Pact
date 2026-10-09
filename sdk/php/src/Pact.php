<?php

declare(strict_types=1);

namespace Pact;

use Opis\JsonSchema\CompliantValidator;
use stdClass;

final class Pact
{
    public const VERSION = '0.2.0';

    public const CORE_URI = 'https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md';

    public const SWARM_URI = 'https://github.com/bramato/Pact/blob/main/specification/PACT-SWARM-v0.2.md';

    public const SCHEMA_BASE = 'https://github.com/bramato/Pact/blob/main/specification/0.2/schemas/';

    private CompliantValidator $validator;

    private array $schemas;

    public function __construct(?string $schemaFile = null)
    {
        $this->validator = new CompliantValidator;
        $this->schemas = (array) json_decode(file_get_contents($schemaFile ?? __DIR__.'/../resources/schemas.json'), false, 512, JSON_THROW_ON_ERROR);
        foreach ($this->schemas as $schema) {
            $this->validator->resolver()->registerRaw($schema);
        }
    }

    public static function ensure(bool $condition, string $code, string $message): void
    {
        if (! $condition) {
            throw new PactError($code, $message);
        }
    }

    public function shape(string $name, mixed $value): void
    {
        self::ensure(isset($this->schemas[$name]), 'SCHEMA_UNKNOWN', 'Unknown resource');
        $result = $this->validator->validate($value, $this->schemas[$name]);
        if (! $result->isValid()) {
            throw new PactError('SCHEMA_INVALID', 'Invalid '.$name);
        }
    }

    private static function jsonSafety(mixed $value): void
    {
        if (is_int($value) || is_float($value)) {
            self::ensure(is_finite((float) $value) && (floor((float) $value) != $value || abs($value) <= 9007199254740991), 'JSON_NUMBER', 'Unsafe JSON number');
        } elseif (is_string($value)) {
            self::ensure(mb_check_encoding($value, 'UTF-8'), 'JSON_UNICODE', 'Invalid Unicode');
        } elseif (is_array($value) || $value instanceof stdClass) {
            foreach ($value as $key => $child) {
                self::jsonSafety((string) $key);
                self::jsonSafety($child);
            }
        } else {
            self::ensure($value === null || is_bool($value), 'JSON_VALUE', 'Unsupported JSON value');
        }
    }

    public static function canonical(mixed $value): string
    {
        self::jsonSafety($value);

        return self::serializeCanonical($value);
    }

    private static function serializeCanonical(mixed $value): string
    {
        if ($value instanceof stdClass) {
            $members = (array) $value;
            uksort($members, fn ($a, $b) => strcmp(mb_convert_encoding((string) $a, 'UTF-16BE', 'UTF-8'), mb_convert_encoding((string) $b, 'UTF-16BE', 'UTF-8')));
            $parts = [];
            foreach ($members as $key => $child) {
                $parts[] = self::serializeCanonical((string) $key).':'.self::serializeCanonical($child);
            }

            return '{'.implode(',', $parts).'}';
        }
        if (is_array($value)) {
            self::ensure(array_is_list($value), 'JSON_VALUE', 'Use stdClass for JSON objects');

            return '['.implode(',', array_map(self::serializeCanonical(...), $value)).']';
        }
        if (is_float($value)) {
            if ($value == 0) {
                return '0';
            }
            $previous = ini_set('serialize_precision', '-1');
            try {
                $text = json_encode($value, JSON_THROW_ON_ERROR);
            } finally {
                ini_set('serialize_precision', $previous);
            }
            $text = strtolower($text);
            if (! str_contains($text, 'e')) {
                return str_ends_with($text, '.0') ? substr($text, 0, -2) : $text;
            }
            [$mantissa, $exponent] = explode('e', $text);
            $exponent = (int) $exponent;
            $negative = str_starts_with($mantissa, '-');
            $unsigned = ltrim($mantissa, '-');
            $digits = str_replace('.', '', $unsigned);
            $digits = rtrim($digits, '0');
            $point = 1 + $exponent;
            if (abs($value) >= 1e-6 && abs($value) < 1e21) {
                if ($point <= 0) {
                    $formatted = '0.'.str_repeat('0', -$point).$digits;
                } elseif ($point >= strlen($digits)) {
                    $formatted = $digits.str_repeat('0', $point - strlen($digits));
                } else {
                    $formatted = substr($digits, 0, $point).'.'.substr($digits, $point);
                }

                return ($negative ? '-' : '').$formatted;
            }

            return ($negative ? '-' : '').$digits[0].(strlen($digits) > 1 ? '.'.substr($digits, 1) : '').'e'.($exponent >= 0 ? '+' : '').$exponent;
        }

        return json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS);
    }

    public static function parse(string $raw): mixed
    {
        try {
            $value = json_decode($raw, false, 512, JSON_THROW_ON_ERROR);
            self::jsonSafety($value);
        } catch (\JsonException $e) {
            throw new PactError('JSON_SYNTAX', 'Invalid JSON or Unicode');
        }
        $stack = [];
        preg_match_all('/"(?:[^"\\\\]|\\\\.)*"|[{}\[\]:,]/s', $raw, $tokens);
        foreach ($tokens[0] as $token) {
            if ($token === '{' || $token === '[') {
                $stack[] = ['object' => $token === '{', 'keys' => [], 'key' => $token === '{'];
            } elseif ($token === '}' || $token === ']') {
                array_pop($stack);
            } elseif ($token === ',' && count($stack) && $stack[array_key_last($stack)]['object']) {
                $stack[array_key_last($stack)]['key'] = true;
            } elseif (str_starts_with($token, '"') && count($stack) && $stack[array_key_last($stack)]['object'] && $stack[array_key_last($stack)]['key']) {
                $index = array_key_last($stack);
                $key = json_decode($token, false, 512, JSON_THROW_ON_ERROR);
                self::ensure(! isset($stack[$index]['keys'][$key]), 'JSON_DUPLICATE', 'Duplicate JSON object member');
                $stack[$index]['keys'][$key] = true;
                $stack[$index]['key'] = false;
            }
        }

        return $value;
    }

    public static function eventDigest(stdClass $event): string
    {
        $body = clone $event;
        unset($body->sha256);

        return hash('sha256', self::canonical($body));
    }

    public static function signEvent(stdClass $event): stdClass
    {
        $result = clone $event;
        $result->sha256 = self::eventDigest($event);

        return $result;
    }

    private static function roundFraction(string $numerator, string $denominator): float
    {
        return (float) bcdiv(bcadd(bcmul($numerator, '2'), $denominator), bcmul($denominator, '2'), 0) / 100;
    }

    private static function basisPoints(int|float $value): string
    {
        self::ensure($value >= 0 && $value <= 100 && (float) round($value, 2) === (float) $value, 'PROGRESS_PRECISION', 'Percentage precision');

        return (string) (int) round($value * 100);
    }

    public function progress(stdClass $value, ?int $previousSequence = null): stdClass
    {
        $this->shape('progress', $value);
        if ($previousSequence !== null) {
            self::ensure($value->sequence > $previousSequence, 'PROGRESS_SEQUENCE', 'Sequence must increase');
        }
        if ($value->total_units !== null) {
            self::ensure($value->completed_units <= $value->total_units, 'PROGRESS_UNITS', 'Completed units exceed total');
            $expected = self::roundFraction(bcmul((string) $value->completed_units, '10000'), (string) $value->total_units);
            self::ensure((float) $value->percentage === $expected, 'PROGRESS_PERCENTAGE', 'Incorrect percentage');
        }

        return $value;
    }

    public function event(stdClass $value, bool $swarmActive = false): stdClass
    {
        $this->shape('event', $value);
        self::ensure(hash_equals(self::eventDigest($value), $value->sha256), 'EVENT_DIGEST', 'Event digest mismatch');
        if ($value->type === 'task.progress') {
            $this->progress($value->payload);
            self::ensure($value->sequence === $value->payload->sequence, 'EVENT_SEQUENCE', 'Event/progress sequence mismatch');
        }
        if (str_starts_with($value->type, 'question.')) {
            self::ensure($value->payload->task_id === $value->task_id, 'QUESTION_CORRELATION', 'Question task differs');
            self::ensure(($value->type === 'question.opened') === ($value->payload->status === 'open'), 'QUESTION_STATUS', 'Question status differs');
        }
        if ($value->type === 'swarm.progress') {
            $this->swarmSnapshot($value->payload);
        }
        if (in_array($value->type, ['swarm.progress', 'child.status', 'cancellation.requested', 'cancellation.observed'], true)) {
            self::ensure($swarmActive, 'SWARM_REQUIRED', 'Swarm activation required');
        }

        return $value;
    }

    public function contractData(stdClass $descriptor, mixed $data, string $registryPath): void
    {
        $this->shape('schema-reference', $descriptor);
        $registry = json_decode(file_get_contents($registryPath), false, 512, JSON_THROW_ON_ERROR);
        $mapping = $registry->{$descriptor->schema_uri} ?? null;
        self::ensure(is_string($mapping) && ! str_starts_with($mapping, '/'), 'SCHEMA_URI', 'URI not registered locally');
        $base = realpath(dirname($registryPath));
        $path = realpath($base.'/'.$mapping);
        self::ensure($path !== false && str_starts_with($path, $base.DIRECTORY_SEPARATOR), 'SCHEMA_PATH', 'Registry path escaped');
        self::ensure(filesize($path) <= 1048576, 'SCHEMA_SIZE', 'Schema exceeds 1 MiB');
        $bytes = file_get_contents($path);
        self::ensure(hash_equals($descriptor->sha256, hash('sha256', $bytes)), 'SCHEMA_DIGEST', 'Schema bytes differ');
        $schema = json_decode($bytes, false, 512, JSON_THROW_ON_ERROR);
        self::ensure(($schema->{'$id'} ?? null) === $descriptor->schema_uri, 'SCHEMA_ID', 'Schema ID differs');
        self::ensure(($schema->{'$schema'} ?? null) === 'https://json-schema.org/draft/2020-12/schema', 'SCHEMA_DIALECT', 'Unsupported dialect');
        $checkReferences = function (mixed $node) use (&$checkReferences): void {
            if (! is_object($node) && ! is_array($node)) {
                return;
            }foreach ($node as $key => $value) {
                if ($key === '$ref' || $key === '$dynamicRef') {
                    self::ensure(is_string($value) && str_starts_with($value, '#'), 'SCHEMA_REF', 'External references are not allowed');
                } else {
                    $checkReferences($value);
                }
            }
        };
        $checkReferences($schema);
        $result = (new CompliantValidator)->validate($data, $schema);
        if (! $result->isValid()) {
            throw new PactError('CONTRACT_INVALID', 'Data violates declared contract');
        }
    }

    public function answers(array $questions, array $answers, stdClass $context): array
    {
        self::ensure($context->contextId === $context->expectedContextId && $context->contextId !== '', 'QUESTION_CONTEXT', 'Context differs');
        $now = (float) (new \DateTimeImmutable($context->now))->format('U.u');
        self::ensure(is_finite($now), 'QUESTION_TIME', 'Invalid clock');
        $ids = array_map(fn ($q) => $q->question_id, $questions);
        self::ensure(count(array_unique($ids)) === count($ids), 'QUESTION_DUPLICATE', 'Duplicate question IDs');
        $answerIds = array_map(fn ($a) => $a->response_to, $answers);
        self::ensure(count($answers) > 0 && count(array_unique($answerIds)) === count($answers), 'ANSWER_DUPLICATE', 'Duplicate answer IDs');
        $result = json_decode(json_encode($questions, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        foreach ($questions as $question) {
            $this->shape('question', $question);
        }
        foreach ($answers as $answer) {
            $this->shape('answer', $answer);
            $index = array_search($answer->response_to, $ids, true);
            self::ensure($index !== false && $result[$index]->task_id === $context->taskId && $answer->task_id === $context->taskId, 'QUESTION_CORRELATION', 'Question/task differs');
            $question = $result[$index];
            self::ensure($question->status === 'open', 'QUESTION_CLOSED', 'Question is closed');
            self::ensure($question->expires_at === null || $now < (float) (new \DateTimeImmutable($question->expires_at))->format('U.u'), 'QUESTION_EXPIRED', 'Deadline passed');
            $this->contractData($question->answer_schema, $answer->value, $context->registryPath);
            $question->status = 'answered';
        }

        return $result;
    }

    public static function isTerminal(string $state): bool
    {
        return in_array($state, ['TASK_STATE_COMPLETED', 'TASK_STATE_FAILED', 'TASK_STATE_CANCELED', 'TASK_STATE_REJECTED'], true);
    }

    public function transition(string $previous, string $next, bool $outputValidated = false, array $questions = []): string
    {
        $transitions = [
            'UNSPECIFIED' => ['SUBMITTED', 'REJECTED'],
            'SUBMITTED' => ['WORKING', 'INPUT_REQUIRED', 'AUTH_REQUIRED', 'FAILED', 'CANCELED', 'REJECTED'],
            'WORKING' => ['INPUT_REQUIRED', 'AUTH_REQUIRED', 'COMPLETED', 'FAILED', 'CANCELED', 'REJECTED'],
            'INPUT_REQUIRED' => ['WORKING', 'AUTH_REQUIRED', 'FAILED', 'CANCELED', 'REJECTED'],
            'AUTH_REQUIRED' => ['WORKING', 'INPUT_REQUIRED', 'FAILED', 'CANCELED', 'REJECTED'],
            'COMPLETED' => [], 'FAILED' => [], 'CANCELED' => [], 'REJECTED' => [],
        ];
        $from = str_replace('TASK_STATE_', '', $previous);
        $to = str_replace('TASK_STATE_', '', $next);
        self::ensure(str_starts_with($previous, 'TASK_STATE_') && str_starts_with($next, 'TASK_STATE_') && isset($transitions[$from], $transitions[$to]), 'TASK_STATE', 'Unknown state');
        self::ensure($from === $to || in_array($to, $transitions[$from], true), self::isTerminal($previous) ? 'TASK_TERMINAL' : 'TASK_TRANSITION', 'Invalid transition');
        if ($to === 'COMPLETED') {
            self::ensure($outputValidated, 'OUTPUT_REQUIRED', 'Validated output required');
            foreach ($questions as $question) {
                self::ensure(! $question->required || ! in_array($question->status, ['open', 'expired'], true), 'QUESTION_REQUIRED', 'Unresolved required question');
            }
        }

        return $next;
    }

    public function negotiate(?stdClass $params, stdClass $request): array
    {
        if ($params === null || ! in_array(self::CORE_URI, $request->active, true)) {
            self::ensure(($request->allowDowngrade ?? false) === true, 'PACT_UNSUPPORTED', 'Core activation missing');

            return ['mode' => 'a2a'];
        }
        $this->shape('extension-params', $params);
        $this->shape('contract', $request->contract);
        self::ensure(in_array($request->version, $params->versions, true), 'PACT_VERSION', 'Unsupported version');
        self::ensure(count(array_diff(['contracts', 'questions', 'events', 'progress'], $params->features)) === 0, 'PACT_FEATURES', 'Missing Core features');
        $contract = null;
        foreach ($params->contracts as $candidate) {
            if ($candidate->contract_id === $request->contract->contract_id && $candidate->version === $request->contract->version) {
                $contract = $candidate;
            }
        }
        self::ensure($contract !== null, 'CONTRACT_UNSUPPORTED', 'Contract not advertised');
        self::ensure(self::canonical($contract) === self::canonical($request->contract), 'CONTRACT_MISMATCH', 'Contract differs');
        if ($request->swarm ?? false) {
            self::ensure(in_array(self::SWARM_URI, $request->active, true) && in_array('swarm', $params->features, true), 'SWARM_REQUIRED', 'Swarm not negotiated');
        }

        return ['mode' => 'pact', 'extensions' => ($request->swarm ?? false) ? [self::CORE_URI, self::SWARM_URI] : [self::CORE_URI]];
    }

    public function graph(stdClass $value): array
    {
        $this->shape('graph', $value);
        $children = [];
        foreach ($value->children as $c) {
            self::ensure(! isset($children[$c->child_id]), 'DAG_DUPLICATE', 'Duplicate child');
            $children[$c->child_id] = $c;
        }
        $visiting = [];
        $visited = [];
        $order = [];
        $visit = function (string $id) use (&$visit, $children, &$visiting, &$visited, &$order): void {
            self::ensure(isset($children[$id]), 'DAG_DEPENDENCY', 'Unknown dependency');
            self::ensure(! isset($visiting[$id]), 'DAG_CYCLE', 'Cycle detected');
            if (isset($visited[$id])) {
                return;
            }
            $visiting[$id] = true;
            foreach ($children[$id]->depends_on as $dep) {
                $visit($dep);
            }
            unset($visiting[$id]);
            $visited[$id] = true;
            $order[] = $id;
        };
        foreach ($children as $id => $child) {
            $visit((string) $id);
        }
        $policy = $value->aggregation_policy;
        if ($policy->type === 'all_required') {
            self::ensure(count(array_filter($value->children, fn ($c) => $c->required)) > 0, 'AGGREGATION_REQUIRED', 'No required child');
        } else {
            self::ensure(count(array_filter($policy->eligible_child_ids, fn ($id) => ! isset($children[$id]))) === 0 && $policy->min_success <= count($policy->eligible_child_ids), 'QUORUM_ELIGIBILITY', 'Impossible quorum');
        }

        return $order;
    }

    public function swarmSnapshot(stdClass $snapshot): stdClass
    {
        $this->shape('swarm-progress', $snapshot);
        $input = clone $snapshot;
        unset($input->percentage,$input->execution_percentage,$input->high_watermark);
        $input->previous_high_watermark = $snapshot->high_watermark;
        self::ensure(self::canonical($this->swarmProgress($input)) === self::canonical($snapshot), 'SWARM_PERCENTAGE', 'Invalid weighted snapshot');

        return $snapshot;
    }

    public function swarmProgress(stdClass $input): stdClass
    {
        $this->shape('swarm-progress-input', $input);
        foreach ($input->children as $child) {
            if ($child->percentage !== null) {
                self::basisPoints($child->percentage);
            }
        }
        foreach ($input->stages as $stage) {
            if ($stage->percentage !== null) {
                self::basisPoints($stage->percentage);
            }
        }
        if ($input->previous_high_watermark !== null) {
            self::basisPoints($input->previous_high_watermark);
        }
        $children = [];
        foreach ($input->children as $child) {
            self::ensure(! isset($children[$child->child_id]), 'DAG_DUPLICATE', 'Duplicate child IDs');
            $children[$child->child_id] = $child;
        }
        foreach ($input->scope_child_ids as $id) {
            self::ensure(isset($children[$id]), 'QUORUM_ELIGIBILITY', 'Unknown scoped child');
        }
        $stageIds = array_map(fn ($s) => $s->id, $input->stages);
        self::ensure(count(array_unique($stageIds)) === count($stageIds) && count(array_filter($stageIds, fn ($id) => $id === 'execution')) === 1, 'PROGRESS_STAGES', 'Unique stages and execution stage required');
        $executionNumerator = '0';
        $executionDenominator = '0';
        $unknown = false;
        foreach ($input->scope_child_ids as $id) {
            $child = $children[$id];
            self::ensure(! $child->settled || self::isTerminal($child->state), 'PROGRESS_SETTLED', 'Only terminal children may settle');
            $percentage = $child->settled ? 100 : $child->percentage;
            if ($percentage === null) {
                $unknown = true;
            } else {
                $executionNumerator = bcadd($executionNumerator, bcmul(self::basisPoints($percentage), (string) $child->weight));
            }
            $executionDenominator = bcadd($executionDenominator, (string) $child->weight);
        }
        $executionPercentage = $unknown ? null : self::roundFraction($executionNumerator, $executionDenominator);
        $numerator = '0';
        $weight = '0';
        $stages = [];
        foreach ($input->stages as $stage) {
            $weight = bcadd($weight, (string) $stage->weight);
            $output = clone $stage;
            if ($stage->id === 'execution') {
                $numerator = bcadd($numerator, bcmul($executionNumerator, (string) $stage->weight));
                $output->percentage = $executionPercentage;
            } elseif ($stage->percentage === null) {
                $unknown = true;
            } else {
                $numerator = bcadd($numerator, bcmul(bcmul(self::basisPoints($stage->percentage), (string) $stage->weight), $executionDenominator));
            }
            $stages[] = $output;
        }
        $percentage = $unknown ? null : self::roundFraction($numerator, bcmul($executionDenominator, $weight));

        return (object) ['generation' => $input->generation, 'sequence' => $input->sequence, 'scope_child_ids' => $input->scope_child_ids, 'children' => $input->children, 'stages' => $stages,
            'percentage' => $percentage, 'execution_percentage' => $executionPercentage, 'high_watermark' => $percentage === null ? $input->previous_high_watermark : max($percentage, $input->previous_high_watermark ?? 0), 'updated_at' => $input->updated_at];
    }
}

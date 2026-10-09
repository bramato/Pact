import { isDeepStrictEqual } from 'node:util';
import { assertLifecycle, evaluateSwarm, expireQuestion, negotiate, validateAnswer } from './validation.mjs';
import { EventJournal, IdempotencyStore } from './reliability.mjs';

export function runScenario(scenario, registryPath) {
  let model = scenario.kind === 'event' ? new EventJournal() : new IdempotencyStore();
  const results = [];
  for (const [index, step] of scenario.steps.entries()) {
    let actual;
    try {
      if (step.action === 'restart') {
        const snapshot = JSON.parse(JSON.stringify(model.snapshot()));
        model = scenario.kind === 'event' ? new EventJournal(snapshot) : new IdempotencyStore(snapshot);
        actual = { restored: true };
      } else if (scenario.kind === 'event') {
        const { event, raw, ...scope } = step.input;
        actual = model.accept({ ...scope, raw: raw ?? JSON.stringify(event) });
      } else if (scenario.kind === 'idempotency') {
        const { taskId, ...input } = step.input;
        actual = model.accept({ ...input, createTask: () => taskId });
      } else if (scenario.kind === 'lifecycle') {
        const { previous, next, ...options } = step.input;
        actual = { state: assertLifecycle(previous, next, options) };
      } else if (scenario.kind === 'question') {
        const { question, answer, ...context } = step.input;
        actual = { status: validateAnswer(question, answer, { ...context, registryPath }).status };
      } else if (scenario.kind === 'expiry') {
        const { question, now } = step.input;
        const result = expireQuestion(question, now);
        actual = { status: result.question.status, taskState: result.taskState };
      } else if (scenario.kind === 'swarm') {
        actual = evaluateSwarm(step.input);
      } else if (scenario.kind === 'negotiation') {
        const { params, ...request } = step.input;
        actual = negotiate(params, request);
      } else {
        throw new Error(`Unknown scenario kind: ${scenario.kind}`);
      }
    } catch (error) {
      actual = { error: error.code ?? error.name };
    }
    results.push({ id: `${scenario.id}/${index + 1}`, passed: isDeepStrictEqual(actual, step.expected), expected: step.expected, actual });
  }
  return results;
}

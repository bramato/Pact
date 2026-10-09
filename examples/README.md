# An offline two-agent conversation

These files show the JSON-RPC binding of A2A 1.0, reviewed against upstream source release v1.0.1. They are complete illustrative envelopes rather than live endpoints. All URLs under `example.org` are placeholders. The local harness resolves the contract URLs through [schema-registry.json](schema-registry.json) and never makes a network request.

1. Read [agent-card.json](a2a/agent-card.json). The executor advertises PACT in `capabilities.extensions`, its supported version/contracts in `params`, a JSON-RPC interface, and bearer authentication. Actual credentials belong to the authenticated request, not the card.
2. Verify [contract.json](contract.json). Input, output, and question-answer descriptors pin the exact bytes of the local [contract schemas](contracts/document-review/1.0.0/input.schema.json). The caller verifies the advertised version and descriptors before submission.
3. Send [send-message.json](a2a/send-message.json) to the advertised interface with the headers below. Its structured Part carries [input.json](input.json), and the negotiated URI key carries PACT contract, correlation, and idempotency metadata. `returnImmediately: true` permits an in-progress task response.
4. Observe [working-task.json](a2a/working-task.json). The response wraps a native Task inside `result.task`. Its A2A state is `TASK_STATE_WORKING`; its PACT progress is separately measured at 35%.
5. Receive [question-status.json](a2a/question-status.json) as the status-update object inside a native streaming response's `statusUpdate` member. It sets `TASK_STATE_INPUT_REQUIRED` and identifies a required audience question with a pinned answer schema and deadline.
6. Send [answer-message.json](a2a/answer-message.json) before the deadline. It uses the same task/context and `response_to` question ID. Its structured Part contains the answer value. The fixture answer time is `2026-10-09T08:32:00Z`.
7. Observe [completed-task.json](a2a/completed-task.json). A validated structured output artifact accompanies `TASK_STATE_COMPLETED`. The artifact contract agrees with the negotiated descriptor.

Each HTTP request declares:

```http
Content-Type: application/json
A2A-Version: 1.0
A2A-Extensions: https://example.org/pact/v1
```

The executor should echo active extensions in its response. The caller checks activation; the message's `extensions` member by itself does not negotiate support. Authentication uses the Agent Card's advertised mechanism. There is no real token, endpoint, or online demo in these fixtures.

## Try individual resources

```sh
npm run validate -- contract examples/contract.json
npm run validate -- progress examples/progress.json
npm run validate -- question examples/question.json
npm run validate -- answer examples/answer.json
npm run validate -- event examples/event.json
npm run validate -- swarm examples/swarm.json
```

Standalone question and answer validation checks resource shape. Their correlation, expiry, and answer schema are checked together in the behavior scenarios. Contract shape validation is likewise separate from resolving the exact schema bytes and validating its data. `npm run check` verifies all three local contract digests and the complete example conversation.

## Swarm

[swarm.json](swarm.json) describes a parent task with three children: extraction succeeds; required review succeeds after extraction; optional style review fails after extraction. `all_required` accepts the two valid required outputs, and the parent output has a positive validation verdict. Attempt history and artifact IDs retain the optional failure as provenance.

Validation verdicts in these local fixtures are test inputs. A production coordinator must compute them by independently validating actual artifacts, check authorized delegation, and persist the audit record. It must not trust a child's claim that its own output is valid.

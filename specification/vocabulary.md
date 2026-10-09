# Vocabulary

| Term | Meaning |
| --- | --- |
| Originator | Role dispatching a task to an executor |
| Executor | Role performing the task and producing questions/results |
| Coordinator | One accountable owner of a parent task and its child DAG |
| Contract | Immutable ID/version with URI and exact-byte digest for input/output schemas |
| Question | Identified, schema-constrained request for additional input |
| Progress | Measured work, independent of task lifecycle |
| Event | Identified producer notification processed at least once |
| Child | Delegated task with dependencies and provenance |
| Artifact | Native A2A task output validated against the output contract |

`Task.id`, `Message.taskId`, and `contextId` are authoritative A2A identifiers. PACT's `task_id` mirrors a task reference inside its own resource; it must match the surrounding task where present. Hierarchy and correlation supplement these identifiers.

Specification state names such as `WORKING` map to A2A 1.0 JSON values such as `TASK_STATE_WORKING`. `TASK_STATE_UNSPECIFIED` is neither success nor terminal. Interrupted states are `INPUT_REQUIRED` and `AUTH_REQUIRED`; completed, failed, canceled, and rejected are terminal. A PACT phase or a progress percentage never changes this meaning.

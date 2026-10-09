import { createHash } from "node:crypto";
import { readFileSync, realpathSync, statSync } from "node:fs";
import { dirname, isAbsolute, relative, resolve } from "node:path";
import { createRequire } from "node:module";
import { Ajv2020 } from "ajv/dist/2020.js";
import type { FormatsPlugin } from "ajv-formats";
import { canonicalize } from "json-canonicalize";
const addFormats = createRequire(import.meta.url)(
  "ajv-formats",
) as FormatsPlugin;

export const VERSION = "0.2.0";
export const CORE_URI =
  "https://github.com/bramato/Pact/blob/main/specification/PACT-CORE-v0.2.md";
export const SWARM_URI =
  "https://github.com/bramato/Pact/blob/main/specification/PACT-SWARM-v0.2.md";
export const SCHEMA_BASE =
  "https://github.com/bramato/Pact/blob/main/specification/0.2/schemas/";
export type Json =
  null | boolean | number | string | Json[] | { [key: string]: Json };
export type JsonObject = { [key: string]: Json };
export type State =
  `TASK_STATE_${"UNSPECIFIED" | "SUBMITTED" | "WORKING" | "INPUT_REQUIRED" | "AUTH_REQUIRED" | "COMPLETED" | "FAILED" | "CANCELED" | "REJECTED"}`;
export interface SchemaReference {
  schema_uri: string;
  sha256: string;
}
export interface Contract {
  contract_id: string;
  version: string;
  input: SchemaReference;
  output: SchemaReference;
  question_answer?: SchemaReference;
}
export interface Progress {
  sequence: number;
  stage: string;
  completed_units: number;
  total_units: number | null;
  percentage: number | null;
  updated_at: string;
}
export interface Question {
  question_id: string;
  task_id: string;
  prompt: string;
  answer_schema: SchemaReference;
  required: boolean;
  expires_at: string | null;
  status: "open" | "answered" | "expired" | "withdrawn";
}
export interface Answer {
  response_to: string;
  task_id: string;
  value: Json;
}
export interface PactEvent {
  event_id: string;
  task_id: string;
  producer_id: string;
  sequence: number;
  type: string;
  occurred_at: string;
  correlation_id: string;
  causation_id: string | null;
  payload: JsonObject;
  sha256: string;
}
export interface ExtensionParams {
  versions: string[];
  contracts: Contract[];
  features: string[];
}
export interface ContributorProgress {
  child_id: string;
  weight: number;
  attempt: number;
  state: State;
  settled: boolean;
  percentage: number | null;
}
export interface StageProgress {
  id: string;
  weight: number;
  percentage: number | null;
}
export interface SwarmProgressInput {
  generation: number;
  sequence: number;
  scope_child_ids: string[];
  children: ContributorProgress[];
  stages: StageProgress[];
  previous_high_watermark: number | null;
  updated_at: string;
}
export interface SwarmProgress {
  generation: number;
  sequence: number;
  scope_child_ids: string[];
  children: ContributorProgress[];
  stages: StageProgress[];
  percentage: number | null;
  execution_percentage: number | null;
  high_watermark: number | null;
  updated_at: string;
}
export interface A2AMessage {
  messageId: string;
  role: "ROLE_USER" | "ROLE_AGENT";
  contextId?: string;
  taskId?: string;
  parts: Array<{ data: Json; mediaType?: string }>;
  metadata?: JsonObject;
  extensions?: string[];
}
export interface A2ATask {
  id: string;
  contextId: string;
  status: { state: State; timestamp: string; message?: A2AMessage };
  metadata?: JsonObject;
  artifacts?: Array<{
    artifactId: string;
    parts: Array<{ data: Json }>;
    metadata?: JsonObject;
    extensions?: string[];
  }>;
}

export class PactError extends Error {
  constructor(
    public readonly code: string,
    message: string,
    public readonly retryable = false,
    public readonly details: unknown = undefined,
  ) {
    super(message);
    this.name = "PactError";
  }
}
export function ensure(
  condition: unknown,
  code: string,
  message: string,
): asserts condition {
  if (!condition) throw new PactError(code, message);
}
export function sha256(bytes: string | Buffer): string {
  return createHash("sha256").update(bytes).digest("hex");
}

function jsonSafety(value: unknown): void {
  if (typeof value === "number")
    ensure(
      Number.isFinite(value) &&
        (!Number.isInteger(value) || Number.isSafeInteger(value)),
      "JSON_NUMBER",
      "JSON numbers must be finite and integers safe",
    );
  else if (typeof value === "string") {
    for (const character of value) {
      const code = character.codePointAt(0)!;
      ensure(
        code < 0xd800 || code > 0xdfff,
        "JSON_UNICODE",
        "Unpaired Unicode surrogate",
      );
    }
  } else if (Array.isArray(value)) value.forEach(jsonSafety);
  else if (value && typeof value === "object") {
    ensure(
      Object.getPrototypeOf(value) === Object.prototype ||
        Object.getPrototypeOf(value) === null,
      "JSON_VALUE",
      "Use plain JSON objects",
    );
    for (const [key, child] of Object.entries(value)) {
      jsonSafety(key);
      jsonSafety(child);
    }
  } else
    ensure(
      value === null || typeof value === "boolean",
      "JSON_VALUE",
      "Unsupported JSON value",
    );
}
export function canonicalJson(value: unknown): string {
  jsonSafety(value);
  return canonicalize(value);
}
export function parseJson(raw: string): any {
  let value: unknown;
  try {
    value = JSON.parse(raw);
    jsonSafety(value);
  } catch (e) {
    if (e instanceof PactError && e.code === "JSON_NUMBER") throw e;
    throw new PactError("JSON_SYNTAX", "Invalid JSON or Unicode");
  }
  const stack: Array<{ object: boolean; keys: Set<string>; key: boolean }> = [];
  for (const token of raw.match(/"(?:[^"\\]|\\.)*"|[{}\[\]:,]/g) ?? []) {
    if (token === "{" || token === "[")
      stack.push({
        object: token === "{",
        keys: new Set(),
        key: token === "{",
      });
    else if (token === "}" || token === "]") stack.pop();
    else if (token === "," && stack.at(-1)?.object) stack.at(-1)!.key = true;
    else if (
      token.startsWith('"') &&
      stack.at(-1)?.object &&
      stack.at(-1)!.key
    ) {
      const entry = stack.at(-1)!,
        key = JSON.parse(token);
      ensure(
        !entry.keys.has(key),
        "JSON_DUPLICATE",
        "Duplicate JSON object member",
      );
      entry.keys.add(key);
      entry.key = false;
    }
  }
  return value;
}
export function eventDigest(
  event: Omit<PactEvent, "sha256"> | PactEvent,
): string {
  const { sha256: _ignored, ...body } = event as PactEvent;
  return sha256(canonicalJson(body));
}
export function signEvent(event: Omit<PactEvent, "sha256">): PactEvent {
  return { ...event, sha256: eventDigest(event) };
}

function validator(): Ajv2020 {
  const ajv = new Ajv2020({
    allErrors: true,
    strict: true,
    strictTypes: false,
  });
  addFormats(ajv);
  return ajv;
}
const terminal = new Set<State>([
  "TASK_STATE_COMPLETED",
  "TASK_STATE_FAILED",
  "TASK_STATE_CANCELED",
  "TASK_STATE_REJECTED",
]);
export function isTerminal(state: State): boolean {
  return terminal.has(state);
}
const transitions: Record<State, State[]> = {
  TASK_STATE_UNSPECIFIED: ["TASK_STATE_SUBMITTED", "TASK_STATE_REJECTED"],
  TASK_STATE_SUBMITTED: [
    "TASK_STATE_WORKING",
    "TASK_STATE_INPUT_REQUIRED",
    "TASK_STATE_AUTH_REQUIRED",
    "TASK_STATE_FAILED",
    "TASK_STATE_CANCELED",
    "TASK_STATE_REJECTED",
  ],
  TASK_STATE_WORKING: [
    "TASK_STATE_INPUT_REQUIRED",
    "TASK_STATE_AUTH_REQUIRED",
    "TASK_STATE_COMPLETED",
    "TASK_STATE_FAILED",
    "TASK_STATE_CANCELED",
    "TASK_STATE_REJECTED",
  ],
  TASK_STATE_INPUT_REQUIRED: [
    "TASK_STATE_WORKING",
    "TASK_STATE_AUTH_REQUIRED",
    "TASK_STATE_FAILED",
    "TASK_STATE_CANCELED",
    "TASK_STATE_REJECTED",
  ],
  TASK_STATE_AUTH_REQUIRED: [
    "TASK_STATE_WORKING",
    "TASK_STATE_INPUT_REQUIRED",
    "TASK_STATE_FAILED",
    "TASK_STATE_CANCELED",
    "TASK_STATE_REJECTED",
  ],
  TASK_STATE_COMPLETED: [],
  TASK_STATE_FAILED: [],
  TASK_STATE_CANCELED: [],
  TASK_STATE_REJECTED: [],
};
function roundFraction(numerator: bigint, denominator: bigint): number {
  return Number((2n * numerator + denominator) / (2n * denominator)) / 100;
}
function basisPoints(value: number): bigint {
  ensure(
    Number.isFinite(value) &&
      value >= 0 &&
      value <= 100 &&
      Math.abs(value * 100 - Math.round(value * 100)) < 1e-8,
    "PROGRESS_PRECISION",
    "Percentage must have at most two decimal places",
  );
  return BigInt(Math.round(value * 100));
}

export class Pact {
  private readonly ajv = validator();
  constructor(
    schemas: Record<string, object> = JSON.parse(
      readFileSync(
        new URL("../resources/schemas.json", import.meta.url),
        "utf8",
      ),
    ),
  ) {
    Object.values(schemas).forEach((schema) => this.ajv.addSchema(schema));
    Object.keys(schemas).forEach((name) =>
      this.ajv.getSchema(`${SCHEMA_BASE}${name}.schema.json`),
    );
  }
  shape(name: string, value: unknown): void {
    const validate = this.ajv.getSchema(`${SCHEMA_BASE}${name}.schema.json`);
    ensure(validate, "SCHEMA_UNKNOWN", `Unknown resource ${name}`);
    if (!validate(value))
      throw new PactError(
        "SCHEMA_INVALID",
        `Invalid ${name}`,
        false,
        structuredClone(validate.errors),
      );
  }
  progress(value: Progress, previousSequence?: number): Progress {
    this.shape("progress", value);
    if (previousSequence !== undefined)
      ensure(
        value.sequence > previousSequence,
        "PROGRESS_SEQUENCE",
        "Sequence must increase",
      );
    if (value.total_units !== null) {
      ensure(
        value.completed_units <= value.total_units,
        "PROGRESS_UNITS",
        "Completed units exceed total",
      );
      ensure(
        value.percentage ===
          roundFraction(
            BigInt(value.completed_units) * 10000n,
            BigInt(value.total_units),
          ),
        "PROGRESS_PERCENTAGE",
        "Incorrect percentage",
      );
    }
    return value;
  }
  event(value: PactEvent, swarmActive = false): PactEvent {
    this.shape("event", value);
    ensure(
      value.sha256 === eventDigest(value),
      "EVENT_DIGEST",
      "Event digest mismatch",
    );
    if (value.type === "task.progress") {
      this.progress(value.payload as unknown as Progress);
      ensure(
        value.sequence === value.payload.sequence,
        "EVENT_SEQUENCE",
        "Event/progress sequence mismatch",
      );
    }
    if (value.type.startsWith("question.")) {
      const question = value.payload as unknown as Question;
      ensure(
        question.task_id === value.task_id,
        "QUESTION_CORRELATION",
        "Question belongs to another task",
      );
      ensure(
        (value.type === "question.opened") === (question.status === "open"),
        "QUESTION_STATUS",
        "Question status differs from event type",
      );
    }
    if (value.type === "swarm.progress")
      this.swarmSnapshot(value.payload as unknown as SwarmProgress);
    if (
      value.type === "swarm.progress" ||
      value.type === "child.status" ||
      value.type.startsWith("cancellation.")
    ) {
      ensure(swarmActive, "SWARM_REQUIRED", "Swarm activation is required");
    }
    return value;
  }
  contractData(
    descriptor: SchemaReference,
    data: unknown,
    registryPath: string,
  ): void {
    this.shape("schema-reference", descriptor);
    const registry = JSON.parse(readFileSync(registryPath, "utf8")) as Record<
      string,
      string
    >;
    const mapping = Object.hasOwn(registry, descriptor.schema_uri)
      ? registry[descriptor.schema_uri]
      : undefined;
    ensure(
      typeof mapping === "string" && !isAbsolute(mapping),
      "SCHEMA_URI",
      "URI is not registered locally",
    );
    const base = realpathSync(dirname(registryPath)),
      path = realpathSync(resolve(base, mapping));
    const within = relative(base, path);
    ensure(
      within !== ".." && !within.startsWith("../") && !isAbsolute(within),
      "SCHEMA_PATH",
      "Registry path escaped",
    );
    ensure(
      statSync(path).size <= 1048576,
      "SCHEMA_SIZE",
      "Schema exceeds 1 MiB",
    );
    const bytes = readFileSync(path);
    ensure(
      sha256(bytes) === descriptor.sha256,
      "SCHEMA_DIGEST",
      "Exact schema bytes differ",
    );
    const schema = JSON.parse(bytes.toString("utf8"));
    ensure(
      schema.$id === descriptor.schema_uri,
      "SCHEMA_ID",
      "Schema ID differs",
    );
    ensure(
      schema.$schema === "https://json-schema.org/draft/2020-12/schema",
      "SCHEMA_DIALECT",
      "Unsupported schema dialect",
    );
    const checkReferences = (node: unknown): void => {
      if (!node || typeof node !== "object") return;
      for (const [key, value] of Object.entries(node)) {
        if (key === "$ref" || key === "$dynamicRef")
          ensure(
            typeof value === "string" && value.startsWith("#"),
            "SCHEMA_REF",
            "External references are not allowed",
          );
        else checkReferences(value);
      }
    };
    checkReferences(schema);
    const validate = validator().compile(schema);
    if (!validate(data))
      throw new PactError(
        "CONTRACT_INVALID",
        "Data violates declared contract",
        false,
        validate.errors,
      );
  }
  answers(
    questions: Question[],
    answers: Answer[],
    context: {
      taskId: string;
      contextId: string;
      expectedContextId: string;
      now: string;
      registryPath: string;
    },
  ): Question[] {
    ensure(
      context.contextId === context.expectedContextId &&
        context.contextId.length > 0,
      "QUESTION_CONTEXT",
      "Task context differs",
    );
    const now = Date.parse(context.now);
    ensure(Number.isFinite(now), "QUESTION_TIME", "Invalid clock");
    ensure(
      new Set(questions.map((q) => q.question_id)).size === questions.length,
      "QUESTION_DUPLICATE",
      "Duplicate question IDs",
    );
    ensure(
      answers.length > 0 &&
        new Set(answers.map((a) => a.response_to)).size === answers.length,
      "ANSWER_DUPLICATE",
      "Answer IDs must be distinct",
    );
    const result = structuredClone(questions);
    for (const question of questions) this.shape("question", question);
    for (const answer of answers) {
      this.shape("answer", answer);
      const question = result.find((q) => q.question_id === answer.response_to);
      ensure(
        question &&
          question.task_id === context.taskId &&
          answer.task_id === context.taskId,
        "QUESTION_CORRELATION",
        "Wrong question/task",
      );
      ensure(
        question.status === "open",
        "QUESTION_CLOSED",
        "Question is closed",
      );
      ensure(
        question.expires_at === null || now < Date.parse(question.expires_at),
        "QUESTION_EXPIRED",
        "Deadline passed",
      );
      this.contractData(
        question.answer_schema,
        answer.value,
        context.registryPath,
      );
      question.status = "answered";
    }
    return result;
  }
  transition(
    previous: State,
    next: State,
    outputValidated = false,
    questions: Question[] = [],
  ): State {
    ensure(
      Object.hasOwn(transitions, previous) && Object.hasOwn(transitions, next),
      "TASK_STATE",
      "Unknown state",
    );
    ensure(
      previous === next || transitions[previous].includes(next),
      isTerminal(previous) ? "TASK_TERMINAL" : "TASK_TRANSITION",
      "Invalid transition",
    );
    if (next === "TASK_STATE_COMPLETED") {
      ensure(
        outputValidated === true,
        "OUTPUT_REQUIRED",
        "Validated output required",
      );
      ensure(
        !questions.some(
          (q) => q.required && ["open", "expired"].includes(q.status),
        ),
        "QUESTION_REQUIRED",
        "Unresolved required question",
      );
    }
    return next;
  }
  negotiate(
    params: ExtensionParams | null,
    request: {
      version: string;
      contract: Contract;
      active: string[];
      swarm?: boolean;
      allowDowngrade?: boolean;
    },
  ): { mode: "a2a" | "pact"; extensions?: string[] } {
    if (!params || !request.active.includes(CORE_URI)) {
      ensure(
        request.allowDowngrade === true,
        "PACT_UNSUPPORTED",
        "Core activation missing",
      );
      return { mode: "a2a" };
    }
    this.shape("extension-params", params);
    this.shape("contract", request.contract);
    ensure(
      params.versions.includes(request.version),
      "PACT_VERSION",
      "Unsupported version",
    );
    ensure(
      ["contracts", "questions", "events", "progress"].every((feature) =>
        params.features.includes(feature),
      ),
      "PACT_FEATURES",
      "Missing Core feature",
    );
    const contract = params.contracts.find(
      (c) =>
        c.contract_id === request.contract.contract_id &&
        c.version === request.contract.version,
    );
    ensure(contract, "CONTRACT_UNSUPPORTED", "Contract is not advertised");
    ensure(
      canonicalJson(contract) === canonicalJson(request.contract),
      "CONTRACT_MISMATCH",
      "Exact contract descriptors differ",
    );
    if (request.swarm)
      ensure(
        request.active.includes(SWARM_URI) && params.features.includes("swarm"),
        "SWARM_REQUIRED",
        "Swarm was not negotiated",
      );
    return {
      mode: "pact",
      extensions: request.swarm ? [CORE_URI, SWARM_URI] : [CORE_URI],
    };
  }
  graph(value: {
    children: Array<{
      child_id: string;
      depends_on: string[];
      required: boolean;
      weight: number;
    }>;
    aggregation_policy: {
      type: string;
      eligible_child_ids?: string[];
      min_success?: number;
    };
  }): string[] {
    this.shape("graph", value);
    const children = new Map(value.children.map((c) => [c.child_id, c]));
    ensure(
      children.size === value.children.length,
      "DAG_DUPLICATE",
      "Duplicate child",
    );
    const visiting = new Set<string>(),
      visited = new Set<string>(),
      order: string[] = [];
    const visit = (id: string): void => {
      ensure(children.has(id), "DAG_DEPENDENCY", "Unknown dependency");
      ensure(!visiting.has(id), "DAG_CYCLE", "Cycle detected");
      if (visited.has(id)) return;
      visiting.add(id);
      children.get(id)!.depends_on.forEach(visit);
      visiting.delete(id);
      visited.add(id);
      order.push(id);
    };
    children.forEach((_, id) => visit(id));
    const policy = value.aggregation_policy;
    if (policy.type === "all_required")
      ensure(
        value.children.some((c) => c.required),
        "AGGREGATION_REQUIRED",
        "No required child",
      );
    else
      ensure(
        policy.eligible_child_ids!.every((id) => children.has(id)) &&
          policy.min_success! <= policy.eligible_child_ids!.length,
        "QUORUM_ELIGIBILITY",
        "Impossible quorum",
      );
    return order;
  }
  swarmSnapshot(snapshot: SwarmProgress): SwarmProgress {
    this.shape("swarm-progress", snapshot);
    const { percentage, execution_percentage, high_watermark, ...body } =
      snapshot;
    ensure(
      canonicalJson(
        this.swarmProgress({
          ...body,
          previous_high_watermark: high_watermark,
        }),
      ) === canonicalJson(snapshot),
      "SWARM_PERCENTAGE",
      "Invalid weighted snapshot",
    );
    return snapshot;
  }
  swarmProgress(input: SwarmProgressInput): SwarmProgress {
    this.shape("swarm-progress-input", input);
    if (input.previous_high_watermark !== null)
      basisPoints(input.previous_high_watermark);
    const children = new Map(
      input.children.map((child) => [child.child_id, child]),
    );
    ensure(
      children.size === input.children.length,
      "DAG_DUPLICATE",
      "Duplicate child IDs",
    );
    ensure(
      input.scope_child_ids.every((id) => children.has(id)),
      "QUORUM_ELIGIBILITY",
      "Unknown scoped child",
    );
    ensure(
      new Set(input.stages.map((stage) => stage.id)).size ===
        input.stages.length &&
        input.stages.filter((s) => s.id === "execution").length === 1,
      "PROGRESS_STAGES",
      "Exactly one execution stage and unique stage IDs required",
    );
    let executionNumerator = 0n,
      executionDenominator = 0n,
      unknown = false;
    for (const id of input.scope_child_ids) {
      const child = children.get(id)!;
      ensure(
        !child.settled || isTerminal(child.state),
        "PROGRESS_SETTLED",
        "Only terminal children may be settled",
      );
      const percentage = child.settled ? 100 : child.percentage;
      if (percentage === null) unknown = true;
      else executionNumerator += basisPoints(percentage) * BigInt(child.weight);
      executionDenominator += BigInt(child.weight);
    }
    const executionPercentage = unknown
      ? null
      : roundFraction(executionNumerator, executionDenominator);
    let numerator = 0n,
      totalWeight = 0n;
    for (const stage of input.stages) {
      totalWeight += BigInt(stage.weight);
      if (stage.id === "execution")
        numerator += executionNumerator * BigInt(stage.weight);
      else if (stage.percentage === null) unknown = true;
      else
        numerator +=
          basisPoints(stage.percentage) *
          BigInt(stage.weight) *
          executionDenominator;
    }
    const percentage = unknown
      ? null
      : roundFraction(numerator, executionDenominator * totalWeight);
    const highWatermark =
      percentage === null
        ? input.previous_high_watermark
        : Math.max(percentage, input.previous_high_watermark ?? 0);
    return {
      generation: input.generation,
      sequence: input.sequence,
      scope_child_ids: input.scope_child_ids,
      children: input.children,
      stages: input.stages.map((stage) =>
        stage.id === "execution"
          ? { ...stage, percentage: executionPercentage }
          : stage,
      ),
      percentage,
      execution_percentage: executionPercentage,
      high_watermark: highWatermark,
      updated_at: input.updated_at,
    };
  }
}

export class EventReceipts {
  private readonly receipts = new Map<string, string>();
  private readonly positions = new Map<string, number>();
  constructor(private readonly pact = new Pact()) {}
  accept(
    tenant: string,
    producer: string,
    raw: string,
    swarmActive = false,
  ): "accepted" | "duplicate" | "stale" {
    ensure(tenant && producer, "SECURITY_SCOPE", "Trusted scope required");
    const event = parseJson(raw) as PactEvent;
    ensure(
      event.producer_id === producer,
      "PRODUCER_IDENTITY",
      "Authenticated producer differs",
    );
    const key = canonicalJson([tenant, producer, event.event_id]),
      digest = sha256(raw),
      prior = this.receipts.get(key);
    if (prior) {
      ensure(
        prior === digest,
        "EVENT_CONFLICT",
        "Changed bytes for accepted event ID",
      );
      return "duplicate";
    }
    this.pact.event(event, swarmActive);
    const position = canonicalJson([tenant, producer, event.task_id]),
      previous = this.positions.get(position);
    this.receipts.set(key, digest);
    if (previous !== undefined && event.sequence <= previous) return "stale";
    this.positions.set(position, event.sequence);
    return "accepted";
  }
}

export { PactClient, type ClientOptions } from "./client.js";

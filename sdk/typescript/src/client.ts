import { randomUUID } from "node:crypto";
import {
  Pact,
  PactError,
  ensure,
  CORE_URI,
  SWARM_URI,
  VERSION,
  canonicalJson,
  parseJson,
  isTerminal,
  type A2AMessage,
  type A2ATask,
  type Contract,
  type JsonObject,
  type ExtensionParams,
} from "./index.js";
export interface ClientOptions {
  token: string;
  contract: Contract;
  registryPath: string;
  swarm?: boolean;
  timeoutMs?: number;
}
/** Owns no credentials beyond the supplied token. Keep prepared request bytes for retries. */
export class PactClient {
  readonly pact = new Pact();
  readonly active: string[];
  constructor(
    readonly endpoint: string,
    readonly options: ClientOptions,
  ) {
    this.active = options.swarm ? [CORE_URI, SWARM_URI] : [CORE_URI];
  }
  async discover(
    cardUrl = this.endpoint.replace(/\/a2a$/, "/.well-known/agent-card.json"),
  ): Promise<JsonObject> {
    const response = await fetch(cardUrl, {
      signal: AbortSignal.timeout(this.options.timeoutMs ?? 5000),
    });
    ensure(response.ok, "DISCOVERY_HTTP", "Discovery failed");
    const card = parseJson(await response.text()) as any;
    ensure(
      card.supportedInterfaces?.some(
        (i: any) =>
          i.protocolVersion === "1.0" &&
          i.protocolBinding === "JSONRPC" &&
          i.url === this.endpoint,
      ),
      "A2A_BINDING",
      "Card does not advertise selected A2A 1.0 binding",
    );
    const extensions = card.capabilities?.extensions ?? [],
      core = extensions.find((e: any) => e.uri === CORE_URI);
    this.pact.negotiate((core?.params as ExtensionParams) ?? null, {
      version: VERSION,
      contract: this.options.contract,
      active: this.active,
      swarm: this.options.swarm,
    });
    if (this.options.swarm)
      ensure(
        extensions.some((e: any) => e.uri === SWARM_URI),
        "SWARM_REQUIRED",
        "Card omits Swarm",
      );
    return card;
  }
  prepare(
    method: "SendMessage" | "GetTask" | "CancelTask",
    params: unknown,
    id = randomUUID(),
  ): string {
    return JSON.stringify({ jsonrpc: "2.0", id, method, params });
  }
  async raw(raw: string): Promise<any> {
    let response: Response;
    try {
      response = await fetch(this.endpoint, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          Authorization: `Bearer ${this.options.token}`,
          "A2A-Version": "1.0",
          "A2A-Extensions": this.active.join(", "),
        },
        body: raw,
        signal: AbortSignal.timeout(this.options.timeoutMs ?? 5000),
      });
    } catch {
      throw new PactError("CONNECTION", "Peer did not respond", true);
    }
    if (!response.ok)
      throw new PactError(
        `HTTP_${response.status}`,
        "Peer HTTP failure",
        [408, 429].includes(response.status) || response.status >= 500,
      );
    const echoed = (response.headers.get("A2A-Extensions") ?? "")
      .split(",")
      .map((x) => x.trim());
    ensure(
      this.active.every((x) => echoed.includes(x)),
      "ACTIVATION",
      "Peer did not activate requested extensions",
    );
    const envelope = parseJson(await response.text()) as any,
      request = parseJson(raw);
    ensure(
      envelope.jsonrpc === "2.0" &&
        envelope.id === request.id &&
        Object.hasOwn(envelope, "result") !== Object.hasOwn(envelope, "error"),
      "A2A_ENVELOPE",
      "Invalid JSON-RPC response",
    );
    if (envelope.error) {
      const detail = envelope.error.data?.[CORE_URI];
      throw new PactError(
        detail?.code ?? `A2A_${envelope.error.code}`,
        detail?.message ?? envelope.error.message,
        detail?.retryable === true,
      );
    }
    return envelope.result;
  }
  async send(message: A2AMessage): Promise<A2ATask> {
    const result = await this.raw(this.prepare("SendMessage", { message }));
    return this.task(result.task);
  }
  async get(id: string): Promise<A2ATask> {
    return this.task(await this.raw(this.prepare("GetTask", { id })), id);
  }
  async cancel(id: string): Promise<A2ATask> {
    return this.task(await this.raw(this.prepare("CancelTask", { id })), id);
  }
  task(task: A2ATask, expectedId?: string): A2ATask {
    ensure(
      task?.id &&
        task.contextId &&
        task.status?.timestamp?.endsWith("Z") &&
        (!expectedId || task.id === expectedId),
      "A2A_TASK",
      "Invalid task correlation",
    );
    this.pact.transition(
      task.status.state,
      task.status.state,
      task.status.state === "TASK_STATE_COMPLETED",
      [],
    );
    const meta = task.metadata?.[CORE_URI] as any;
    this.pact.shape("metadata", meta);
    ensure(
      meta.contract &&
        canonicalJson(meta.contract) === canonicalJson(this.options.contract),
      "CONTRACT_MISMATCH",
      "Task changed negotiated contract",
    );
    if (meta.progress) this.pact.progress(meta.progress);
    const swarmMeta = task.metadata?.[SWARM_URI] as any;
    if (this.options.swarm && swarmMeta?.progress)
      this.pact.swarmSnapshot(swarmMeta.progress);
    if (meta.event) {
      this.pact.event(meta.event, !!this.options.swarm);
      ensure(
        meta.event.task_id === task.id,
        "EVENT_CORRELATION",
        "Task event differs",
      );
    }
    if (meta.questions)
      for (const q of meta.questions) {
        this.pact.shape("question", q);
        ensure(
          q.task_id === task.id,
          "QUESTION_CORRELATION",
          "Question task differs",
        );
      }
    if (task.status.state === "TASK_STATE_COMPLETED") {
      ensure(
        (task.artifacts?.length ?? 0) > 0,
        "OUTPUT_REQUIRED",
        "Completed task has no artifact",
      );
      for (const artifact of task.artifacts!) {
        ensure(
          artifact.extensions?.includes(CORE_URI),
          "OUTPUT_REQUIRED",
          "Artifact must label Core content",
        );
        const a = artifact.metadata?.[CORE_URI] as any;
        ensure(
          a &&
            canonicalJson(a.contract) === canonicalJson(this.options.contract),
          "CONTRACT_MISMATCH",
          "Artifact contract differs",
        );
        ensure(
          artifact.parts.length === 1 &&
            Object.hasOwn(artifact.parts[0], "data"),
          "OUTPUT_REQUIRED",
          "Structured output required",
        );
        this.pact.contractData(
          this.options.contract.output,
          artifact.parts[0].data,
          this.options.registryPath,
        );
      }
      this.pact.transition(
        task.status.state,
        task.status.state,
        true,
        meta.questions ?? [],
      );
    }
    return task;
  }
  message(
    data: JsonObject,
    key = randomUUID(),
    task?: A2ATask,
    answers?: JsonObject[],
  ): A2AMessage {
    return {
      messageId: randomUUID(),
      role: "ROLE_USER",
      ...(task ? { taskId: task.id, contextId: task.contextId } : {}),
      parts: [{ data }],
      extensions: this.active,
      metadata: {
        [CORE_URI]: {
          version: VERSION,
          contract: this.options.contract as unknown as JsonObject,
          idempotency_key: key,
          ...(answers ? { answers } : {}),
        },
      },
    };
  }
}

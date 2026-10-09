import { readFileSync, writeFileSync } from "node:fs";
import {
  PactClient,
  CORE_URI,
  SWARM_URI,
} from "../../sdk/typescript/dist/index.js";
const config = JSON.parse(
  readFileSync(
    process.env.PACT_CONFIG ??
      new URL("../../storage/demo-config.json", import.meta.url),
  ),
);
const contract = JSON.parse(
    readFileSync(new URL("./contract.json", import.meta.url)),
  ),
  client = new PactClient(config.url + "/a2a", {
    token: config.tokens.find((t) => t.producer === "client").token,
    contract,
    registryPath: new URL("./schema-registry.json", import.meta.url).pathname,
    swarm: process.argv.includes("--swarm"),
  });
await client.discover();
const transcript = [];
let task = await client.send(
  client.message({
    text: "PACT connects three careful agents",
    clarify: true,
    swarm: !!client.options.swarm,
    optional_failure: !!client.options.swarm,
    retry_review: !!client.options.swarm,
  }),
);
transcript.push({ step: "clarification", task });
const answers = task.metadata[CORE_URI].questions.map((q) => ({
  response_to: q.question_id,
  task_id: task.id,
  value: { approved: true },
}));
task = await client.send(client.message({}, undefined, task, answers));
transcript.push({ step: "answers", task });
const deadline = Date.now() + 30000;
while (
  !["TASK_STATE_COMPLETED", "TASK_STATE_FAILED"].includes(task.status.state) &&
  Date.now() < deadline
) {
  await new Promise((r) => setTimeout(r, 200));
  task = await client.get(task.id);
}
if (task.status.state !== "TASK_STATE_COMPLETED")
  throw new Error("Task did not complete; run the worker in another terminal");
transcript.push({ step: "validated-result", task });
writeFileSync(
  new URL("../../storage/demo-transcript.json", import.meta.url),
  JSON.stringify(transcript, null, 2) + "\n",
);
console.log(
  JSON.stringify(
    {
      task: task.id,
      state: task.status.state,
      result: task.artifacts[0].parts[0].data,
      contributors: task.metadata[SWARM_URI]?.contributors,
    },
    null,
    2,
  ),
);

import { phpExecutable } from "./runtime.mjs";
import { spawn } from "node:child_process";
import { once } from "node:events";
import { existsSync } from "node:fs";
const php = phpExecutable();
const worker = spawn(php, ["reference/bin/worker.php", "--watch"], {
  stdio: ["ignore", "ignore", "pipe"],
});
let errors = "";
worker.stderr.on("data", (chunk) => (errors += chunk));
const stopped = once(worker, "exit");
try {
  for (const flags of [[], ["--swarm"]]) {
    const demo = spawn(process.execPath, ["examples/v0.2/demo.mjs", ...flags], {
      stdio: "inherit",
    });
    const [code] = await once(demo, "exit");
    if (code !== 0) throw new Error("Demo failed " + errors);
  }
} finally {
  worker.kill("SIGTERM");
  await stopped;
}

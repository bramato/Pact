#!/usr/bin/env node
import { readFileSync } from "node:fs";
import { runEndpoint } from "./endpoint-conformance.mjs";
const [command, endpoint, ...flags] = process.argv.slice(2);
const option = (name) => {
  const i = flags.indexOf(name);
  return i < 0 ? undefined : flags[i + 1];
};
if (command !== "test" || !endpoint) {
  console.error(
    "Usage: pact test URL --config FILE [--reference-suite] [--contract FILE --registry FILE --input FILE]",
  );
  process.exit(2);
}
try {
  const config = option("--config")
    ? JSON.parse(readFileSync(option("--config"), "utf8"))
    : null;
  const client = config?.tokens.find((t) => t.producer === "client");
  const result = await runEndpoint(endpoint, {
    token: process.env.PACT_TOKEN ?? client?.token,
    otherToken: config?.tokens.find((t) => t.producer === "other")?.token,
    referenceSuite: flags.includes("--reference-suite"),
    ...(option("--contract")
      ? { contract: JSON.parse(readFileSync(option("--contract"))) }
      : {}),
    ...(option("--registry") ? { registryPath: option("--registry") } : {}),
    ...(option("--input")
      ? { input: JSON.parse(readFileSync(option("--input"))) }
      : {}),
  });
  console.log(JSON.stringify(result, null, 2));
} catch (e) {
  console.error(
    JSON.stringify({
      failed: true,
      code: e.code ?? e.name,
      message: e.message,
    }),
  );
  process.exit(1);
}

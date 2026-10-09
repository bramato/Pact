import { phpExecutable, composerExecutable } from "./runtime.mjs";
import { spawnSync } from "node:child_process";
import {
  mkdtempSync,
  mkdirSync,
  writeFileSync,
  existsSync,
  rmSync,
  readdirSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { resolve } from "node:path";
import assert from "node:assert/strict";
const root = resolve(import.meta.dirname, ".."),
  work = mkdtempSync(resolve(tmpdir(), "pact-packages-")),
  php = phpExecutable(),
  composer = composerExecutable();
const run = (bin, args, cwd = root) => {
  const result = spawnSync(bin, args, {
    cwd,
    encoding: "utf8",
    env: process.env,
    maxBuffer: 4 * 1024 * 1024,
  });
  if (result.status !== 0)
    throw new Error(`${bin} failed: ${result.stderr}\n${result.stdout}`);
  return result.stdout;
};
try {
  run("npm", ["run", "build:sdk"]);
  run("npm", [
    "pack",
    "--workspace",
    "@pact-protocol/core",
    "--pack-destination",
    work,
  ]);
  const tar = resolve(
    work,
    readdirSync(work).find((x) => x.endsWith(".tgz")),
  );
  const js = resolve(work, "node-app");
  mkdirSync(js);
  writeFileSync(
    resolve(js, "package.json"),
    JSON.stringify({ private: true, type: "module" }),
  );
  run(
    "npm",
    ["install", tar, "--ignore-scripts", "--no-audit", "--no-fund"],
    js,
  );
  writeFileSync(
    resolve(js, "check.mjs"),
    `import {Pact,CORE_URI,PactClient} from '@pact-protocol/core';new Pact().progress({sequence:1,stage:'test',completed_units:1,total_units:2,percentage:50,updated_at:'2026-10-09T12:00:00Z'});if(!CORE_URI||!PactClient)throw new Error('Missing export');console.log('npm archive standalone import passed');`,
  );
  process.stdout.write(run(process.execPath, ["check.mjs"], js));
  const phpcore = resolve(work, "core"),
    laravel = resolve(work, "laravel");
  mkdirSync(phpcore);
  mkdirSync(laravel);
  for (const [name, directory] of [
    ["php", phpcore],
    ["laravel", laravel],
  ]) {
    run(composer, [
      "archive",
      "--working-dir",
      resolve(root, "sdk", name),
      "--format",
      "zip",
      "--dir",
      work,
      "--file",
      "pact-" + name,
    ]);
    const zip = resolve(work, "pact-" + name + ".zip");
    run("unzip", ["-q", zip, "-d", directory]);
    assert.ok(existsSync(resolve(directory, "LICENSE.md")), "Archive license");
    if (name === "php")
      assert.ok(
        existsSync(resolve(directory, "resources/schemas.json")),
        "Bundled schemas",
      );
  }
  const app = resolve(work, "php-app");
  mkdirSync(app);
  writeFileSync(
    resolve(app, "composer.json"),
    JSON.stringify({
      name: "pact-protocol/package-smoke",
      license: "MIT",
      require: {
        "pact-protocol/core": "0.2.0",
        "pact-protocol/laravel": "0.2.0",
      },
      repositories: [
        {
          type: "path",
          url: phpcore,
          options: {
            symlink: false,
            versions: { "pact-protocol/core": "0.2.0" },
          },
        },
        {
          type: "path",
          url: laravel,
          options: {
            symlink: false,
            versions: { "pact-protocol/laravel": "0.2.0" },
          },
        },
      ],
      config: { "allow-plugins": false, platform: { php: "8.2.0" } },
    }),
  );
  run(composer, ["install", "--no-interaction", "--no-progress"], app);
  writeFileSync(
    resolve(app, "check.php"),
    `<?php require 'vendor/autoload.php'; $pact=new Pact\\Pact(); $pact->progress(json_decode('{"sequence":1,"stage":"test","completed_units":1,"total_units":2,"percentage":50,"updated_at":"2026-10-09T12:00:00Z"}')); if(!class_exists(PactLaravel\\Routes::class))throw new RuntimeException('Missing Laravel adapter'); echo 'Composer archives standalone import passed', PHP_EOL;`,
  );
  process.stdout.write(run(php, ["check.php"], app));
  console.log(
    "All three package archives contain standalone code, bundled schemas and licenses. No packages were published.",
  );
} finally {
  rmSync(work, { recursive: true, force: true });
}

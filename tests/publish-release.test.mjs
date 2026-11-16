import { test } from "node:test";
import assert from "node:assert/strict";
import { execFileSync, spawnSync } from "node:child_process";
import { createHash } from "node:crypto";
import {
  mkdtempSync,
  mkdirSync,
  readFileSync,
  rmSync,
  writeFileSync,
  copyFileSync,
  chmodSync,
  readdirSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
test("publisher validates locally and refuses tampering and conflicting immutable tags", () => {
  const temp = mkdtempSync(join(tmpdir(), "pact-publish-test-"));
  try {
    const repo = join(temp, "repo"),
      bin = join(temp, "bin"),
      out = join(temp, "out"),
      log = join(temp, "gh.log");
    mkdirSync(repo);
    mkdirSync(bin);
    const tar = join(temp, "source.tar");
    writeFileSync(
      tar,
      execFileSync("git", ["archive", "HEAD"], {
        cwd: root,
        maxBuffer: 128 * 1024 * 1024,
      }),
    );
    execFileSync("tar", ["-xf", tar, "-C", repo]);
    copyFileSync(
      join(root, "tools/release.mjs"),
      join(repo, "tools/release.mjs"),
    );
    copyFileSync(join(root, "tools/publish-release.mjs"), join(repo, "tools/publish-release.mjs"));
    copyFileSync(join(root, "release.json"), join(repo, "release.json"));
    execFileSync("git", ["init", "-q"], { cwd: repo });
    execFileSync("git", ["add", "."], { cwd: repo });
    execFileSync(
      "git",
      [
        "-c",
        "user.name=Test",
        "-c",
        "user.email=test@example.com",
        "commit",
        "-qm",
        "fixture",
      ],
      { cwd: repo },
    );
    execFileSync(
      "node",
      ["tools/release.mjs", "build", "--version", "0.2.1", "--output", out],
      { cwd: repo },
    );
    const mock = join(bin, "gh");
    writeFileSync(
      mock,
      `#!/usr/bin/env node\nconst fs = require('fs'); fs.appendFileSync(process.env.GH_LOG, JSON.stringify(process.argv.slice(2))+'\\n');\nconst endpoint=process.argv[5];\nif(endpoint==='user') console.log(JSON.stringify({login:'bramato'}));\nelse if(endpoint.includes('/git/commits/')) console.log(JSON.stringify({tree:{sha:'tree'}}));\nelse if(endpoint.includes('/commits/')) console.log('{}');\nelse if(endpoint==='repos/bramato/pact-php') console.log(JSON.stringify({name:'pact-php',owner:{login:'bramato'},default_branch:'main',private:false}));\nelse if(endpoint.endsWith('/git/ref/heads/main')) console.log(JSON.stringify({object:{sha:'head'}}));\nelse if(endpoint.includes('/git/trees/')) console.log(JSON.stringify({tree:[]}));\nelse if(endpoint.includes('/git/ref/tags/')) console.log(JSON.stringify({object:{type:'commit',sha:'tagcommit'}}));\nelse { console.error('unexpected '+endpoint); process.exit(1); }\n`,
    );
    chmodSync(mock, 0o755);
    const env = {
      ...process.env,
      PATH: `${bin}:${process.env.PATH}`,
      GH_LOG: log,
    };
    const run = (...args) =>
      spawnSync(
        "node",
        [join(repo, "tools/publish-release.mjs"), "--directory", out, ...args],
        { encoding: "utf8", env },
      );
    const dry = run();
    assert.equal(dry.status, 0, dry.stderr);
    assert.equal(JSON.parse(dry.stdout).mode, "dry-run");
    assert.throws(() => readFileSync(log));
    const conflict = run("--publish");
    assert.notEqual(conflict.status, 0);
    assert.match(conflict.stderr, /Conflicting immutable tag/);
    assert.doesNotMatch(
      readFileSync(log, "utf8"),
      /POST|PATCH|release.*create/,
    );
    const core = join(out, "packages/core");
    writeFileSync(join(core, "website.html"), "unallowlisted content\n");
    const list = (dir, prefix = "") => readdirSync(join(dir, prefix), { withFileTypes: true })
      .flatMap((entry) => {
        const path = prefix ? `${prefix}/${entry.name}` : entry.name;
        return entry.isDirectory() ? list(dir, path) : [path];
      }).sort();
    const sha = (path) => createHash("sha256").update(readFileSync(path)).digest("hex");
    writeFileSync(join(core, "SHA256SUMS"), list(core).filter((p) => p !== "SHA256SUMS")
      .map((p) => `${sha(join(core, p))}  ${p}\n`).join(""));
    const manifestPath = join(out, "release-manifest.json");
    const manifest = JSON.parse(readFileSync(manifestPath));
    const archive = join(out, manifest.packages[0].archive);
    rmSync(archive);
    execFileSync("zip", ["-X", "-q", archive, "-@"], { cwd: core, input: list(core).join("\n") + "\n" });
    manifest.packages[0].sha256 = sha(archive);
    writeFileSync(manifestPath, JSON.stringify(manifest, null, 2) + "\n");
    const before = readFileSync(log, "utf8");
    const forged = run("--publish");
    assert.notEqual(forged.status, 0);
    assert.match(forged.stderr, /differs from source commit/);
    assert.equal(readFileSync(log, "utf8"), before);
    writeFileSync(join(out, "packages/core/README.md"), "tampered");
    const tampered = run();
    assert.notEqual(tampered.status, 0);
    assert.match(tampered.stderr, /checksum/i);
  } finally {
    rmSync(temp, { recursive: true, force: true });
  }
});

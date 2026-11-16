import { test } from "node:test";
import assert from "node:assert/strict";
import { execFileSync, spawnSync } from "node:child_process";
import {
  mkdtempSync,
  mkdirSync,
  readFileSync,
  rmSync,
  writeFileSync,
  copyFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";

const root = resolve(import.meta.dirname, "..");
const exec = (cmd, args, cwd, options = {}) =>
  execFileSync(cmd, args, { cwd, encoding: "utf8", ...options });

test("release uses committed sources and produces deterministic standalone Composer archives", () => {
  const temp = mkdtempSync(join(tmpdir(), "pact-release-test-"));
  try {
    const repo = join(temp, "repo");
    mkdirSync(repo);
    const tar = join(temp, "source.tar");
    writeFileSync(
      tar,
      execFileSync("git", ["archive", "HEAD"], {
        cwd: root,
        maxBuffer: 128 * 1024 * 1024,
      }),
    );
    exec("tar", ["-xf", tar, "-C", repo], root);
    copyFileSync(
      join(root, "tools/release.mjs"),
      join(repo, "tools/release.mjs"),
    );
    copyFileSync(join(root, "release.json"), join(repo, "release.json"));
    exec("git", ["init", "-q"], repo);
    exec("git", ["add", "."], repo);
    exec(
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
      repo,
    );
    const commit = exec("git", ["rev-parse", "HEAD"], repo).trim();
    const source = join(repo, "sdk/php/src/Pact.php");
    const original = readFileSync(source, "utf8");
    writeFileSync(source, original + "\nDIRTY_SENTINEL\n");
    const one = join(temp, "one"),
      two = join(temp, "two");
    const build = (dir) =>
      exec(
        "node",
        ["tools/release.mjs", "build", "--version", "0.2.1", "--output", dir],
        repo,
      );
    build(one);
    build(two);
    build(one);
    assert.equal(
      readFileSync(join(one, "packages/core/src/Pact.php"), "utf8"),
      original,
    );
    const manifest = JSON.parse(
      readFileSync(join(one, "release-manifest.json")),
    );
    assert.equal(manifest.sourceCommit, commit);
    assert.equal(manifest.protocolVersion, "0.2.0");
    for (const pkg of manifest.packages) {
      assert.deepEqual(
        readFileSync(join(one, pkg.archive)),
        readFileSync(join(two, pkg.archive)),
      );
      const list = exec("unzip", ["-Z1", join(one, pkg.archive)], repo);
      assert.match(list, /^composer\.json$/m);
      assert.match(list, /^RELEASE\.json$/m);
      assert.doesNotMatch(list, /(^|\/)(vendor|tests|composer\.lock)(\/|$)/m);
      const composer = JSON.parse(
        readFileSync(join(one, pkg.directory, "composer.json")),
      );
      assert.equal(composer.name, pkg.name);
      assert.equal(composer.version, undefined);
      assert.equal(composer.repositories, undefined);
      assert.equal(composer["require-dev"], undefined);
    }
    assert.ok(
      JSON.parse(
        readFileSync(join(one, "packages/core/resources/schemas.json")),
      ).contract,
    );
    assert.deepEqual(
      JSON.parse(
        readFileSync(
          join(one, "packages/laravel/resources/contracts/mcp-results.json"),
        ),
      ),
      JSON.parse(
        readFileSync(
          join(repo, "specification/federation/0.1/mcp-results.schema.json"),
        ),
      ),
    );
    assert.ok(
      readFileSync(
        join(one, "packages/laravel/resources/views/consent.blade.php"),
      ).length,
    );
    writeFileSync(join(one, "packages/core/README.md"), "modified");
    const conflict = spawnSync(
      "node",
      ["tools/release.mjs", "build", "--version", "0.2.1", "--output", one],
      { cwd: repo, encoding: "utf8" },
    );
    assert.notEqual(conflict.status, 0);
    assert.match(conflict.stderr, /different content/);
    const invalid = spawnSync(
      "node",
      ["tools/release.mjs", "build", "--version", "../bad", "--output", two],
      { cwd: repo, encoding: "utf8" },
    );
    assert.notEqual(invalid.status, 0);
  } finally {
    rmSync(temp, { recursive: true, force: true });
  }
});

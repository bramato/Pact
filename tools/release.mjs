#!/usr/bin/env node
import { execFileSync } from "node:child_process";
import { createHash } from "node:crypto";
import {
  cpSync,
  existsSync,
  mkdirSync,
  mkdtempSync,
  readFileSync,
  readdirSync,
  rmSync,
  statSync,
  utimesSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";

const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const run = (command, args, options = {}) =>
  execFileSync(command, args, {
    cwd: root,
    maxBuffer: 128 * 1024 * 1024,
    ...options,
  });
const sha = (path) =>
  createHash("sha256").update(readFileSync(path)).digest("hex");
const writeJson = (path, object) =>
  writeFileSync(path, JSON.stringify(object, null, 2) + "\n");
function files(dir, prefix = "") {
  return readdirSync(join(dir, prefix), { withFileTypes: true })
    .sort((a, b) => (a.name < b.name ? -1 : a.name > b.name ? 1 : 0))
    .flatMap((entry) => {
      const path = prefix ? `${prefix}/${entry.name}` : entry.name;
      if (entry.isSymbolicLink())
        throw new Error(`Symlink is not allowed: ${path}`);
      return entry.isDirectory()
        ? files(dir, path)
        : entry.isFile()
          ? [path]
          : [];
    });
}
function copyDirectory(src, dst) {
  if (!existsSync(src) || !statSync(src).isDirectory())
    throw new Error(`Missing directory: ${src}`);
  mkdirSync(dirname(dst), { recursive: true });
  cpSync(src, dst, {
    recursive: true,
    filter: (path) =>
      !/(^|\/)(vendor|tests|node_modules|\.git|storage|\.env[^/]*|composer\.lock)(\/|$)/.test(
        path,
      ),
  });
}
function build(version, output, ref) {
  if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(version))
    throw new Error("Version must be a stable semantic version (e.g. 0.2.0)");
  const commit = run("git", ["rev-parse", "--verify", `${ref}^{commit}`], {
    encoding: "utf8",
  }).trim();
  if (!/^[a-f0-9]{40,64}$/.test(commit))
    throw new Error("Could not resolve ref to a commit");
  const temp = mkdtempSync(join(tmpdir(), "pact-release-"));
  try {
    const snapshot = join(temp, "snapshot");
    const result = join(temp, "result");
    mkdirSync(snapshot);
    mkdirSync(result);
    const archive = join(temp, "source.tar");
    writeFileSync(archive, run("git", ["archive", "--format=tar", commit]));
    run("tar", ["-xf", archive, "-C", snapshot]);
    const config = JSON.parse(
      readFileSync(join(snapshot, "release.json"), "utf8"),
    );
    const protocolVersion = config.protocol_version;
    if (!/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(protocolVersion))
      throw new Error("Invalid protocol version in release.json");
    const specs = join(snapshot, "specification/0.2/schemas");
    const schemas = Object.fromEntries(
      readdirSync(specs)
        .filter((name) => name.endsWith(".schema.json"))
        .sort()
        .map((name) => [
          name.replace(/\.schema\.json$/, ""),
          JSON.parse(readFileSync(join(specs, name), "utf8")),
        ]),
    );
    const definitions = [
      {
        key: "core",
        name: "pact-protocol/core",
        path: "sdk/php",
        repository: "https://github.com/bramato/pact-php",
        dirs: ["src"],
      },
      {
        key: "laravel",
        name: "pact-protocol/laravel",
        path: "sdk/laravel",
        repository: "https://github.com/bramato/pact-laravel",
        dirs: [
          "src",
          "config",
          "database",
          "resources/views",
          "resources/contracts",
        ],
      },
    ];
    const packages = [];
    for (const pkg of definitions) {
      const configured = config.packages.find((item) => item.name === pkg.name);
      if (
        !configured ||
        configured.path !== pkg.path ||
        configured.repository !== pkg.repository
      )
        throw new Error(`Invalid release configuration for ${pkg.name}`);
      const source = join(snapshot, pkg.path),
        target = join(result, "packages", pkg.key);
      mkdirSync(target, { recursive: true });
      for (const dir of pkg.dirs)
        copyDirectory(join(source, dir), join(target, dir));
      const composer = JSON.parse(
        readFileSync(join(source, "composer.json"), "utf8"),
      );
      if (composer.name !== pkg.name)
        throw new Error(`Unexpected Composer name: ${composer.name}`);
      delete composer["require-dev"];
      delete composer["autoload-dev"];
      delete composer.repositories;
      delete composer.version;
      composer.homepage = "https://github.com/bramato/Pact";
      composer.support = {
        source: pkg.repository,
        issues: `${pkg.repository}/issues`,
        docs: "https://github.com/bramato/Pact/tree/main/docs",
      };
      composer.keywords = [
        "pact",
        "protocol",
        "php",
        ...(pkg.key === "laravel" ? ["laravel"] : []),
      ];
      writeJson(join(target, "composer.json"), composer);
      cpSync(join(snapshot, "LICENSE.md"), join(target, "LICENSE.md"));
      if (pkg.key === "core") {
        mkdirSync(join(target, "resources"), { recursive: true });
        writeJson(join(target, "resources/schemas.json"), schemas);
      } else
        cpSync(
          join(
            snapshot,
            "specification/federation/0.1/mcp-results.schema.json",
          ),
          join(target, "resources/contracts/mcp-results.json"),
        );
      writeFileSync(
        join(target, "README.md"),
        `# ${pkg.name}\n\n${composer.description}.\n\n## Installation\n\n\`\`\`bash\ncomposer require ${pkg.name}\n\`\`\`\n\n[Documentation](https://github.com/bramato/Pact/tree/main/docs) · [Source](${pkg.repository})\n`,
      );
      writeJson(join(target, "RELEASE.json"), {
        version,
        protocolVersion,
        sourceCommit: commit,
      });
      const paths = files(target);
      writeFileSync(
        join(target, "SHA256SUMS"),
        paths.map((path) => `${sha(join(target, path))}  ${path}\n`).join(""),
      );
      const date = new Date("1980-01-01T00:00:00Z");
      for (const path of files(target))
        utimesSync(join(target, path), date, date);
      const archiveName = `${pkg.name.replace("/", "-")}-${version}.zip`;
      run("zip", ["-X", "-q", join(result, archiveName), "-@"], {
        cwd: target,
        input: files(target).join("\n") + "\n",
        env: { ...process.env, TZ: "UTC" },
      });
      packages.push({
        name: pkg.name,
        repository: pkg.repository,
        directory: `packages/${pkg.key}`,
        archive: archiveName,
        sha256: sha(join(result, archiveName)),
      });
    }
    writeJson(join(result, "release-manifest.json"), {
      version,
      protocolVersion,
      sourceCommit: commit,
      packages,
    });
    const destination = resolve(output);
    if (existsSync(destination)) {
      const existing = files(destination),
        generated = files(result);
      if (
        JSON.stringify(existing) !== JSON.stringify(generated) ||
        existing.some(
          (path) => sha(join(destination, path)) !== sha(join(result, path)),
        )
      )
        throw new Error(
          `Output already exists with different content: ${destination}`,
        );
    } else {
      mkdirSync(dirname(destination), { recursive: true });
      cpSync(result, destination, { recursive: true, errorOnExist: true });
    }
    process.stdout.write(`Built ${version} from ${commit} in ${destination}\n`);
  } finally {
    rmSync(temp, { recursive: true, force: true });
  }
}
try {
  const args = process.argv.slice(2);
  if (args.shift() !== "build")
    throw new Error(
      "Usage: node tools/release.mjs build --version X.Y.Z --output DIRECTORY [--ref REF]",
    );
  const options = {};
  while (args.length) {
    const key = args.shift();
    if (
      !["--version", "--output", "--ref"].includes(key) ||
      !args.length ||
      options[key]
    )
      throw new Error(`Invalid option: ${key}`);
    options[key] = args.shift();
  }
  if (!options["--version"] || !options["--output"])
    throw new Error("--version and --output are required");
  build(options["--version"], options["--output"], options["--ref"] || "HEAD");
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}

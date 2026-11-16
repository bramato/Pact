#!/usr/bin/env node
import { spawnSync } from "node:child_process";
import { createHash } from "node:crypto";
import {
  existsSync,
  lstatSync,
  mkdtempSync,
  readFileSync,
  readdirSync,
  rmSync,
  writeFileSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath } from "node:url";
import { TextDecoder } from "node:util";

const targets = [
  {
    name: "pact-protocol/core",
    repository: "https://github.com/bramato/pact-php",
    directory: "packages/core",
    repo: "pact-php",
  },
  {
    name: "pact-protocol/laravel",
    repository: "https://github.com/bramato/pact-laravel",
    directory: "packages/laravel",
    repo: "pact-laravel",
  },
];
const root = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const hash = (algorithm, data) =>
  createHash(algorithm).update(data).digest("hex");
const blob = (data) =>
  hash("sha1", Buffer.concat([Buffer.from(`blob ${data.length}\0`), data]));
const safePath = (path) =>
  typeof path === "string" &&
  path.length > 0 &&
  !path.startsWith("/") &&
  !path.includes("\\") &&
  !/[\x00-\x1f\x7f*?\[\]]/.test(path) &&
  path
    .split("/")
    .every(
      (part) =>
        part &&
        part !== "." &&
        part !== ".." &&
        !/^\.env/i.test(part) &&
        ![".git", "node_modules", "vendor", "storage"].includes(part),
    );
function files(dir, prefix = "") {
  return readdirSync(join(dir, prefix), { withFileTypes: true })
    .sort((a, b) => a.name.localeCompare(b.name))
    .flatMap((entry) => {
      const path = prefix ? `${prefix}/${entry.name}` : entry.name;
      if (!safePath(path) || entry.isSymbolicLink())
        throw new Error(`Unsafe path: ${path}`);
      if (entry.isDirectory()) return files(dir, path);
      if (!entry.isFile()) throw new Error(`Unsupported file: ${path}`);
      return [path];
    });
}
function readJson(path) {
  return JSON.parse(readFileSync(path, "utf8"));
}
function run(command, args, input) {
  const result = spawnSync(command, args, {
    input,
    encoding: "utf8",
    maxBuffer: 128 * 1024 * 1024,
  });
  if (result.error) throw result.error;
  if (result.status !== 0)
    throw new Error(`${command} failed: ${(result.stderr || "").trim()}`);
  return result.stdout;
}
function validate(directory) {
  const dir = resolve(directory);
  if (
    !existsSync(dir) ||
    !lstatSync(dir).isDirectory() ||
    lstatSync(dir).isSymbolicLink()
  )
    throw new Error("Release directory must be a real directory");
  const all = files(dir),
    manifest = readJson(join(dir, "release-manifest.json"));
  if (
    !/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(manifest.version) ||
    !/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(
      manifest.protocolVersion,
    ) ||
    !/^[a-f0-9]{40}$/.test(manifest.sourceCommit)
  )
    throw new Error("Invalid manifest version or source commit");
  if (
    !Array.isArray(manifest.packages) ||
    manifest.packages.length !== targets.length
  )
    throw new Error("Invalid package list");
  const expected = new Set(["release-manifest.json"]);
  const packages = targets.map((target) => {
    const matches = manifest.packages.filter(
      (item) => item.name === target.name,
    );
    if (matches.length !== 1)
      throw new Error(`Missing or duplicate package: ${target.name}`);
    const pkg = matches[0],
      archive = `${target.name.replace("/", "-")}-${manifest.version}.zip`;
    if (
      pkg.repository !== target.repository ||
      pkg.directory !== target.directory ||
      pkg.archive !== archive ||
      !/^[a-f0-9]{64}$/.test(pkg.sha256)
    )
      throw new Error(`Invalid package configuration: ${target.name}`);
    expected.add(archive);
    if (hash("sha256", readFileSync(join(dir, archive))) !== pkg.sha256)
      throw new Error(`Archive checksum mismatch: ${archive}`);
    const base = join(dir, target.directory),
      paths = files(base),
      content = {};
    for (const path of paths) {
      expected.add(`${target.directory}/${path}`);
      const data = readFileSync(join(base, path));
      try {
        new TextDecoder("utf-8", { fatal: true }).decode(data);
      } catch {
        throw new Error(`Non-UTF-8 file: ${path}`);
      }
      if (data.includes(0)) throw new Error(`Binary file: ${path}`);
      content[path] = data;
    }
    const sums = content.SHA256SUMS?.toString("utf8");
    if (!sums || !sums.endsWith("\n")) throw new Error("Missing SHA256SUMS");
    const listed = new Set();
    for (const line of sums.trimEnd().split("\n")) {
      const match = /^([a-f0-9]{64})  (.+)$/.exec(line);
      if (
        !match ||
        !safePath(match[2]) ||
        match[2] === "SHA256SUMS" ||
        listed.has(match[2]) ||
        !content[match[2]] ||
        hash("sha256", content[match[2]]) !== match[1]
      )
        throw new Error(`Invalid checksum entry: ${line}`);
      listed.add(match[2]);
    }
    if (paths.some((path) => path !== "SHA256SUMS" && !listed.has(path)))
      throw new Error("Unlisted package file");
    const release = JSON.parse(content["RELEASE.json"].toString("utf8"));
    if (
      release.version !== manifest.version ||
      release.protocolVersion !== manifest.protocolVersion ||
      release.sourceCommit !== manifest.sourceCommit ||
      JSON.parse(content["composer.json"].toString("utf8")).name !== target.name
    )
      throw new Error("Package metadata mismatch");
    const zipPaths = run("unzip", ["-Z1", join(dir, archive)])
      .trimEnd()
      .split("\n");
    if (
      zipPaths.length !== paths.length ||
      new Set(zipPaths).size !== paths.length ||
      zipPaths.some((p) => !safePath(p) || !content[p])
    )
      throw new Error("Archive file list mismatch");
    // unzip -p writes exact bytes; compare as buffers, including trailing newlines.
    for (const path of paths) {
      const extracted = spawnSync("unzip", ["-p", join(dir, archive), path], {
        maxBuffer: 128 * 1024 * 1024,
      });
      if (extracted.status !== 0 || !extracted.stdout.equals(content[path]))
        throw new Error(`Archive content mismatch: ${path}`);
    }
    return { ...target, archive, base, content };
  });
  if (all.length !== expected.size || all.some((path) => !expected.has(path)))
    throw new Error("Unexpected file in release directory");
  const temp = mkdtempSync(join(tmpdir(), "pact-verify-release-"));
  try {
    const rebuilt = join(temp, "release");
    run(process.execPath, [
      join(root, "tools/release.mjs"), "build", "--version", manifest.version,
      "--output", rebuilt, "--ref", manifest.sourceCommit,
    ]);
    for (const pkg of packages) {
      const base = join(rebuilt, pkg.directory);
      const paths = files(base);
      if (
        paths.length !== Object.keys(pkg.content).length ||
        paths.some((path) => !pkg.content[path] ||
          !readFileSync(join(base, path)).equals(pkg.content[path]))
      ) throw new Error(`Package differs from source commit: ${pkg.name}`);
    }
  } finally {
    rmSync(temp, { recursive: true, force: true });
  }
  return { manifest, packages, directory: dir };
}
function api(endpoint, method = "GET", body, missing = false) {
  const result = spawnSync(
    "gh",
    [
      "api",
      "--method",
      method,
      endpoint,
      ...(body === undefined ? [] : ["--input", "-"]),
    ],
    {
      input: body === undefined ? undefined : JSON.stringify(body),
      encoding: "utf8",
      maxBuffer: 128 * 1024 * 1024,
    },
  );
  if (result.error) throw result.error;
  if (result.status !== 0) {
    if (missing && /(?:HTTP 404|\b404\b)/.test(result.stderr || ""))
      return null;
    throw new Error(
      `gh API ${endpoint} failed: ${(result.stderr || "").trim()}`,
    );
  }
  return result.stdout.trim() ? JSON.parse(result.stdout) : null;
}
function tree(repo, sha) {
  const commit = api(`repos/bramato/${repo}/git/commits/${sha}`);
  const result = api(
    `repos/bramato/${repo}/git/trees/${commit.tree.sha}?recursive=1`,
  );
  if (
    result.truncated ||
    !Array.isArray(result.tree) ||
    result.tree.some(
      (item) =>
        item.type !== "tree" &&
        (item.type !== "blob" ||
          item.mode !== "100644" ||
          !safePath(item.path)),
    )
  )
    throw new Error(`Unsafe or truncated remote tree: ${repo}`);
  return Object.fromEntries(
    result.tree
      .filter((item) => item.type === "blob")
      .map((item) => [item.path, item.sha]),
  );
}
function sameTree(remote, content) {
  const paths = Object.keys(content);
  return (
    Object.keys(remote).length === paths.length &&
    paths.every((path) => remote[path] === blob(content[path]))
  );
}
function remoteJson(repo, sha) {
  const item = api(`repos/bramato/${repo}/git/blobs/${sha}`);
  if (item.encoding !== "base64")
    throw new Error("Unexpected GitHub blob encoding");
  return JSON.parse(Buffer.from(item.content, "base64").toString("utf8"));
}
function publish(bundle) {
  const user = api("user");
  if (user.login !== "bramato")
    throw new Error("Authenticated GitHub user must be bramato");
  if (!api(`repos/bramato/Pact/commits/${bundle.manifest.sourceCommit}`))
    throw new Error("Source commit is not present in bramato/Pact");
  const output = [];
  for (const pkg of bundle.packages) {
    const repo = pkg.repo,
      prefix = `repos/bramato/${repo}`,
      tag = `v${bundle.manifest.version}`;
    let info = api(prefix, "GET", undefined, true);
    let created = false;
    if (!info) {
      run("gh", [
        "repo",
        "create",
        `bramato/${repo}`,
        "--public",
        "--add-readme",
        "--disable-wiki",
        "--description",
        `Generated Composer package for ${pkg.name}`,
      ]);
      info = api(prefix);
      created = true;
    }
    if (
      info.owner?.login !== "bramato" ||
      info.name !== repo ||
      info.default_branch !== "main" ||
      info.private
    )
      throw new Error(`Unexpected repository settings: ${repo}`);
    const head = api(`${prefix}/git/ref/heads/main`).object.sha;
    const oldTree = tree(repo, head);
    const existingTag = api(
      `${prefix}/git/ref/tags/${tag}`,
      "GET",
      undefined,
      true,
    );
    let commit;
    if (existingTag) {
      if (
        existingTag.object.type !== "commit" ||
        !sameTree(tree(repo, existingTag.object.sha), pkg.content)
      )
        throw new Error(`Conflicting immutable tag: ${tag}`);
      commit = existingTag.object.sha;
      if (head !== commit)
        throw new Error(`Main differs from existing release tag: ${repo}`);
    } else if (sameTree(oldTree, pkg.content)) {
      commit = head;
      api(`${prefix}/git/refs`, "POST", {
        ref: `refs/tags/${tag}`,
        sha: commit,
      });
    } else {
      if (!created) {
        if (!oldTree["composer.json"] || !oldTree["RELEASE.json"])
          throw new Error(`Repository is not a generated package: ${repo}`);
        const composer = remoteJson(repo, oldTree["composer.json"]),
          release = remoteJson(repo, oldTree["RELEASE.json"]);
        if (
          composer.name !== pkg.name ||
          !/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/.test(release.version) ||
          !/^[a-f0-9]{40}$/.test(release.sourceCommit)
        )
          throw new Error(`Invalid prior release metadata: ${repo}`);
        const prior = api(
          `${prefix}/git/ref/tags/v${release.version}`,
          "GET",
          undefined,
          true,
        );
        if (
          !prior ||
          prior.object.type !== "commit" ||
          prior.object.sha !== head
        )
          throw new Error(`Unreviewed changes on main: ${repo}`);
      }
      const changes = Object.entries(pkg.content).map(([path, data]) => ({
        path,
        mode: "100644",
        type: "blob",
        content: data.toString("utf8"),
      }));
      for (const path of Object.keys(oldTree))
        if (!pkg.content[path])
          changes.push({ path, mode: "100644", type: "blob", sha: null });
      const baseTree = api(`${prefix}/git/commits/${head}`).tree.sha;
      const newTree = api(`${prefix}/git/trees`, "POST", {
        base_tree: baseTree,
        tree: changes,
      });
      commit = api(`${prefix}/git/commits`, "POST", {
        message: `Release ${tag}`,
        tree: newTree.sha,
        parents: [head],
      }).sha;
      api(`${prefix}/git/refs/heads/main`, "PATCH", {
        sha: commit,
        force: false,
      });
      api(`${prefix}/git/refs`, "POST", {
        ref: `refs/tags/${tag}`,
        sha: commit,
      });
    }
    let release = api(`${prefix}/releases/tags/${tag}`, "GET", undefined, true);
    const assetPaths = [
      join(bundle.directory, pkg.archive),
      join(pkg.base, "RELEASE.json"),
      join(pkg.base, "SHA256SUMS"),
    ];
    if (!release) {
      const temp = mkdtempSync(join(tmpdir(), "pact-notes-"));
      try {
        const notes = join(temp, "notes.md");
        writeFileSync(
          notes,
          `Release ${tag}\n\nProtocol: ${bundle.manifest.protocolVersion}\nSource commit: ${bundle.manifest.sourceCommit}\n`,
        );
        run("gh", [
          "release",
          "create",
          tag,
          ...assetPaths,
          "--repo",
          `bramato/${repo}`,
          "--verify-tag",
          "--title",
          tag,
          "--notes-file",
          notes,
        ]);
      } finally {
        rmSync(temp, { recursive: true, force: true });
      }
      release = api(`${prefix}/releases/tags/${tag}`);
    }
    let assets = release.assets || [];
    if (
      release.tag_name !== tag ||
      !release.html_url ||
      assets.some(
        (asset) =>
          !assetPaths.some((path) => path.split("/").at(-1) === asset.name),
      )
    )
      throw new Error(`Unexpected release assets: ${repo}`);
    const missingAssets = [];
    for (const path of assetPaths) {
      const name = path.split("/").at(-1),
        matches = assets.filter((asset) => asset.name === name),
        digest = `sha256:${hash("sha256", readFileSync(path))}`;
      if (
        matches.length > 1 ||
        (matches.length && matches[0].digest !== digest)
      )
        throw new Error(`Mismatched release asset: ${name}`);
      if (!matches.length) missingAssets.push(path);
    }
    if (missingAssets.length) {
      run("gh", [
        "release",
        "upload",
        tag,
        ...missingAssets,
        "--repo",
        `bramato/${repo}`,
      ]);
      release = api(`${prefix}/releases/tags/${tag}`);
      assets = release.assets || [];
    }
    if (
      assets.length !== assetPaths.length ||
      assetPaths.some(
        (path) =>
          !assets.some(
            (asset) =>
              asset.name === path.split("/").at(-1) &&
              asset.digest === `sha256:${hash("sha256", readFileSync(path))}`,
          ),
      )
    )
      throw new Error(`Missing or mismatched release assets: ${repo}`);
    output.push({
      repo: `bramato/${repo}`,
      commit,
      tag,
      release_url: release.html_url,
    });
  }
  return output;
}
try {
  const args = process.argv.slice(2);
  let directory,
    doPublish = false;
  while (args.length) {
    const arg = args.shift();
    if (arg === "--directory" && !directory && args.length)
      directory = args.shift();
    else if (arg === "--publish" && !doPublish) doPublish = true;
    else throw new Error(`Invalid option: ${arg}`);
  }
  if (!directory)
    throw new Error(
      "Usage: node tools/publish-release.mjs --directory DIRECTORY [--publish]",
    );
  const bundle = validate(directory);
  const result = doPublish
    ? publish(bundle)
    : bundle.packages.map((pkg) => ({
        repo: `bramato/${pkg.repo}`,
        tag: `v${bundle.manifest.version}`,
        sourceCommit: bundle.manifest.sourceCommit,
      }));
  process.stdout.write(
    JSON.stringify(
      { mode: doPublish ? "published" : "dry-run", packages: result },
      null,
      2,
    ) + "\n",
  );
} catch (error) {
  console.error(error.message);
  process.exitCode = 1;
}

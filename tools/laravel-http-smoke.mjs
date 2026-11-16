import {
  mkdtempSync,
  mkdirSync,
  writeFileSync,
  copyFileSync,
  rmSync,
  readFileSync,
  existsSync,
} from "node:fs";
import { tmpdir } from "node:os";
import { join, resolve } from "node:path";
import { spawn, spawnSync } from "node:child_process";
import { setTimeout as delay } from "node:timers/promises";
import { phpExecutable } from "./runtime.mjs";

const root = resolve(import.meta.dirname, ".."),
  fixture = join(root, "tests/laravel-http");
const temp = mkdtempSync(join(tmpdir(), "pact-laravel-http-"));
const children = [];
const check = (condition, message) => {
  if (!condition) throw new Error(message);
};
const apps = ["master", "client1", "client2"].map((name, i) => {
  const base = join(temp, name),
    port = 28761 + i,
    url = `http://127.0.0.1:${port}`;
  for (const dir of [
    "config",
    "bootstrap/cache",
    "storage/app",
    "storage/framework/sessions",
    "storage/framework/views",
    "storage/framework/cache/data",
    "storage/logs",
    "database",
  ])
    mkdirSync(join(base, dir), { recursive: true });
  for (const file of ["bootstrap.php", "artisan", "index.php", "setup.php"])
    copyFileSync(join(fixture, file), join(base, file));
  writeFileSync(join(base, "database/db.sqlite"), "");
  writeFileSync(
    join(base, "composer.json"),
    JSON.stringify({ autoload: { "psr-4": { "App\\": "app/" } } }),
  );
  const config = {
    app: `['name'=>'PACT Smoke', 'env'=>'testing', 'debug'=>true, 'url'=>'${url}', 'key'=>'base64:${Buffer.alloc(32, i + 1).toString("base64")}', 'cipher'=>'AES-256-CBC', 'timezone'=>'UTC']`,
    auth: `['defaults'=>['guard'=>'web','passwords'=>'users'], 'guards'=>['web'=>['driver'=>'session','provider'=>'users']], 'providers'=>['users'=>['driver'=>'eloquent','model'=>FixtureUser::class]]]`,
    database: `['default'=>'sqlite','connections'=>['sqlite'=>['driver'=>'sqlite','database'=>'${base}/database/db.sqlite','prefix'=>'','foreign_key_constraints'=>true]], 'migrations'=>['table'=>'migrations','update_date_on_publish'=>true]]`,
    session: `['driver'=>'file','lifetime'=>120,'files'=>'${base}/storage/framework/sessions','cookie'=>'pact_${name}','path'=>'/','http_only'=>true,'same_site'=>'lax']`,
    cache: `['default'=>'array','stores'=>['array'=>['driver'=>'array','serialize'=>false]]]`,
    logging: `['default'=>'single','channels'=>['single'=>['driver'=>'single','path'=>'${base}/storage/logs/laravel.log','level'=>'debug']]]`,
  };
  for (const [key, value] of Object.entries(config))
    writeFileSync(
      join(base, "config", `${key}.php`),
      `<?php return ${value};\n`,
    );
  return {
    name,
    base,
    url,
    env: {
      ...process.env,
      FIXTURE_BASE: base,
      PACT_VENDOR: join(root, "sdk/laravel/vendor"),
    },
    cookies: new Map(),
  };
});
function run(app, ...args) {
  const result = spawnSync(phpExecutable(), args, {
    cwd: app.base,
    env: app.env,
    encoding: "utf8",
  });
  check(
    result.status === 0,
    `${app.name}: php ${args.join(" ")} failed\n${result.stdout}\n${result.stderr}`,
  );
  return result.stdout;
}
async function request(app, url, options = {}) {
  const headers = { ...options.headers };
  if (app.cookies.size)
    headers.cookie = [...app.cookies].map(([k, v]) => `${k}=${v}`).join("; ");
  const response = await fetch(url.startsWith("http") ? url : app.url + url, {
    ...options,
    headers,
    redirect: "manual",
  });
  for (const cookie of response.headers.getSetCookie()) {
    const [pair] = cookie.split(";");
    const index = pair.indexOf("=");
    app.cookies.set(pair.slice(0, index), pair.slice(index + 1));
  }
  return response;
}
async function expect(app, url, status, options) {
  const response = await request(app, url, options);
  const body = await response.text();
  check(
    response.status === status,
    `${app.name} ${url}: expected ${status}, got ${response.status}\n${body.slice(0, 3000)}`,
  );
  return { response, body };
}
try {
  for (const app of apps) {
    run(app, "setup.php");
    run(
      app,
      "artisan",
      "pact:install",
      `--role=${app.name === "master" ? "master" : "client"}`,
      `--url=${app.url}`,
      "--allow-local-host=127.0.0.1",
      "--no-interaction",
    );
    const child = spawn(
      phpExecutable(),
      ["-S", `127.0.0.1:${new URL(app.url).port}`, "index.php"],
      { cwd: app.base, env: app.env, stdio: "ignore" },
    );
    children.push(child);
  }
  for (const app of apps) {
    let ready = false,
      last;
    for (let i = 0; i < 50; i++) {
      try {
        await expect(app, "/login", 200);
        ready = true;
        break;
      } catch (error) {
        if (error.message.includes("expected 200")) throw error;
        last = error;
        await delay(100);
      }
    }
    check(ready, `${app.name} HTTP server did not start: ${last}`);
  }
  const [master, ...clients] = apps;
  for (const client of clients) {
    const registration = run(
      client,
      "artisan",
      "pact:oauth-client",
      `--master=${master.url}`,
    );
    const clientId = registration.match(/OAuth client ID:\s*(\S+)/)?.[1];
    check(clientId, "OAuth client ID missing");
    const application = run(
      master,
      "artisan",
      "pact:application:add",
      `--url=${client.url}`,
      `--client-id=${clientId}`,
      `--name=${client.name}`,
    );
    const applicationId = application.match(/\(([^)]+)\)/)?.[1];
    check(applicationId, "application ID missing");
    const connect = run(
      master,
      "artisan",
      "pact:connect",
      applicationId,
      "--user=1",
    );
    const authorize = connect.match(/https?:\/\/\S+/)?.[0];
    check(authorize, "authorization URL missing");
    const consent = await expect(client, authorize, 200);
    const field = (name) =>
      consent.body.match(new RegExp(`name="${name}" value="([^"]+)"`))?.[1];
    const csrf = field("_token"),
      token = field("auth_token");
    check(csrf && token, "consent fields missing");
    const approval = await expect(client, "/pact/oauth/authorize", 302, {
      method: "POST",
      headers: { "content-type": "application/x-www-form-urlencoded" },
      body: new URLSearchParams({ _token: csrf, auth_token: token }),
    });
    const callback = approval.response.headers.get("location");
    check(
      callback?.startsWith(master.url + "/pact/oauth/callback"),
      "unexpected callback",
    );
    await expect(master, callback, 202);
    run(master, "artisan", "pact:work", "--once");
  }
  const roster = JSON.parse(
    (await expect(master, "/fixture/members", 200)).body,
  );
  check(roster.length === 3, `expected 3 members: ${JSON.stringify(roster)}`);
  const member = roster.find((m) => m.kind === "agent" && m.active);
  check(member, "active client member missing");
  const sent = JSON.parse(
    (
      await expect(
        master,
        `/fixture/send/${encodeURIComponent(member.id)}`,
        200,
      )
    ).body,
  );
  run(master, "artisan", "pact:work", "--once");
  run(clients[0], "artisan", "pact:work", "--once");
  run(clients[1], "artisan", "pact:work", "--once");
  await delay(2500);
  run(master, "artisan", "pact:work", "--once");
  const task = JSON.parse(
    (await expect(master, `/fixture/task/${sent.task_id}`, 200)).body,
  );
  check(
    task.status?.state === "TASK_STATE_COMPLETED",
    `task not completed: ${JSON.stringify(task)}`,
  );
  check(
    JSON.stringify(task).includes("hello from HTTP smoke"),
    `missing diagnostic output: ${JSON.stringify(task)}`,
  );
  console.log(
    "PASS: three installed Laravel apps, real OAuth PKCE, activation, roster and completed diagnostic message",
  );
} catch (error) {
  for (const app of apps) {
    const log = join(app.base, "storage/logs/laravel.log");
    if (existsSync(log))
      console.error(readFileSync(log, "utf8").slice(0, 4000));
  }
  throw error;
} finally {
  for (const child of children) child.kill();
  await Promise.all(
    children.map(
      (child) =>
        new Promise((resolve) =>
          child.exitCode !== null ? resolve() : child.once("exit", resolve),
        ),
    ),
  );
  rmSync(temp, { recursive: true, force: true });
}

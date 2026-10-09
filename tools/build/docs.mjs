import { marked } from "marked";
import {
  cpSync,
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  statSync,
  writeFileSync,
} from "node:fs";
import { dirname, relative, resolve, extname } from "node:path";
const root = resolve(import.meta.dirname, "../.."),
  site = resolve(root, "site");
mkdirSync(site, { recursive: true });
const sources = [],
  skip = new Set([
    "node_modules",
    "vendor",
    "dist",
    "resources",
    "readme",
    ".git",
  ]);
function scan(dir) {
  for (const entry of readdirSync(resolve(root, dir), {
    withFileTypes: true,
  })) {
    if (skip.has(entry.name)) continue;
    const path = dir ? `${dir}/${entry.name}` : entry.name;
    if (entry.isDirectory()) scan(path);
    else if (entry.name.endsWith(".md")) sources.push(path);
  }
}
for (const dir of ["docs", "specification", "sdk", "conformance", "examples"])
  scan(dir);
for (const name of [
  "README.md",
  "CONTRIBUTING.md",
  "GOVERNANCE.md",
  "SECURITY.md",
  "CODE_OF_CONDUCT.md",
  "CHANGELOG.md",
])
  sources.push(name);
const nav = [
  ["README.md", "Overview"],
  ["docs/quickstart.md", "Quickstart"],
  ["specification/PACT-CORE-v0.2.md", "Core specification"],
  ["specification/PACT-SWARM-v0.2.md", "Swarm specification"],
  ["sdk/typescript/README.md", "TypeScript SDK"],
  ["sdk/php/README.md", "PHP SDK"],
  ["sdk/laravel/README.md", "Laravel adapter"],
  ["conformance/v0.2/README.md", "Conformance"],
  ["docs/implementation.md", "Durability & recovery"],
  ["docs/compatibility.md", "Compatibility"],
  ["docs/roadmap.md", "Roadmap"],
  ["docs/editorial/README.md", "Guida illustrata"],
];
const editorial = JSON.parse(
  readFileSync(resolve(root, "docs/editorial/chapters.json"), "utf8"),
);
const escape = (s) =>
  s.replace(
    /[&<>"']/g,
    (c) =>
      ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[
        c
      ],
  );
const url = (p) => "/docs/" + p.replace(/\.md$/, ".html");
function asset(path) {
  const target = resolve(site, "_assets", path);
  mkdirSync(dirname(target), { recursive: true });
  cpSync(resolve(root, path), target);
  return "/docs/_assets/" + path;
}
asset("docs/readme/logo.svg");
const directoryPages = new Set();
function link(source, value) {
  if (/^(?:[a-z]+:|\/|#)/i.test(value)) return value;
  const [part, hash] = value.split("#");
  const target = resolve(root, dirname(source), decodeURIComponent(part)),
    path = relative(root, target);
  if (path.startsWith("..") || !existsSync(target))
    throw new Error(`Invalid docs link ${source}: ${value}`);
  const suffix = hash ? "#" + hash : "";
  if (statSync(target).isDirectory()) {
    if (existsSync(resolve(target, "README.md")))
      return url(path + "/README.md") + suffix;
    directoryPages.add(path);
    return "/docs/" + path + "/index.html" + suffix;
  }
  return sources.includes(path) ? url(path) + suffix : asset(path) + suffix;
}
const layout = (title, path, body) =>
  `<!doctype html><html lang="${path.startsWith("docs/editorial/") ? "it" : "en"}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="Content-Security-Policy" content="default-src 'none'; img-src 'self'; style-src 'self'; script-src 'self'; base-uri 'none'; form-action 'none'"><title>${escape(title)} · PACT</title><link rel="stylesheet" href="/docs/docs.css"><script defer src="/docs/docs.js"></script></head><body><a class="skip" href="#content">Skip to content</a><header><a class="brand" href="/docs/"><img src="/docs/_assets/docs/readme/logo.svg" alt="" width="36" height="36">PACT <span>Developer documentation</span></a><span class="version">Draft 0.2.0</span></header><div class="shell"><aside><label for="search">Find a page</label><div class="search"><svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"></circle><path d="m21 21-4.3-4.3"></path></svg><input id="search" type="search" placeholder="Search navigation" autocomplete="off"></div><nav aria-label="Documentation">${nav.map(([p, label]) => `<a href="${url(p)}"${p === path ? ' aria-current="page"' : ""}>${label}</a>`).join("")}</nav><p class="note">Core is required.<br>Swarm is negotiated separately.</p></aside><main id="content" tabindex="-1"><div class="breadcrumb">PACT / ${escape(title)}</div><article>${body}</article><footer>Independent A2A profile · MIT · <a href="https://github.com/bramato/Pact">Source repository</a></footer></main></div></body></html>`;
for (const source of sources) {
  let html = marked.parse(readFileSync(resolve(root, source), "utf8"));
  html = html.replace(
    /\b(href|src)="([^"]+)"/g,
    (_, attr, value) => `${attr}="${escape(link(source, value))}"`,
  );
  let headingIndex = 0;
  html = html.replace(
    /<h([1-6])>(.*?)<\/h\1>/g,
    (_, level, text) =>
      `<h${level} id="${
        text
          .replace(/<[^>]+>/g, "")
          .toLowerCase()
          .replace(/[^a-z0-9]+/g, "-")
          .replace(/^-|-$/g, "") || "section-" + ++headingIndex
      }">${text}</h${level}>`,
  );
  const chapter = editorial.sections.find((entry) => entry.source === source);
  if (chapter && source !== "README.md") {
    const image = asset("docs/editorial/assets/" + chapter.asset);
    html = html.replace(
      /(<\/h1>)/,
      `$1<figure class="section-art"><img src="${image}" alt="${escape(chapter.alt)}" width="190" height="190"></figure>`,
    );
  }
  const title =
    nav.find(([p]) => p === source)?.[1] ??
    source.split("/").at(-1).replace(".md", "");
  const target = resolve(site, source.replace(/\.md$/, ".html"));
  mkdirSync(dirname(target), { recursive: true });
  writeFileSync(target, layout(title, source, html));
}
for (const path of directoryPages) {
  const entries = readdirSync(resolve(root, path), { withFileTypes: true });
  const body = `<h1>${escape(path)}</h1><ul>${entries
    .filter((e) => e.isFile())
    .map(
      (e) =>
        `<li><a href="${asset(path + "/" + e.name)}">${escape(e.name)}</a></li>`,
    )
    .join("")}</ul>`;
  const target = resolve(site, path, "index.html");
  mkdirSync(dirname(target), { recursive: true });
  writeFileSync(target, layout(path, path, body));
}
cpSync(resolve(site, "README.html"), resolve(site, "index.html"));
writeFileSync(
  resolve(site, "docs.js"),
  `document.querySelector('#search').addEventListener('input',event=>{const query=event.target.value.toLowerCase();document.querySelectorAll('nav a').forEach(link=>{link.hidden=!link.textContent.toLowerCase().includes(query);});});`,
);
writeFileSync(
  resolve(site, "docs.css"),
  `*{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;color:#1b2a35;background:#fbfcfe;font:16px/1.7 system-ui,sans-serif}a{color:#1268ae;text-underline-offset:3px}header{padding:18px 32px;border-bottom:1px solid #dbe5ed;display:flex;justify-content:space-between;gap:24px;background:white}.brand{display:flex;align-items:center;gap:12px;font-weight:800;font-size:22px;text-decoration:none;color:#162c3c}.brand span{font-size:13px;font-weight:500;color:#526a7d;margin-left:12px}.version{align-self:center;font-size:12px;padding:4px 12px;border:1px solid #bed6e8;border-radius:20px;color:#33617f}.shell{display:grid;grid-template-columns:260px minmax(0,960px);justify-content:center;gap:60px;padding:36px 32px}aside{position:sticky;top:24px;align-self:start}label,.note{font-size:13px;color:#526a7d}.search{display:flex;align-items:center;gap:8px;border:1px solid #c4d4e2;border-radius:8px;background:white;padding:8px 12px;margin:8px 0 24px}.search svg{width:18px;height:18px;flex:none;fill:none;stroke:currentColor;stroke-width:2}input{width:100%;border:0;background:none;font:inherit;font-size:14px;outline-offset:3px}nav a{display:block;text-decoration:none;color:#334f63;padding:9px 12px;margin:2px 0;border-radius:6px;font-size:14px}nav a[aria-current=page]{background:#e5f0fa;color:#074c85;font-weight:700}nav a:hover{background:#eff5fa}nav a[hidden]{display:none}.note{border-top:1px solid #dbe5ed;padding-top:24px;margin-top:28px}.breadcrumb{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#627d91;margin-bottom:28px}article{max-width:860px}h1,h2,h3{color:#142e40;line-height:1.25}h1{font-size:clamp(32px,4vw,46px);letter-spacing:-.035em}h2{margin-top:48px;font-size:26px}h3{margin-top:30px}p,li{max-width:78ch}pre{background:#122c3f;color:#ecf6ff;padding:22px;border-radius:10px;overflow:auto;font-size:13px;line-height:1.7}code{font-size:.88em;background:#eaf1f7;padding:2px 5px;border-radius:3px}pre code{background:none;padding:0}table{border-collapse:collapse;width:100%;display:block;overflow:auto;font-size:14px;margin:24px 0}td,th{padding:12px 16px;border-bottom:1px solid #dbe5ed;text-align:left}th{background:#eaf1f7}img{max-width:100%;height:auto}.section-art{float:right;width:190px;margin:0 0 20px 28px}.section-art img{width:100%;height:auto}@media(max-width:600px){.section-art{float:none;margin:16px auto;width:190px}}article>p:has(img[src$="logo.svg"]){display:none}img[src$="header.png"]{height:260px;width:auto;object-fit:contain}blockquote{margin-left:0;padding-left:20px;border-left:3px solid #5b9dce;color:#526a7d}footer{margin-top:60px;padding:24px 0;border-top:1px solid #dbe5ed;font-size:12px;color:#526a7d}.skip{position:absolute;left:-9999px}.skip:focus{left:16px;top:12px;padding:10px;background:white;z-index:2}a:focus-visible,input:focus-visible{outline:2px solid #1680c6;outline-offset:3px}@media(max-width:850px){header{padding:16px}.brand span{display:none}.shell{display:block;padding:24px 20px}aside{position:static;margin-bottom:36px}nav{display:flex;flex-wrap:wrap;gap:4px}nav a{border:1px solid #dbe5ed}.note{display:none}.breadcrumb{margin-top:24px}}@media(prefers-color-scheme:dark){body{background:#12202d;color:#dbe8f1}header{background:#172a3a}h1,h2,h3,.brand{color:#eaf5ff}a{color:#80c9ff}nav a{color:#c6d9e7}nav a[aria-current=page],nav a:hover,code,th{background:#23445a;color:#cdeaff}.search{background:#172a3a;color:#dbe8f1}.brand span,label,.note,footer,.breadcrumb{color:#a3bacb}.version{color:#9bc9e8}header,td,th,footer,.note{border-color:#345166}}`,
);
console.log(
  `Built ${sources.length} documentation pages and ${directoryPages.size} resource indexes in site/`,
);

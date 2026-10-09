import {
  cpSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  writeFileSync,
} from "node:fs";
import { resolve } from "node:path";
const root = resolve(import.meta.dirname, "../..");
const schemas = Object.fromEntries(
  readdirSync(resolve(root, "specification/0.2/schemas"))
    .filter((file) => file.endsWith(".json"))
    .map((file) => [
      file.replace(".schema.json", ""),
      JSON.parse(
        readFileSync(resolve(root, "specification/0.2/schemas", file), "utf8"),
      ),
    ]),
);
for (const language of ["typescript", "php"]) {
  const directory = resolve(root, "sdk", language, "resources");
  mkdirSync(directory, { recursive: true });
  writeFileSync(
    resolve(directory, "schemas.json"),
    JSON.stringify(schemas, null, 2) + "\n",
  );
  cpSync(
    resolve(root, "LICENSE.md"),
    resolve(root, "sdk", language, "LICENSE.md"),
  );
}

cpSync(resolve(root, "LICENSE.md"), resolve(root, "sdk/laravel/LICENSE.md"));

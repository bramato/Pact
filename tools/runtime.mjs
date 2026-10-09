import { existsSync } from "node:fs";
import { homedir } from "node:os";
import { join } from "node:path";
const executable = (name) => {
  const herd = join(homedir(), "Library/Application Support/Herd/bin", name);
  return existsSync(herd) ? herd : name;
};
export const phpExecutable = () => process.env.PACT_PHP ?? executable("php");
export const composerExecutable = () =>
  process.env.PACT_COMPOSER ?? executable("composer");

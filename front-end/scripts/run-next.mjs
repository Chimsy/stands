#!/usr/bin/env node
/**
 * Runs the Next.js CLI with the local backend's certificate authority trusted.
 *
 * Laravel Herd serves the API over HTTPS using its own CA. macOS trusts that
 * CA, but Node keeps a separate trust store, so server-side fetches to the API
 * fail with UNABLE_TO_VERIFY_LEAF_SIGNATURE. Pointing NODE_EXTRA_CA_CERTS at
 * the same CA fixes it; Node only reads that variable at startup, which is why
 * it cannot live in .env.local.
 *
 * An existing NODE_EXTRA_CA_CERTS always wins, and on machines without Herd
 * this does nothing.
 */
import { spawn } from "node:child_process";
import { existsSync } from "node:fs";
import { homedir } from "node:os";
import { join } from "node:path";

const HERD_CA = join(
  homedir(),
  "Library/Application Support/Herd/config/valet/CA/LaravelValetCASelfSigned.pem",
);

const env = { ...process.env };

if (!env.NODE_EXTRA_CA_CERTS && existsSync(HERD_CA)) {
  env.NODE_EXTRA_CA_CERTS = HERD_CA;
}

const child = spawn("next", process.argv.slice(2), {
  stdio: "inherit",
  env,
  shell: process.platform === "win32",
});

child.on("exit", (code, signal) => {
  if (signal) {
    process.kill(process.pid, signal);
    return;
  }
  process.exit(code ?? 0);
});

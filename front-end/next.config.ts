import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // The Laravel backend one level up has its own lockfile, so point Turbopack
  // at this directory instead of letting it infer the repository root.
  turbopack: { root: __dirname },
};

export default nextConfig;

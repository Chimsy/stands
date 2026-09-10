import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  // The Laravel backend one level up has its own lockfile, so point Turbopack
  // at this directory instead of letting it infer the repository root.
  turbopack: { root: __dirname },

  // Next 16 serves /_next assets in development only to origins listed here,
  // and answers 403 to everything else - which looks like a hydration failure
  // rather than a blocked request. The loopback address is listed so the app
  // works whether it is opened as localhost or as 127.0.0.1.
  allowedDevOrigins: ["127.0.0.1"],
};

export default nextConfig;

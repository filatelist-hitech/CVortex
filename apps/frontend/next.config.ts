import type { NextConfig } from "next";

const allowedDevOrigins: string[] = ["127.0.0.1", "localhost"];

const envOrigins = process.env.ALLOWED_DEV_ORIGINS;
if (envOrigins) {
  for (const origin of envOrigins.split(",")) {
    const trimmed = origin.trim();
    if (trimmed && !allowedDevOrigins.includes(trimmed)) {
      allowedDevOrigins.push(trimmed);
    }
  }
}

if (process.env.APP_URL) {
  try {
    const parsed = new URL(process.env.APP_URL);
    if (parsed.hostname && !allowedDevOrigins.includes(parsed.hostname)) {
      allowedDevOrigins.push(parsed.hostname);
    }
  } catch {
    // ignore invalid APP_URL format
  }
}

const nextConfig: NextConfig = {
  poweredByHeader: false,
  devIndicators: false,
  allowedDevOrigins,
};

export default nextConfig;

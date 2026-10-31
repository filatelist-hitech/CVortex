/**
 * URL Guard & SSRF Protection for Worker Scrapers.
 * Prevents SSRF attacks against internal networks, cloud metadata endpoints,
 * loopback, and container services.
 */

const INTERNAL_HOSTNAMES = new Set([
  "localhost",
  "internal",
  "postgres",
  "redis",
  "backend",
  "worker",
  "nginx",
  "horizon",
  "scheduler",
  "frontend",
])

/**
 * Checks whether an IPv4 address string falls into private, loopback, or metadata ranges.
 */
function isPrivateIPv4(ip: string): boolean {
  const parts = ip.split(".").map(p => parseInt(p, 10))
  if (parts.length !== 4 || parts.some(p => isNaN(p) || p < 0 || p > 255)) {
    return false
  }

  const [b0, b1] = parts

  // 127.0.0.0/8 (Loopback)
  if (b0 === 127) return true

  // 10.0.0.0/8 (Private)
  if (b0 === 10) return true

  // 172.16.0.0/12 (Private: 172.16.0.0 - 172.31.255.255)
  if (b0 === 172 && b1 >= 16 && b1 <= 31) return true

  // 192.168.0.0/16 (Private)
  if (b0 === 192 && b1 === 168) return true

  // 169.254.0.0/16 (Link-local & AWS/GCP/Azure Instance Metadata 169.254.169.254)
  if (b0 === 169 && b1 === 254) return true

  // 0.0.0.0/8 (Current network)
  if (b0 === 0) return true

  // 100.64.0.0/10 (Carrier-grade NAT)
  if (b0 === 100 && b1 >= 64 && b1 <= 127) return true

  return false
}

/**
 * Checks whether a hostname or IP address refers to private or internal infrastructure.
 */
export function isPrivateOrInternalHost(hostname: string): boolean {
  const normalized = hostname.toLowerCase().trim()

  if (INTERNAL_HOSTNAMES.has(normalized)) {
    return true
  }

  if (
    normalized.endsWith(".local") ||
    normalized.endsWith(".internal") ||
    normalized.endsWith(".lan") ||
    normalized.endsWith(".test")
  ) {
    return true
  }

  // IPv6 loopback / private
  if (
    normalized === "::1" ||
    normalized === "[::1]" ||
    normalized.startsWith("fe80:") ||
    normalized.startsWith("[fe80:") ||
    normalized.startsWith("fc00:") ||
    normalized.startsWith("fd00:")
  ) {
    return true
  }

  // IPv4 check
  if (/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(normalized)) {
    return isPrivateIPv4(normalized)
  }

  return false
}

/**
 * Validates whether a given URL is a safe public HTTP/HTTPS URL.
 * Rejects non-HTTP protocols, non-standard sensitive ports, private IPs, and metadata services.
 */
export function isSafeExternalUrl(rawUrl: string | undefined | null): boolean {
  if (!rawUrl || typeof rawUrl !== "string") {
    return false
  }

  try {
    const parsed = new URL(rawUrl)

    // Only allow http: and https:
    if (parsed.protocol !== "http:" && parsed.protocol !== "https:") {
      return false
    }

    // Reject non-standard sensitive ports (e.g. 5432, 6379, 3001, 9000, 22)
    if (parsed.port && parsed.port !== "80" && parsed.port !== "443") {
      return false
    }

    // Reject internal / private hostnames and IP addresses
    if (isPrivateOrInternalHost(parsed.hostname)) {
      return false
    }

    return true
  } catch {
    return false
  }
}

/**
 * Sanitizes an untrusted candidate source URL against SSRF threats, falling back to a trusted URL.
 */
export function sanitizeSourceUrl(untrustedUrl: string | undefined | null, fallbackUrl: string): string {
  if (isSafeExternalUrl(untrustedUrl)) {
    return untrustedUrl as string
  }
  return fallbackUrl
}

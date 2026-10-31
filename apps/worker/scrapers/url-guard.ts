/**
 * URL Guard & SSRF Protection for Worker Scrapers.
 * Prevents SSRF attacks against internal networks, cloud metadata endpoints,
 * loopback, container services, IPv6-mapped addresses, and DNS rebinding wildcard domains.
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

const INTERNAL_DOMAIN_SUFFIXES = [
  ".local",
  ".internal",
  ".lan",
  ".test",
  ".corp",
  ".home",
  ".arpa",
]

const WILDCARD_DNS_DOMAINS = [
  "nip.io",
  "sslip.io",
  "traefik.me",
  "localtest.me",
  "vcap.me",
  "fwhs.me",
]

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

  // 0.0.0.0/8 (Current network / unspecified)
  if (b0 === 0) return true

  // 100.64.0.0/10 (Carrier-grade NAT)
  if (b0 === 100 && b1 >= 64 && b1 <= 127) return true

  return false
}

/**
 * Extracts and decodes an embedded IPv4 address from IPv4-mapped IPv6 strings
 * in both dotted format (::ffff:127.0.0.1) and WHATWG-normalized hex format (::ffff:7f00:1).
 */
function decodeIpv6MappedIpv4(cleanHost: string): string | null {
  // Case 1: dotted notation like ::ffff:127.0.0.1 or 64:ff9b::127.0.0.1
  const dotMatch = cleanHost.match(/(?:^|:)(?:ffff|64:ff9b):(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/)
  if (dotMatch) {
    return dotMatch[1]
  }

  // Case 2: hex notation like ::ffff:7f00:1 or ::ffff:a9fe:a9fe or 64:ff9b::7f00:1
  const hexMatch = cleanHost.match(/(?:^|:)(?:ffff|64:ff9b):([0-9a-f]{1,4}):([0-9a-f]{1,4})$/)
  if (hexMatch) {
    const hi = parseInt(hexMatch[1], 16)
    const lo = parseInt(hexMatch[2], 16)
    const b0 = (hi >> 8) & 0xff
    const b1 = hi & 0xff
    const b2 = (lo >> 8) & 0xff
    const b3 = lo & 0xff
    return `${b0}.${b1}.${b2}.${b3}`
  }

  return null
}

/**
 * Checks whether a hostname or IP address refers to private or internal infrastructure.
 */
export function isPrivateOrInternalHost(hostname: string): boolean {
  const normalized = hostname.toLowerCase().trim()
  const cleanHost = normalized.replace(/^\[|\]$/g, "")

  if (INTERNAL_HOSTNAMES.has(cleanHost)) {
    return true
  }

  if (INTERNAL_DOMAIN_SUFFIXES.some(suffix => cleanHost.endsWith(suffix))) {
    return true
  }

  // Wildcard DNS rebinding domain detection (nip.io, sslip.io, etc.)
  if (WILDCARD_DNS_DOMAINS.some(domain => cleanHost === domain || cleanHost.endsWith("." + domain))) {
    return true
  }

  // Check for embedded IPv4 addresses in wildcard or custom domain names (e.g. 127.0.0.1.nip.io or 127-0-0-1.example.com)
  const embeddedIpv4 = cleanHost.match(/(?:^|\.)(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?:\.|$)/)
  if (embeddedIpv4 && isPrivateIPv4(embeddedIpv4[1])) {
    return true
  }
  const dashedIpv4 = cleanHost.match(/(?:^|\.)(\d{1,3})-(\d{1,3})-(\d{1,3})-(\d{1,3})(?:\.|$)/)
  if (dashedIpv4 && isPrivateIPv4(`${dashedIpv4[1]}.${dashedIpv4[2]}.${dashedIpv4[3]}.${dashedIpv4[4]}`)) {
    return true
  }

  // IPv6 loopback, unspecified, link-local, unique-local, multicast, documentation
  if (cleanHost === "::1" || cleanHost === "::") {
    return true
  }

  if (
    cleanHost.startsWith("fe80:") ||
    /^fe[89ab][0-9a-f]:/i.test(cleanHost) ||
    cleanHost.startsWith("fc") ||
    cleanHost.startsWith("fd") ||
    cleanHost.startsWith("ff") || // Multicast
    cleanHost.startsWith("2001:db8:") || // Documentation
    cleanHost.startsWith("100::") || // Discard prefix
    cleanHost.startsWith("64:ff9b:") || // Well-known NAT64 prefix
    cleanHost.startsWith("2002:") // 6to4 prefix
  ) {
    return true
  }

  // IPv6-mapped IPv4 check (e.g. [::ffff:127.0.0.1], [::ffff:7f00:1], [::ffff:a9fe:a9fe])
  const mappedIpv4 = decodeIpv6MappedIpv4(cleanHost)
  if (mappedIpv4 && isPrivateIPv4(mappedIpv4)) {
    return true
  }

  // IPv4 check
  if (/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/.test(cleanHost)) {
    return isPrivateIPv4(cleanHost)
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

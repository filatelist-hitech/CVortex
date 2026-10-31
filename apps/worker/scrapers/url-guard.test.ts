import { describe, expect, it } from "vitest"
import { isPrivateOrInternalHost, isSafeExternalUrl, sanitizeSourceUrl } from "./url-guard"

describe("url-guard SSRF protection", () => {
  it("detects loopback and private IPv4 addresses", () => {
    expect(isPrivateOrInternalHost("127.0.0.1")).toBe(true)
    expect(isPrivateOrInternalHost("127.255.255.254")).toBe(true)
    expect(isPrivateOrInternalHost("10.0.0.1")).toBe(true)
    expect(isPrivateOrInternalHost("172.16.0.1")).toBe(true)
    expect(isPrivateOrInternalHost("172.31.255.255")).toBe(true)
    expect(isPrivateOrInternalHost("192.168.1.1")).toBe(true)
    expect(isPrivateOrInternalHost("0.0.0.0")).toBe(true)
    expect(isPrivateOrInternalHost("100.64.0.1")).toBe(true)
  })

  it("detects AWS/GCP cloud metadata IP (169.254.169.254)", () => {
    expect(isPrivateOrInternalHost("169.254.169.254")).toBe(true)
    expect(isPrivateOrInternalHost("169.254.1.1")).toBe(true)
  })

  it("detects docker container hostnames and local domains", () => {
    expect(isPrivateOrInternalHost("localhost")).toBe(true)
    expect(isPrivateOrInternalHost("postgres")).toBe(true)
    expect(isPrivateOrInternalHost("redis")).toBe(true)
    expect(isPrivateOrInternalHost("backend")).toBe(true)
    expect(isPrivateOrInternalHost("worker")).toBe(true)
    expect(isPrivateOrInternalHost("myhost.local")).toBe(true)
    expect(isPrivateOrInternalHost("service.internal")).toBe(true)
    expect(isPrivateOrInternalHost("router.lan")).toBe(true)
    expect(isPrivateOrInternalHost("app.test")).toBe(true)
  })

  it("detects IPv6 loopback, unspecified, ULA, link-local and multicast", () => {
    expect(isPrivateOrInternalHost("::1")).toBe(true)
    expect(isPrivateOrInternalHost("[::1]")).toBe(true)
    expect(isPrivateOrInternalHost("::")).toBe(true)
    expect(isPrivateOrInternalHost("[::]")).toBe(true)
    expect(isPrivateOrInternalHost("[fe80::1]")).toBe(true)
    expect(isPrivateOrInternalHost("[fc00::1]")).toBe(true)
    expect(isPrivateOrInternalHost("[fd00::1]")).toBe(true)
    expect(isPrivateOrInternalHost("[ff02::1]")).toBe(true)
  })

  it("detects IPv6-mapped IPv4 addresses (dotted and WHATWG hex normalized)", () => {
    expect(isPrivateOrInternalHost("[::ffff:127.0.0.1]")).toBe(true)
    expect(isPrivateOrInternalHost("[::ffff:7f00:1]")).toBe(true)
    expect(isPrivateOrInternalHost("[::ffff:169.254.169.254]")).toBe(true)
    expect(isPrivateOrInternalHost("[::ffff:a9fe:a9fe]")).toBe(true)
    expect(isPrivateOrInternalHost("[::ffff:10.0.0.1]")).toBe(true)
    expect(isPrivateOrInternalHost("[::ffff:a00:1]")).toBe(true)
    expect(isPrivateOrInternalHost("[::ffff:192.168.1.1]")).toBe(true)
    expect(isPrivateOrInternalHost("[::ffff:c0a8:101]")).toBe(true)
    expect(isPrivateOrInternalHost("[64:ff9b::127.0.0.1]")).toBe(true)
    expect(isPrivateOrInternalHost("[64:ff9b::7f00:1]")).toBe(true)
  })

  it("detects DNS rebinding wildcard domains and embedded private IPs", () => {
    expect(isPrivateOrInternalHost("127.0.0.1.nip.io")).toBe(true)
    expect(isPrivateOrInternalHost("169.254.169.254.nip.io")).toBe(true)
    expect(isPrivateOrInternalHost("10.0.0.1.sslip.io")).toBe(true)
    expect(isPrivateOrInternalHost("customer.127-0-0-1.nip.io")).toBe(true)
    expect(isPrivateOrInternalHost("localtest.me")).toBe(true)
    expect(isPrivateOrInternalHost("test.localtest.me")).toBe(true)
  })

  it("allows legitimate public domains", () => {
    expect(isPrivateOrInternalHost("linkedin.com")).toBe(false)
    expect(isPrivateOrInternalHost("hh.ru")).toBe(false)
    expect(isPrivateOrInternalHost("career.habr.com")).toBe(false)
    expect(isPrivateOrInternalHost("freehire.me")).toBe(false)
    expect(isPrivateOrInternalHost("jobs.lever.co")).toBe(false)
    expect(isPrivateOrInternalHost("boards.greenhouse.io")).toBe(false)
  })

  it("validates safe external URLs", () => {
    expect(isSafeExternalUrl("https://career.habr.com/vacancies/123")).toBe(true)
    expect(isSafeExternalUrl("https://boards.greenhouse.io/company/jobs/456")).toBe(true)
    expect(isSafeExternalUrl("http://freehire.me/job/789")).toBe(true)

    // Rejects private/SSRF URLs
    expect(isSafeExternalUrl("http://169.254.169.254/latest/meta-data/")).toBe(false)
    expect(isSafeExternalUrl("http://localhost:5432")).toBe(false)
    expect(isSafeExternalUrl("http://127.0.0.1:3001/health")).toBe(false)
    expect(isSafeExternalUrl("http://postgres:5432/db")).toBe(false)
    expect(isSafeExternalUrl("ftp://example.com/file")).toBe(false)
    expect(isSafeExternalUrl("javascript:alert(1)")).toBe(false)

    // Rejects IPv6-mapped and wildcard DNS bypasses
    expect(isSafeExternalUrl("http://[::ffff:127.0.0.1]/secret")).toBe(false)
    expect(isSafeExternalUrl("http://[::ffff:169.254.169.254]/latest/meta-data/")).toBe(false)
    expect(isSafeExternalUrl("http://[::]/health")).toBe(false)
    expect(isSafeExternalUrl("http://127.0.0.1.nip.io/admin")).toBe(false)
    expect(isSafeExternalUrl("http://169.254.169.254.nip.io/")).toBe(false)
    expect(isSafeExternalUrl("http://customer.127-0-0-1.nip.io/")).toBe(false)
    expect(isSafeExternalUrl("http://[64:ff9b::127.0.0.1]/")).toBe(false)
  })

  it("sanitizes untrusted URLs with safe fallback", () => {
    const fallback = "https://freehire.me/job/safe-fallback"
    expect(sanitizeSourceUrl("http://169.254.169.254/secret", fallback)).toBe(fallback)
    expect(sanitizeSourceUrl("http://localhost:8080/exploit", fallback)).toBe(fallback)
    expect(sanitizeSourceUrl("http://[::ffff:169.254.169.254]/meta", fallback)).toBe(fallback)
    expect(sanitizeSourceUrl("http://127.0.0.1.nip.io/evil", fallback)).toBe(fallback)
    expect(sanitizeSourceUrl("https://jobs.lever.co/valid-job", fallback)).toBe("https://jobs.lever.co/valid-job")
  })
})

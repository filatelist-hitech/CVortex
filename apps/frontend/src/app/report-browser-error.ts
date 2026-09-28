const DUPLICATE_WINDOW_MS = 10_000;
const MAX_RECENT_REPORTS = 32;
const recentReports = new Map<string, number>();
const SAFE_ERROR_NAMES = new Set(["Error", "EvalError", "RangeError", "ReferenceError", "SyntaxError", "TypeError", "URIError", "AggregateError"]);

async function opaqueErrorReference(error: unknown): Promise<string | undefined> {
  if (!(error instanceof Error) || !globalThis.crypto?.subtle) return undefined;

  const name = SAFE_ERROR_NAMES.has(error.name) ? error.name : "Error";
  const locations = (error.stack ?? "").split("\n").slice(1, 9).flatMap((frame) => {
    const match = /:(\d{1,8}):(\d{1,8})\)?$/.exec(frame);
    return match ? [match[1] + ":" + match[2]] : [];
  }).slice(0, 4);
  const bytes = await globalThis.crypto.subtle.digest("SHA-256", new TextEncoder().encode(JSON.stringify([name, locations])));
  return Array.from(new Uint8Array(bytes), (byte) => byte.toString(16).padStart(2, "0")).join("");
}

function safeRouteReference(): string {
  return window.location.pathname === "/" || window.location.pathname === "/register"
    ? window.location.pathname
    : "other";
}

function recentlyReported(key: string, now: number): boolean {
  for (const [recentKey, timestamp] of recentReports) {
    if (now - timestamp >= DUPLICATE_WINDOW_MS) recentReports.delete(recentKey);
  }
  const previous = recentReports.get(key);
  if (previous !== undefined && now - previous < DUPLICATE_WINDOW_MS) return true;

  recentReports.delete(key);
  recentReports.set(key, now);
  while (recentReports.size > MAX_RECENT_REPORTS) {
    const oldest = recentReports.keys().next().value;
    if (oldest === undefined) break;
    recentReports.delete(oldest);
  }

  return false;
}

export async function reportBrowserError(kind: "runtime" | "rejection" | "render", component = "browser", error?: unknown): Promise<string | null> {
  let errorRef: string | undefined;
  try { errorRef = await opaqueErrorReference(error); } catch { errorRef = undefined; }
  const now = Date.now();
  const reportKey = component + ":" + kind + ":" + (errorRef ?? "unclassified");
  if (recentlyReported(reportKey, now)) return null;

  try {
    const csrf = decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");
    const response = await fetch("/api/v1/diagnostics/report", {
      method: "POST", credentials: "same-origin",
      headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": csrf },
      body: JSON.stringify({
        kind,
        component,
        route: safeRouteReference(),
        ...(errorRef ? { error_ref: errorRef } : {}),
      }),
    });
    if (!response.ok) return null;
    const body = await response.json();
    return typeof body?.data?.request_id === "string" ? body.data.request_id : null;
  } catch {
    return null;
  }
}

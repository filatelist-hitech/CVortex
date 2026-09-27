let lastReport = 0;
let lastResult: Promise<string | null> | null = null;

export function reportBrowserError(kind: "runtime" | "rejection" | "render", component = "browser"): Promise<string | null> {
  if (Date.now() - lastReport < 10_000) return lastResult ?? Promise.resolve(null);
  lastReport = Date.now();
  lastResult = (async () => { try {
    const csrf = decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");
    const response = await fetch("/api/v1/diagnostics/report", {
      method: "POST", credentials: "same-origin",
      headers: { Accept: "application/json", "Content-Type": "application/json", "X-XSRF-TOKEN": csrf },
      body: JSON.stringify({ kind, component }),
    });
    if (!response.ok) return null;
    const body = await response.json();
    return typeof body?.data?.request_id === "string" ? body.data.request_id : null;
  } catch { return null; } })();
  return lastResult;
}

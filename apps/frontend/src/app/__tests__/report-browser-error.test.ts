import { afterEach, describe, expect, it, vi } from "vitest";
import { reportBrowserError } from "../report-browser-error";

afterEach(() => {
  vi.restoreAllMocks();
  window.history.replaceState({}, "", "/");
});

describe("browser diagnostics reporting", () => {
  it("suppresses the same recent occurrence without reusing its reference", async () => {
    vi.spyOn(Date, "now").mockReturnValue(Date.now() + 100_000);
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json({ data: { request_id: "req_first" } }));
    const failure = new TypeError("password=SECRET_CANARY");
    failure.stack = "TypeError: password=SECRET_CANARY\n    at readField (http://localhost/app.js:12:4)";

    await expect(reportBrowserError("runtime", "browser", failure)).resolves.toBe("req_first");
    await expect(reportBrowserError("runtime", "browser", failure)).resolves.toBeNull();

    expect(fetchMock).toHaveBeenCalledTimes(1);
  });

  it("sends distinct opaque references without messages, stacks, or query strings", async () => {
    window.history.replaceState({}, "", "/?api_key=SECRET_CANARY");
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async () => {
      const requestId = "req_browser_" + fetchMock.mock.calls.length;
      return Response.json({ data: { request_id: requestId } });
    });
    const firstFailure = new Error("email=person@example.test token=SECRET_CANARY");
    firstFailure.stack = "Error: email=person@example.test\n    at renderCard (https://cvortex.test/page?token=SECRET_CANARY:12:4)";
    const secondFailure = new Error("resume=PRIVATE_CONTENT");
    secondFailure.stack = "Error: resume=PRIVATE_CONTENT\n    at renderCard (https://cvortex.test/page?token=SECRET_CANARY:28:4)";

    await reportBrowserError("render", "app-root", firstFailure);
    await reportBrowserError("render", "app-root", secondFailure);

    expect(fetchMock).toHaveBeenCalledTimes(2);
    const payloads = fetchMock.mock.calls.map((call) => JSON.parse(String(call[1]?.body)) as Record<string, unknown>);
    expect(payloads[0].error_ref).toMatch(/^[a-f0-9]{64}$/);
    expect(payloads[1].error_ref).toMatch(/^[a-f0-9]{64}$/);
    expect(payloads[0].error_ref).not.toBe(payloads[1].error_ref);
    for (const payload of payloads) {
      expect(payload.route).toBe("/");
      expect(JSON.stringify(payload)).not.toMatch(/SECRET_CANARY|person@example\.test|PRIVATE_CONTENT|https:\/\/|resume=|email=|token=/i);
      expect(payload).not.toHaveProperty("message");
      expect(payload).not.toHaveProperty("stack");
    }
  });
});

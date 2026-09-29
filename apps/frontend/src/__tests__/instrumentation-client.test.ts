import { afterEach, describe, expect, it, vi } from "vitest";

const mocks = vi.hoisted(() => ({ reportBrowserError: vi.fn() }));

vi.mock("../app/report-browser-error", () => mocks);

afterEach(() => {
  vi.restoreAllMocks();
  vi.resetModules();
  mocks.reportBrowserError.mockReset();
});

describe("client error instrumentation", () => {
  it("forwards runtime errors and unhandled rejection reasons to telemetry", async () => {
    const listeners = new Map<string, EventListener>();
    vi.spyOn(window, "addEventListener").mockImplementation((type, listener) => {
      if (type === "error" || type === "unhandledrejection") {
        listeners.set(type, listener as EventListener);
      }
    });

    await import("../instrumentation-client");

    const runtimeError = new TypeError("runtime failure");
    listeners.get("error")?.(new ErrorEvent("error", { error: runtimeError }));

    const rejectionReason = "token=SECRET_CANARY";
    const rejectionEvent = Object.assign(new Event("unhandledrejection"), { reason: rejectionReason });
    listeners.get("unhandledrejection")?.(rejectionEvent);

    expect(mocks.reportBrowserError).toHaveBeenNthCalledWith(1, "runtime", "browser", runtimeError);
    expect(mocks.reportBrowserError).toHaveBeenNthCalledWith(2, "rejection", "browser", rejectionReason);
  });
});

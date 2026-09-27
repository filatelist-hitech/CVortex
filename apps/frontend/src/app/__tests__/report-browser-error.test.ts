import { afterEach, describe, expect, it, vi } from "vitest";
import { reportBrowserError } from "../report-browser-error";

afterEach(() => vi.restoreAllMocks());

describe("browser diagnostics reporting", () => {
  it("returns no reference for a suppressed event instead of reusing an earlier one", async () => {
    vi.spyOn(Date, "now").mockReturnValue(Date.now() + 100_000);
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json({ data: { request_id: "req_first" } }));

    await expect(reportBrowserError("runtime")).resolves.toBe("req_first");
    await expect(reportBrowserError("render", "app-root")).resolves.toBeNull();

    expect(fetchMock).toHaveBeenCalledTimes(1);
  });
});

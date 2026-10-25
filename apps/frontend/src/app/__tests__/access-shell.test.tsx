import { cleanup, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AccessShell, { ApiError, api } from "../access-shell";

const mocks = vi.hoisted(() => ({
  router: { replace: vi.fn(), push: vi.fn() },
}));

vi.mock("next/navigation", () => ({
  useRouter: () => mocks.router,
}));

vi.mock("../career-workspace", () => ({
  default: ({ userId }: { userId?: string }) => (
    <div data-testid="mock-career-workspace" data-user-id={userId}>
      Career Workspace Content for {userId}
    </div>
  ),
}));

vi.mock("../diagnostics", () => ({
  default: () => <div data-testid="mock-diagnostics">Diagnostics Content</div>,
}));

vi.mock("../admin-settings", () => ({
  default: () => <div data-testid="mock-admin-settings">Admin Settings Content</div>,
}));

Object.defineProperty(window, "matchMedia", {
  writable: true,
  value: vi.fn().mockImplementation(() => ({
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })),
});
HTMLDialogElement.prototype.showModal = function () { this.setAttribute("open", ""); };
HTMLDialogElement.prototype.close = function () { this.removeAttribute("open"); this.dispatchEvent(new Event("close")); };

describe("api() function contract and abort handling", () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    global.fetch = originalFetch;
  });

  it("passes through AbortError without converting to NETWORK_ERROR", async () => {
    const abortError = new DOMException("The user aborted a request.", "AbortError");
    global.fetch = vi.fn().mockRejectedValue(abortError);

    await expect(api("/api/v1/some-endpoint")).rejects.toThrow(abortError);

    try {
      await api("/api/v1/some-endpoint");
      expect.fail("Should have thrown");
    } catch (err: unknown) {
      expect(err).toBe(abortError);
      expect(err instanceof ApiError).toBe(false);
      expect((err as DOMException).name).toBe("AbortError");
    }
  });

  it("converts genuine network failure to ApiError with NETWORK_ERROR code", async () => {
    const networkError = new TypeError("Failed to fetch");
    global.fetch = vi.fn().mockRejectedValue(networkError);

    await expect(api("/api/v1/some-endpoint")).rejects.toThrow(ApiError);

    try {
      await api("/api/v1/some-endpoint");
      expect.fail("Should have thrown");
    } catch (err: unknown) {
      expect(err instanceof ApiError).toBe(true);
      const apiErr = err as ApiError;
      expect(apiErr.code).toBe("NETWORK_ERROR");
      expect(apiErr.retryable).toBe(true);
      expect(apiErr.message).toContain("NETWORK_ERROR");
    }
  });

  it("parses server error responses and Retry-After header", async () => {
    global.fetch = vi.fn().mockResolvedValue({
      ok: false,
      status: 429,
      headers: new Headers({ "Retry-After": "30", "X-Request-ID": "req-123" }),
      json: () => Promise.resolve({
        error: { code: "RATE_LIMIT_EXCEEDED", retryable: true, request_id: "req-123" },
      }),
    } as unknown as Response);

    try {
      await api("/api/v1/some-endpoint");
      expect.fail("Should have thrown");
    } catch (err: unknown) {
      expect(err instanceof ApiError).toBe(true);
      const apiErr = err as ApiError;
      expect(apiErr.code).toBe("RATE_LIMIT_EXCEEDED");
      expect(apiErr.retryable).toBe(true);
      expect(apiErr.retryAfterSeconds).toBe(30);
      expect(apiErr.message).toContain("req-123");
    }
  });
});

describe("AccessShell user isolation and workspace key integrity", () => {
  const originalFetch = global.fetch;

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    global.fetch = originalFetch;
  });

  it("passes user.id as key and userId prop to CareerWorkspace to prevent cross-account leak", async () => {
    global.fetch = vi.fn().mockImplementation((url: string) => {
      if (url.includes("/api/v1/me")) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: () => Promise.resolve({
            data: { id: "user-alpha-123", email: "alpha@example.com", role: "user", status: "active" },
          }),
        });
      }
      return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}) });
    });

    render(<AccessShell destination="career" />);

    const workspace = await screen.findByTestId("mock-career-workspace");
    expect(workspace).toBeInTheDocument();
    expect(workspace).toHaveAttribute("data-user-id", "user-alpha-123");
    expect(screen.getByText("Career Workspace Content for user-alpha-123")).toBeInTheDocument();
  });

  it("guards /admin/settings against non-admin users with 403 status card", async () => {
    global.fetch = vi.fn().mockImplementation((url: string) => {
      if (url.includes("/api/v1/me")) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: () => Promise.resolve({
            data: { id: "user-regular-456", email: "regular@example.com", role: "user", status: "active" },
          }),
        });
      }
      return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}) });
    });

    render(<AccessShell destination="admin-settings" />);

    expect(await screen.findByRole("heading", { name: "Доступ запрещён" })).toBeInTheDocument();
    expect(screen.getByText("Настройки системы доступны только администраторам.")).toBeInTheDocument();
    expect(screen.queryByTestId("mock-admin-settings")).not.toBeInTheDocument();
  });

  it("renders AdminSystemSettings for authenticated admin user", async () => {
    global.fetch = vi.fn().mockImplementation((url: string) => {
      if (url.includes("/api/v1/me")) {
        return Promise.resolve({
          ok: true,
          status: 200,
          json: () => Promise.resolve({
            data: { id: "admin-root-001", email: "admin@example.com", role: "admin", status: "active" },
          }),
        });
      }
      return Promise.resolve({ ok: true, status: 200, json: () => Promise.resolve({}) });
    });

    render(<AccessShell destination="admin-settings" />);

    expect(await screen.findByTestId("mock-admin-settings")).toBeInTheDocument();
    expect(screen.queryByText("Доступ запрещён")).not.toBeInTheDocument();
  });
});

import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, api } from "../access-shell";
import Diagnostics from "../diagnostics";
import ErrorPage from "../error";

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe("diagnostic UI", () => {
  it("shows a safe API error with code, reference and retry hint", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json({
      message: "SQLSTATE private secret", error: {
        code: "INTERNAL_ERROR", request_id: "req_test", retryable: true,
      },
    }, { status: 500 }));
    const failure = await api("/api/v1/fail").catch((error) => error);
    expect(failure).toBeInstanceOf(ApiError);
    expect(failure).toMatchObject({ code: "INTERNAL_ERROR", retryable: true });
    expect(failure.message).toContain("[INTERNAL_ERROR] Reference: req_test You can retry.");
    expect(failure.message).not.toContain("SQLSTATE");
  });

  it("does not describe a non-retryable provider configuration error as retryable", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json({
      error: { code: "LLM_PROVIDER_CONFIGURATION", request_id: "req_config", retryable: false },
    }, { status: 503 }));
    const failure = await api("/api/v1/applications/generate").catch((error) => error);
    expect(failure).toMatchObject({ code: "LLM_PROVIDER_CONFIGURATION", retryable: false });
    expect(failure.message).toContain("needs administrator configuration");
    expect(failure.message).not.toContain("retry");
  });

  it("lists and filters incidents, then resolves detail", async () => {
    const incident = { id: "incident-1", error_code: "INTERNAL_ERROR", severity: "ERROR", status: "OPEN",
      message: "A safe failure.", service: "backend", component: "api", environment: "testing",
      occurrence_count: 2, first_seen_at: "2026-09-27", last_seen_at: "2026-09-27", exception_class: "RuntimeException",
      retryable: false, impact: "The affected operation did not complete.", recovery_action: "Inspect the sanitized incident details and dependency health." };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path.startsWith("/api/v1/diagnostics/incidents?")) return Response.json({ data: { data: [incident] } });
      if (path.endsWith("/incident-1") && options?.method === "PATCH") return Response.json({ data: { incident: { ...incident, status: "RESOLVED" }, occurrences: [] } });
      if (path.endsWith("/incident-1")) return Response.json({ data: { incident, occurrences: [{ id: "event-1", request_id: "req_test", queue: "analysis-high", connection: "redis", created_at: "2026-09-27", safe_stack: "RuntimeException.php:7 RuntimeException (throw site)\n[app] ApplicationPreparationService.php:42 generate\n[framework] ControllerDispatcher.php:91 dispatch" }] } });
      throw new Error(path);
    });
    render(<Diagnostics />);
    expect(await screen.findByText("INTERNAL_ERROR")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("search"), { target: { value: "req_test" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(expect.stringContaining("search=req_test"), expect.anything()));
    fireEvent.click(screen.getByRole("button", { name: /INTERNAL_ERROR/ }));
    expect(await screen.findByText(/Request req_test/)).toBeInTheDocument();
    expect(screen.getByText(/Queue analysis-high · Connection redis/)).toBeInTheDocument();
    expect(screen.getByText("Next action:")).toBeInTheDocument();
    expect(screen.getByText("Inspect the sanitized incident details and dependency health.")).toBeInTheDocument();
    expect(screen.getAllByText(/ApplicationPreparationService.php/)).toHaveLength(2);
    const traceDetails = screen.getByText("Show complete bounded trace").closest("details");
    expect(traceDetails).not.toHaveAttribute("open");
    fireEvent.click(screen.getByText("Show complete bounded trace"));
    expect(traceDetails).toHaveAttribute("open");
    fireEvent.click(screen.getByRole("button", { name: "RESOLVED" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(expect.stringContaining("/incident-1"), expect.objectContaining({ method: "PATCH" })));
  });

  it("loads the next incident page and resets it when filters change", async () => {
    const incident = { id: "incident-2", error_code: "QUEUE_JOB_FAILED", severity: "ERROR", status: "OPEN",
      message: "A background operation failed.", service: "backend", component: "queue", environment: "testing",
      occurrence_count: 1, first_seen_at: "2026-09-27", last_seen_at: "2026-09-27", exception_class: "RuntimeException" };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const page = new URL(String(input), "http://localhost").searchParams.get("page");
      return Response.json({ data: { data: page === "2" ? [incident] : [], current_page: Number(page), last_page: 2, total: 26 } });
    });
    render(<Diagnostics />);
    expect(await screen.findByText("Page 1 of 2 · 26 incidents")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Next" }));
    expect(await screen.findByText("QUEUE_JOB_FAILED")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("search"), { target: { value: "req_test" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith(expect.stringContaining("search=req_test&page=1"), expect.anything()));
  });

  it("shows every incident category by default and clears filters with Show all incidents", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json({
      data: { data: [], current_page: 1, last_page: 1, total: 0 },
    }));
    render(<Diagnostics />);

    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith("/api/v1/diagnostics/incidents?page=1", expect.anything()));
    expect(screen.getByLabelText("severity")).toHaveValue("");
    expect(screen.getByLabelText("status")).toHaveValue("");
    expect(screen.getByText(/All incident severities and statuses are shown by default/)).toBeInTheDocument();

    fireEvent.change(screen.getByLabelText("severity"), { target: { value: "ERROR" } });
    fireEvent.change(screen.getByLabelText("application id"), { target: { value: "app_test" } });
    fireEvent.click(screen.getByRole("button", { name: "Show all incidents" }));

    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith("/api/v1/diagnostics/incidents?page=1", expect.anything()));
    expect(screen.getByLabelText("severity")).toHaveValue("");
    expect(screen.getByLabelText("application id")).toHaveValue("");
  });

  it("offers a Retry action only when the API marks the failure retryable", async () => {
    let retryable = false;
    vi.spyOn(globalThis, "fetch").mockImplementation(async () => Response.json({
      error: { code: "INTERNAL_ERROR", request_id: "req_list", retryable },
    }, { status: 503 }));
    render(<Diagnostics />);
    expect(await screen.findByRole("alert")).toHaveTextContent("The operation could not be completed.");
    expect(screen.queryByRole("button", { name: "Retry" })).not.toBeInTheDocument();

    retryable = true;
    fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    expect(await screen.findByRole("button", { name: "Retry" })).toBeInTheDocument();
  });

  it("renders a recovery action without an exception message", () => {
    const reset = vi.fn();
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(null, { status: 401 }));
    render(<ErrorPage error={new Error("SECRET_CANARY")} reset={reset} />);
    expect(screen.getByText("Page unavailable")).toBeInTheDocument();
    expect(screen.queryByText("SECRET_CANARY")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    expect(reset).toHaveBeenCalledOnce();
  });
});

import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, api } from "../access-shell";
import Diagnostics from "../diagnostics";
import ErrorPage from "../error";

afterEach(() => { cleanup(); vi.restoreAllMocks(); });

describe("diagnostic UI", () => {
  const incident = { id: "incident-1", error_code: "INTERNAL_ERROR", severity: "ERROR", status: "OPEN",
    message: "A safe failure.", service: "backend", component: "api", environment: "testing",
    occurrence_count: 1, first_seen_at: "2026-09-27", last_seen_at: "2026-09-27",
    retryable: true, impact: "The affected operation did not complete.", recovery_action: "Inspect incident details." };

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

  it("ignores stale incident-list errors after a newer reload succeeds", async () => {
    const latestIncident = { ...incident, id: "latest", error_code: "LATEST_INCIDENT" };
    const pending: Array<{ resolve: (response: Response) => void; reject: (error: Error) => void }> = [];
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(() => new Promise((resolve, reject) => {
      pending.push({ resolve, reject });
    }));
    render(<Diagnostics />);

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
    fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));

    pending[1].resolve(Response.json({ data: { data: [latestIncident], current_page: 2, last_page: 2, total: 26 } }));
    expect(await screen.findByText("LATEST_INCIDENT")).toBeInTheDocument();
    pending[0].reject(new Error("stale network failure"));

    await waitFor(() => expect(screen.getByText("Page 2 of 2 · 26 incidents")).toBeInTheDocument());
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  it("keeps pagination on the filters that produced the displayed list", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const page = new URL(String(input), "http://localhost").searchParams.get("page");
      return Response.json({ data: { data: [], current_page: Number(page), last_page: 2, total: 26 } });
    });
    render(<Diagnostics />);

    expect(await screen.findByText("Page 1 of 2 · 26 incidents")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("severity"), { target: { value: "ERROR" } });
    fireEvent.click(screen.getByRole("button", { name: "Next" }));

    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith("/api/v1/diagnostics/incidents?page=2", expect.anything()));
    expect(await screen.findByText("Page 2 of 2 · 26 incidents")).toBeInTheDocument();
  });

  it("does not let an older successful list response replace the latest results", async () => {
    const staleIncident = { ...incident, id: "stale", error_code: "STALE_INCIDENT" };
    const latestIncident = { ...incident, id: "latest", error_code: "LATEST_INCIDENT" };
    const pending: Array<(response: Response) => void> = [];
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(() => new Promise((resolve) => pending.push(resolve)));
    render(<Diagnostics />);

    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(1));
    fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));

    pending[1](Response.json({ data: { data: [latestIncident], current_page: 2, last_page: 2, total: 26 } }));
    expect(await screen.findByText("LATEST_INCIDENT")).toBeInTheDocument();
    pending[0](Response.json({ data: { data: [staleIncident], current_page: 1, last_page: 1, total: 1 } }));

    await waitFor(() => expect(screen.getByText("Page 2 of 2 · 26 incidents")).toBeInTheDocument());
    expect(screen.queryByText("STALE_INCIDENT")).not.toBeInTheDocument();
    expect(screen.getByText("LATEST_INCIDENT")).toBeInTheDocument();
  });

  it("ignores stale incident-detail responses after a newer incident is opened", async () => {
    const firstIncident = { ...incident, id: "first", error_code: "FIRST_DETAIL" };
    const secondIncident = { ...incident, id: "second", error_code: "SECOND_DETAIL" };
    const pending: Array<(response: Response) => void> = [];
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation((input) => {
      const path = String(input);
      if (path.includes("incidents?")) {
        return Promise.resolve(Response.json({ data: { data: [firstIncident, secondIncident], current_page: 1, last_page: 1, total: 2 } }));
      }
      return new Promise((resolve) => pending.push(resolve));
    });
    render(<Diagnostics />);

    fireEvent.click(await screen.findByRole("button", { name: /FIRST_DETAIL/ }));
    fireEvent.click(screen.getByRole("button", { name: /SECOND_DETAIL/ }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(3));

    pending[1](Response.json({ data: { incident: secondIncident, occurrences: [] } }));
    expect(await screen.findByRole("region", { name: "SECOND_DETAIL" })).toBeInTheDocument();
    pending[0](Response.json({ data: { incident: firstIncident, occurrences: [] } }));

    await waitFor(() => expect(screen.getByRole("region", { name: "SECOND_DETAIL" })).toBeInTheDocument());
    expect(screen.queryByRole("region", { name: "FIRST_DETAIL" })).not.toBeInTheDocument();
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

  it("retries the failed list with its original filters and page", async () => {
    let fail = false;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (fail) {
        fail = false;
        throw new Error("network down");
      }
      const page = Number(new URL(path, "http://localhost").searchParams.get("page"));
      return Response.json({ data: { data: page === 2 ? [incident] : [], current_page: page, last_page: 2, total: 26 } });
    });
    render(<Diagnostics />);
    expect(await screen.findByText("Page 1 of 2 · 26 incidents")).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("search"), { target: { value: "req_original" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith("/api/v1/diagnostics/incidents?search=req_original&page=1", expect.anything()));
    fail = true;
    fireEvent.click(screen.getByRole("button", { name: "Next" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Incident list could not be loaded.");
    fireEvent.change(screen.getByLabelText("search"), { target: { value: "req_changed" } });
    const callsBeforeRetry = fetchMock.mock.calls.length;
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(callsBeforeRetry + 1));
    expect(fetchMock).toHaveBeenLastCalledWith("/api/v1/diagnostics/incidents?search=req_original&page=2", expect.anything());
    expect(await screen.findByText("Page 2 of 2 · 26 incidents")).toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  it("retries only the failed incident detail GET, including a second network failure", async () => {
    let detailCalls = 0;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path.endsWith("/incident-1")) {
        detailCalls++;
        if (detailCalls < 3) throw new Error("network down");
        return Response.json({ data: { incident, occurrences: [] } });
      }
      return Response.json({ data: { data: [incident], current_page: 1, last_page: 1, total: 1 } });
    });
    render(<Diagnostics />);
    fireEvent.click(await screen.findByRole("button", { name: /INTERNAL_ERROR/ }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Incident details could not be loaded.");
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    await waitFor(() => expect(detailCalls).toBe(2));
    expect(screen.getByRole("alert")).toHaveTextContent("Incident details could not be loaded.");
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    expect(await screen.findByText("Inspect incident details.")).toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    expect(fetchMock.mock.calls.filter(([path]) => String(path).includes("incidents?"))).toHaveLength(1);
    expect(fetchMock.mock.calls.filter(([, options]) => options?.method === "PATCH")).toHaveLength(0);
    expect(detailCalls).toBe(3);
  });

  it("retries only the failed status PATCH and keeps detail visible after success", async () => {
    let patchCalls = 0;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (options?.method === "PATCH") {
        patchCalls++;
        if (patchCalls === 1) throw new Error("network down");
        if (patchCalls === 2) return Response.json({ error: { code: "INTERNAL_ERROR", retryable: true } }, { status: 503 });
        return Response.json({ data: { incident: { ...incident, status: "RESOLVED" }, occurrences: [] } });
      }
      if (path.endsWith("/incident-1")) return Response.json({ data: { incident, occurrences: [] } });
      return Response.json({ data: { data: [incident], current_page: 1, last_page: 1, total: 1 } });
    });
    render(<Diagnostics />);
    fireEvent.click(await screen.findByRole("button", { name: /INTERNAL_ERROR/ }));
    expect(await screen.findByText("Inspect incident details.")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "RESOLVED" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Status change to RESOLVED failed.");
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    await waitFor(() => expect(patchCalls).toBe(2));
    expect(screen.getByRole("alert")).toHaveTextContent("Status change to RESOLVED failed.");
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    expect(await screen.findByRole("status")).toHaveTextContent("Incident status changed to RESOLVED.");
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "RESOLVED" })).toBeDisabled();
    expect(fetchMock.mock.calls.filter(([, options]) => options?.method === "PATCH")).toHaveLength(3);
    expect(fetchMock.mock.calls.filter(([path, options]) => String(path).endsWith("/incident-1") && options?.method !== "PATCH")).toHaveLength(1);
  });

  it("serializes status changes while a PATCH is pending", async () => {
    let resolvePatch: ((response: Response) => void) | undefined;
    let patchCalls = 0;
    const updatedIncident = { ...incident, status: "IGNORED" };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (options?.method === "PATCH") {
        patchCalls++;
        return new Promise((resolve) => { resolvePatch = resolve; });
      }
      if (path.endsWith("/incident-1")) return Response.json({ data: { incident, occurrences: [] } });
      return Response.json({ data: { data: [incident], current_page: 1, last_page: 1, total: 1 } });
    });
    render(<Diagnostics />);
    fireEvent.click(await screen.findByRole("button", { name: /INTERNAL_ERROR/ }));
    expect(await screen.findByText("Inspect incident details.")).toBeInTheDocument();

    fireEvent.click(screen.getByRole("button", { name: "IGNORED" }));
    await waitFor(() => expect(resolvePatch).toBeDefined());
    expect(screen.getByRole("button", { name: "RESOLVED" })).toBeDisabled();
    expect(screen.getByRole("button", { name: "OPEN" })).toBeDisabled();

    fireEvent.click(screen.getByRole("button", { name: "RESOLVED" }));
    expect(patchCalls).toBe(1);
    resolvePatch?.(Response.json({ data: { incident: updatedIncident, occurrences: [] } }));

    expect(await screen.findByRole("status")).toHaveTextContent("Incident status changed to IGNORED.");
    expect(screen.getByText(/ERROR · IGNORED · backend\/api/)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "IGNORED" })).toBeDisabled();
    expect(fetchMock.mock.calls.filter(([, request]) => request?.method === "PATCH")).toHaveLength(1);
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

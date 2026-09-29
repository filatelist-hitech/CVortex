import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, api } from "../access-shell";
import AccessShell from "../access-shell";
import Diagnostics from "../diagnostics";
import ErrorPage from "../error";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));
afterEach(() => { cleanup(); vi.restoreAllMocks(); });

const incident = {
  id: "incident-1", severity: "ERROR", status: "OPEN", error_code: "LLM_PROVIDER_UNAVAILABLE",
  message: "The analysis service is temporarily unavailable.", service: "backend", component: "vacancy", environment: "testing",
  occurrence_count: 23, first_seen_at: "2026-09-27T19:00:00Z", last_seen_at: "2026-09-28T19:00:00Z",
  exception_class: "App\\AI\\Exceptions\\LlmProviderException", retryable: true,
  impact: "The LLM-backed operation did not complete.", recovery_action: "Retry after the provider recovers; check provider status if the failure continues.",
  latest_operation: "vacancy_requirement_extraction", latest_provider: "OpenAI",
};
const occurrence = {
  id: "event-1", created_at: "2026-09-28T19:00:00Z", request_id: "req_123456789abcdefgh", job_id: "job_123456789abcdefgh",
  llm_run_id: "run_123456789abcdefgh", application_id: "app_123456789abcdefgh", user_id: "user-1",
  route: "/api/v1/vacancies", operation: "vacancy_requirement_extraction", provider: "OpenAI", queue: "analysis-high", connection: "redis", attempt: 3, retry_after_seconds: null,
  safe_stack: "ConfiguredLlmProvider.php:23 throw site\n[app] VacancyAnalysisService.php:135 analyze\n[framework] ControllerDispatcher.php:91 dispatch",
};
function mockData(items = [incident], detailIncident = incident, detailOccurrence: Omit<typeof occurrence, "retry_after_seconds"> & { retry_after_seconds: number | null } = occurrence) {
  return vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
    const path = String(input);
    if (path.includes("/diagnostics/incidents?")) return Response.json({ data: { data: items, current_page: 1, last_page: 1, total: items.length } });
    if (path.endsWith("/incident-1") && options?.method === "PATCH") return Response.json({ data: { incident: { ...detailIncident, status: JSON.parse(String(options.body)).status }, occurrences: [detailOccurrence] } });
    if (path.endsWith("/incident-1")) return Response.json({ data: { incident: detailIncident, occurrences: [detailOccurrence] } });
    throw new Error(path);
  });
}
async function openDetail() {
  render(<Diagnostics />);
  fireEvent.click(await screen.findByRole("button", { name: /Vacancy analysis failed/ }));
  await screen.findByRole("heading", { name: "Vacancy analysis failed" });
}

describe("Error Center", () => {
  it("keeps safe API recovery copy and retry flags", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json({ message: "SECRET", error: { code: "LLM_PROVIDER_CONFIGURATION", request_id: "req_config", retryable: false } }, { status: 503 }));
    const failure = await api("/api/v1/applications/generate").catch((error) => error);
    expect(failure).toBeInstanceOf(ApiError);
    expect(failure).toMatchObject({ code: "LLM_PROVIDER_CONFIGURATION", retryable: false });
    expect(failure.message).toContain("needs administrator configuration");
    expect(failure.message).not.toContain("SECRET");
  });

  it("shows scan-first rows, count, readable time, and compact filters", async () => {
    const fetchMock = mockData();
    render(<Diagnostics />);
    expect(screen.getByRole("status", { name: "Loading incidents" })).toBeInTheDocument();
    const row = await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    expect(row).toHaveTextContent("Provider temporarily unavailable");
    expect(row).toHaveTextContent("23 occurrences");
    expect(row).toHaveTextContent("OpenAI");
    expect(row).not.toHaveTextContent("LlmProviderException");
    expect(row).not.toHaveTextContent("incident-1");
    expect(within(row).getByText(/Last seen/).querySelector("time")).toHaveAttribute("title", expect.stringContaining("2026"));
    expect(screen.getByLabelText("Sort")).toHaveValue("priority");
    expect(screen.getByRole("button", { name: "More filters" })).toHaveAttribute("aria-expanded", "false");
    expect(screen.queryByLabelText("application id")).not.toBeVisible();
    fireEvent.click(screen.getByRole("button", { name: "More filters" }));
    expect(screen.getByLabelText("application id")).toBeVisible();
    fireEvent.change(screen.getByLabelText("Status"), { target: { value: "OPEN" } });
    fireEvent.change(screen.getByLabelText("Time"), { target: { value: "24" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(expect.stringContaining("status=OPEN&hours=24"), expect.anything()));
    expect(screen.getByRole("button", { name: "Remove status filter" })).toBeInTheDocument();
    expect(screen.getByText("Last 24h ×")).toBeInTheDocument();
    fireEvent.click(within(screen.getByLabelText("Active filters")).getByRole("button", { name: "Clear all" }));
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith("/api/v1/diagnostics/incidents?page=1", expect.anything()));
  });

  it("distinguishes no incidents, filtered empty and missing references", async () => {
    const fetchMock = mockData([]);
    render(<Diagnostics />);
    expect(await screen.findByRole("heading", { name: "No incidents recorded" })).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Severity"), { target: { value: "ERROR" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    expect(await screen.findByRole("heading", { name: "No incidents match these filters" })).toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Search incidents"), { target: { value: "req_missing" } });
    fireEvent.click(screen.getByRole("button", { name: "Search" }));
    expect(await screen.findByRole("heading", { name: "No incidents match this search with the current filters." })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Clear filters and search again" }));
    expect(await screen.findByRole("heading", { name: "No incident found for this reference" })).toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledWith(expect.stringContaining("search=req_missing"), expect.anything());
  });

  it("puts cause, impact, action and retry decision ahead of technical context", async () => {
    mockData();
    await openDetail();
    const decision = screen.getByRole("region", { name: "Diagnosis and next action" });
    expect(decision).toHaveTextContent("Provider temporarily unavailable");
    expect(decision).toHaveTextContent("The LLM-backed operation did not complete.");
    expect(decision).toHaveTextContent("Diagnostics do not confirm whether data changed.");
    expect(decision).toHaveTextContent("Retry after the provider recovers");
    expect(decision).toHaveTextContent("RetryableYes");
    expect(screen.getByText("OpenAI", { selector: "dd" })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Technical details/ })).toHaveAttribute("aria-expanded", "false");
    expect(screen.queryByText(/ConfiguredLlmProvider.php/)).not.toBeInTheDocument();
    expect(screen.getByText(/Status: OPEN/)).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Reopen" })).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Resolve" })).toBeInTheDocument();
  });

  it("expands one occurrence and keeps framework frames behind technical disclosure", async () => {
    mockData();
    await openDetail();
    const occurrenceButton = screen.getByRole("button", { name: /Attempt 3/ });
    expect(occurrenceButton).toHaveAttribute("aria-expanded", "false");
    expect(screen.queryByText("analysis-high")).not.toBeInTheDocument();
    fireEvent.click(occurrenceButton);
    expect(occurrenceButton).toHaveAttribute("aria-expanded", "true");
    expect(screen.getByText("analysis-high")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /Technical details/ }));
    expect(screen.getByText(/ConfiguredLlmProvider.php/)).toBeInTheDocument();
    expect(screen.getByText(/VacancyAnalysisService.php/)).toBeInTheDocument();
    const framework = screen.getByText("Show framework frames (1)").closest("details");
    expect(framework).not.toHaveAttribute("open");
    expect(screen.getByText(/ControllerDispatcher.php/)).not.toBeVisible();
  });

  it("copies safe IDs and updates only available status actions", async () => {
    const writeText = vi.fn().mockResolvedValue(undefined);
    Object.defineProperty(navigator, "clipboard", { configurable: true, value: { writeText } });
    const fetchMock = mockData();
    await openDetail();
    fireEvent.click(screen.getByRole("button", { name: "Copy request ID" }));
    await waitFor(() => expect(writeText).toHaveBeenCalledWith(occurrence.request_id));
    expect(screen.getByRole("status")).toHaveTextContent("Request copied");
    fireEvent.click(screen.getByRole("button", { name: "Copy diagnostic summary" }));
    await waitFor(() => expect(writeText).toHaveBeenCalledWith(expect.stringContaining("Error: LLM_PROVIDER_UNAVAILABLE")));
    expect(String(writeText.mock.lastCall?.[0])).not.toContain("LlmProviderException");
    fireEvent.click(screen.getByRole("button", { name: "Resolve" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith(expect.stringContaining("/incident-1"), expect.objectContaining({ method: "PATCH" })));
    expect(await screen.findByText(/Status: RESOLVED/)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Reopen" })).toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Resolve" })).not.toBeInTheDocument();
  });

  it.each([
    ["LLM_PROVIDER_CONFIGURATION", false, "Provider configuration required", "No"],
    ["LLM_PROVIDER_RATE_LIMITED", true, "Provider rate limited", "Yes"],
    ["LLM_OUTPUT_INVALID", false, "Generated output could not be validated", "No"],
  ])("shows deterministic %s diagnosis", async (code, retryable, cause, answer) => {
    const variant = { ...incident, error_code: code, retryable };
    mockData([variant], variant);
    await openDetail();
    expect(screen.getByRole("region", { name: "Diagnosis and next action" })).toHaveTextContent(cause);
    expect(screen.getByRole("region", { name: "Diagnosis and next action" })).toHaveTextContent(`Retryable${answer}`);
  });

  it("offers retry only for retryable diagnostics load errors", async () => {
    let retryable = false;
    vi.spyOn(globalThis, "fetch").mockImplementation(async () => Response.json({ error: { code: "INTERNAL_ERROR", request_id: "req_list", retryable } }, { status: 503 }));
    render(<Diagnostics />);
    expect(await screen.findByRole("alert")).toHaveTextContent("Incident list could not be loaded.");
    expect(screen.queryByRole("button", { name: "Retry" })).not.toBeInTheDocument();
    retryable = true;
    fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    expect(await screen.findByRole("button", { name: "Retry" })).toBeInTheDocument();
  });

  it("applies provider with status and search, keeps it for paging, and clears its chip", async () => {
    const fetchMock = mockData();
    render(<Diagnostics />);
    await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    fireEvent.click(screen.getByRole("button", { name: "More filters" }));
    fireEvent.change(screen.getByLabelText("provider"), { target: { value: "OpenAI" } });
    fireEvent.change(screen.getByLabelText("Status"), { target: { value: "OPEN" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await screen.findByRole("button", { name: "Remove provider filter" });
    fireEvent.change(screen.getByLabelText("Search incidents"), { target: { value: "req_123" } });
    fireEvent.click(screen.getByRole("button", { name: "Search" }));
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith(expect.stringContaining("provider=OpenAI&status=OPEN&search=req_123"), expect.anything()));
    fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith(expect.stringContaining("provider=OpenAI&status=OPEN&search=req_123"), expect.anything()));
    fireEvent.click(screen.getByRole("button", { name: "Remove provider filter" }));
    await waitFor(() => expect(fetchMock).toHaveBeenLastCalledWith(expect.not.stringContaining("provider="), expect.anything()));
  });

  it("retries a failed detail GET with the same ID and restores heading focus", async () => {
    let attempts = 0;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path.includes("incidents?")) return Response.json({ data: { data: [incident], current_page: 1, last_page: 1, total: 1 } });
      if (path.endsWith("/incident-1")) return ++attempts === 1
        ? Response.json({ error: { code: "INTERNAL_ERROR", retryable: true } }, { status: 503 })
        : Response.json({ data: { incident, occurrences: [occurrence] } });
      throw new Error(path);
    });
    render(<Diagnostics />);
    const row = await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    row.focus(); fireEvent.click(row);
    expect(await screen.findByRole("alert")).toHaveTextContent("Incident details could not be loaded.");
    expect(screen.getByRole("button", { name: /All incidents/ })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    const heading = await screen.findByRole("heading", { name: "Vacancy analysis failed" });
    await waitFor(() => expect(heading).toHaveFocus());
    expect(fetchMock.mock.calls.filter(([input]) => String(input).endsWith("/incident-1"))).toHaveLength(2);
    expect(fetchMock.mock.calls.filter(([input]) => String(input).includes("incidents?"))).toHaveLength(1);
  });

  it("locks status actions during PATCH and retries only the failed transition", async () => {
    let finish!: (response: Response) => void;
    let patchCount = 0;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path.includes("incidents?")) return Response.json({ data: { data: [incident], current_page: 1, last_page: 1, total: 1 } });
      if (options?.method === "PATCH") {
        patchCount++;
        if (patchCount === 1) return new Promise<Response>((resolve) => { finish = resolve; });
        return Response.json({ data: { incident: { ...incident, status: "RESOLVED" }, occurrences: [occurrence] } });
      }
      return Response.json({ data: { incident, occurrences: [occurrence] } });
    });
    await openDetail();
    fireEvent.click(screen.getByRole("button", { name: "Resolve" }));
    fireEvent.click(screen.getByRole("button", { name: "Resolve" }));
    expect(patchCount).toBe(1);
    expect(screen.getByRole("button", { name: "Resolve" })).toBeDisabled();
    expect(screen.getByText("Saving…")).toBeInTheDocument();
    finish(Response.json({ error: { code: "INTERNAL_ERROR", retryable: true } }, { status: 503 }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Status change to RESOLVED failed.");
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    expect(await screen.findByText(/Status: RESOLVED/)).toBeInTheDocument();
    expect(screen.getByText("Status changed to RESOLVED.")).toHaveAttribute("role", "status");
    expect(fetchMock.mock.calls.filter(([, options]) => options?.method === "PATCH")).toHaveLength(2);
    expect(fetchMock.mock.calls.filter(([input]) => String(input).endsWith("/incident-1") && !String(input).includes("incidents?"))).toHaveLength(3);
  });

  it.each(["runtime", "rejection", "render"])("labels browser %s without inventing a cause", async (kind) => {
    const browser = { ...incident, error_code: "FRONTEND_RUNTIME_ERROR", latest_operation: kind,
      recovery_action: "Check the affected route and occurrence reference. Review browser logs if the failure repeats." };
    mockData([browser], browser);
    render(<Diagnostics />);
    const label = ({ runtime: "Browser runtime error", rejection: "Unhandled promise rejection", render: "React render failure" } as Record<string, string>)[kind];
    fireEvent.click(await screen.findByRole("button", { name: new RegExp(label) }));
    const decision = await screen.findByRole("region", { name: "Diagnosis and next action" });
    expect(decision).toHaveTextContent("root cause was not captured");
    expect(decision).toHaveTextContent("Check the affected route and occurrence reference");
  });

  it("shows recorded retry delay and accessible occurrence date", async () => {
    const limited = { ...incident, error_code: "LLM_PROVIDER_RATE_LIMITED" };
    mockData([limited], limited, { ...occurrence, retry_after_seconds: 42 });
    render(<Diagnostics />);
    fireEvent.click(await screen.findByRole("button", { name: /Vacancy analysis failed/ }));
    expect(await screen.findByText("Retry after 42 seconds.")).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Diagnosis and next action" })).toHaveTextContent("Wait at least 42 seconds, then retry the operation.");
    const time = screen.getByRole("button", { name: /Request/ }).querySelector("time");
    expect(time).toHaveTextContent(/28 Sep/);
    expect(time).toHaveAttribute("aria-label", expect.stringContaining("2026"));
  });

  it("does not invent an unrecorded retry delay", async () => {
    const limited = { ...incident, error_code: "LLM_PROVIDER_RATE_LIMITED" };
    mockData([limited], limited);
    await openDetail();
    expect(screen.getByText("Retry delay was not recorded.")).toBeInTheDocument();
    expect(screen.getByText("Check provider status and wait before retrying; no delay was recorded.")).toBeInTheDocument();
  });

  it("keeps retrying a failed PATCH without falling through to detail GET", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      if (String(input).includes("incidents?")) return Response.json({ data: { data: [incident], current_page: 1, last_page: 1, total: 1 } });
      if (options?.method === "PATCH") return Response.json({ error: { code: "INTERNAL_ERROR", retryable: true } }, { status: 503 });
      return Response.json({ data: { incident, occurrences: [occurrence] } });
    });
    await openDetail();
    fireEvent.click(screen.getByRole("button", { name: "Ignore" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Status change to IGNORED failed.");
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    await waitFor(() => expect(fetchMock.mock.calls.filter(([, options]) => options?.method === "PATCH")).toHaveLength(2));
    expect(screen.getByRole("alert")).toHaveTextContent("Status change to IGNORED failed.");
    expect(fetchMock.mock.calls.filter(([input, options]) => String(input).endsWith("/incident-1") && options?.method !== "PATCH")).toHaveLength(1);
  });

  it("announces result count and supports ignore then reopen", async () => {
    const fetchMock = mockData();
    render(<Diagnostics />);
    expect(await screen.findByRole("status", { name: "" })).toHaveTextContent("1 matching incidents");
    fireEvent.click(screen.getByRole("button", { name: /Vacancy analysis failed/ }));
    await screen.findByRole("heading", { name: "Vacancy analysis failed" });
    fireEvent.click(screen.getByRole("button", { name: "Ignore" }));
    expect(await screen.findByText("Status changed to IGNORED.")).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Reopen" }));
    expect(await screen.findByText("Status changed to OPEN.")).toBeInTheDocument();
    expect(fetchMock.mock.calls.filter(([, options]) => options?.method === "PATCH")).toHaveLength(2);
  });

  it("hides admin diagnostics from a normal user at the dedicated route", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      if (String(input) === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "user@example.test", role: "user", status: "ACTIVE" } });
      throw new Error(String(input));
    });
    render(<AccessShell destination="diagnostics" />);
    expect(await screen.findByRole("heading", { name: "Access denied" })).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalledWith(expect.stringContaining("/diagnostics/incidents"), expect.anything());
  });

  it("keeps page error copy safe", () => {
    const reset = vi.fn();
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(null, { status: 401 }));
    render(<ErrorPage error={new Error("SECRET_CANARY")} reset={reset} />);
    expect(screen.getByText("Page unavailable")).toBeInTheDocument();
    expect(screen.queryByText("SECRET_CANARY")).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Retry" }));
    expect(reset).toHaveBeenCalledOnce();
  });
});

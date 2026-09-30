import { act, cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { ApiError, api } from "../access-shell";
import AccessShell from "../access-shell";
import Diagnostics from "../diagnostics";
import ErrorPage from "../error";
import { ApplicationDraftPanel } from "../vacancy-workspace";

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
function deferred<T>() {
  let resolve!: (value: T) => void;
  const promise = new Promise<T>((finish) => { resolve = finish; });
  return { promise, resolve };
}
function listResponse(items: (Omit<typeof incident, "latest_operation" | "latest_provider"> & { latest_operation?: string | null; latest_provider?: string | null })[], page = 1, lastPage = 1, total = lastPage > 1 ? 26 : items.length) {
  return Response.json({ data: { data: items, current_page: page, last_page: lastPage, total } });
}
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

  it("preserves only a bounded numeric Retry-After for retryable API failures", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json(
      { error: { code: "LLM_PROVIDER_UNAVAILABLE", retryable: true } },
      { status: 503, headers: { "Retry-After": "86400" } },
    ));
    const failure = await api("/api/v1/applications/generate").catch((error) => error);
    expect(failure).toMatchObject({ retryable: true, retryAfterSeconds: 86400 });
    expect(failure.message).toContain("You can retry after 86400 seconds.");

    vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json(
      { error: { code: "LLM_PROVIDER_UNAVAILABLE", retryable: true } },
      { status: 503, headers: { "Retry-After": "86401" } },
    ));
    const invalid = await api("/api/v1/applications/generate").catch((error) => error);
    expect(invalid).toMatchObject({ retryAfterSeconds: null });
    expect(invalid.message).toBe("The analysis service is temporarily unavailable. You can retry.");
  });

  it("blocks synchronous draft generation until Retry-After expires", async () => {
    const preparation = { id: "prep-1", vacancy_id: "vacancy-1", status: "DRAFT", stale: false, items: [] };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/vacancies/vacancy-1/preparation" && options?.method === "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/preparations/prep-1" && options?.method !== "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/preparations/prep-1/generate" && options?.method === "POST") {
        return Response.json(
          { error: { code: "LLM_PROVIDER_UNAVAILABLE", retryable: true } },
          { status: 503, headers: { "Retry-After": "86400" } },
        );
      }
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<ApplicationDraftPanel vacancyId="vacancy-1" vacancyTitle="Backend Engineer" />);
    const generate = await screen.findByRole("button", { name: "Generate recommendations and cover drafts" });
    fireEvent.click(generate);

    expect(await screen.findByRole("alert")).toHaveTextContent("You can retry after 86400 seconds.");
    expect(generate).toBeDisabled();
    expect(screen.getByRole("status")).toHaveTextContent("Wait at least 24 hours before retrying a provider-backed action.");
    expect(fetchMock.mock.calls.filter(([input]) => String(input).endsWith("/generate"))).toHaveLength(1);
  });

  it.each([
    { action: "edit", status: "DRAFT", button: "Save edit and revalidate" },
    { action: "accept", status: "DRAFT", button: "Accept draft" },
    { action: "approve", status: "ACCEPTED", button: "Explicitly approve content" },
  ])("honors Retry-After after the $action action and leaves Reject available", async ({ action, status, button }) => {
    const item = {
      id: "draft-1", kind: "COVER_DRAFT", variant: "SHORT", section: null, before: null,
      content: "Draft copy.", reason: null, risk: null, status, revision_number: 1,
      validation_result: "PASS", claim_usages: [], revisions: [], approvals: [],
    };
    const preparation = { id: "prep-1", vacancy_id: "vacancy-1", status: "DRAFT", stale: false, items: [item] };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/vacancies/vacancy-1/preparation" && options?.method === "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/preparations/prep-1" && options?.method !== "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/draft-items/draft-1" && options?.method === "PATCH") {
        return Response.json({ error: { code: "LLM_PROVIDER_UNAVAILABLE", retryable: true } }, { status: 503, headers: { "Retry-After": "42" } });
      }
      if (path === "/api/v1/applications/draft-items/draft-1/approve" && options?.method === "POST") {
        return Response.json({ error: { code: "LLM_PROVIDER_UNAVAILABLE", retryable: true } }, { status: 503, headers: { "Retry-After": "42" } });
      }
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<ApplicationDraftPanel vacancyId="vacancy-1" vacancyTitle="Backend Engineer" />);
    await screen.findByRole("button", { name: button });
    if (action === "edit") fireEvent.change(screen.getByLabelText("Draft content"), { target: { value: "Edited draft copy." } });
    const providerAction = screen.getByRole("button", { name: button });
    expect(providerAction).toBeEnabled();
    fireEvent.click(providerAction);

    expect(await screen.findByRole("alert")).toHaveTextContent("You can retry after 42 seconds.");
    expect(providerAction).toBeDisabled();
    if (action === "edit") fireEvent.change(screen.getByLabelText("Draft content"), { target: { value: "Draft copy." } });
    expect(screen.getByRole("button", { name: "Reject" })).toBeEnabled();
    expect(screen.getByRole("status")).toHaveTextContent("Wait at least 42 seconds before retrying a provider-backed action.");
    expect(fetchMock.mock.calls.filter(([input]) => String(input).includes("draft-1"))).toHaveLength(1);
  });

  it("shares a provider-action cooldown and re-enables draft actions when it expires", async () => {
    const item = {
      id: "draft-1", kind: "COVER_DRAFT", variant: "SHORT", section: null, before: null,
      content: "Draft copy.", reason: null, risk: null, status: "DRAFT", revision_number: 1,
      validation_result: "PASS", claim_usages: [], revisions: [], approvals: [],
    };
    const preparation = { id: "prep-1", vacancy_id: "vacancy-1", status: "DRAFT", stale: false, items: [item] };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/vacancies/vacancy-1/preparation" && options?.method === "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/preparations/prep-1" && options?.method !== "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/draft-items/draft-1" && options?.method === "PATCH") {
        return Response.json({ error: { code: "LLM_PROVIDER_UNAVAILABLE", retryable: true } }, { status: 503, headers: { "Retry-After": "3" } });
      }
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<ApplicationDraftPanel vacancyId="vacancy-1" vacancyTitle="Backend Engineer" />);
    await screen.findByRole("button", { name: "Save edit and revalidate" });
    fireEvent.change(screen.getByLabelText("Draft content"), { target: { value: "Edited draft copy." } });
    const saveEdit = screen.getByRole("button", { name: "Save edit and revalidate" });
    vi.useFakeTimers();
    await act(async () => {
      fireEvent.click(saveEdit);
      await Promise.resolve();
      await Promise.resolve();
      await Promise.resolve();
    });

    expect(screen.getByRole("alert")).toHaveTextContent("You can retry after 3 seconds.");
    fireEvent.change(screen.getByLabelText("Draft content"), { target: { value: "Draft copy." } });
    const accept = screen.getByRole("button", { name: "Accept draft" });
    expect(saveEdit).toBeDisabled();
    expect(accept).toBeDisabled();
    expect(screen.getByRole("button", { name: "Reject" })).toBeEnabled();
    expect(screen.getByRole("status")).toHaveTextContent("Wait at least 3 seconds before retrying a provider-backed action.");

    await act(async () => { await vi.advanceTimersByTimeAsync(3_000); });
    vi.useRealTimers();

    expect(screen.queryByText(/Wait at least/)).not.toBeInTheDocument();
    expect(accept).toBeEnabled();
    fireEvent.change(screen.getByLabelText("Draft content"), { target: { value: "Another edited draft." } });
    expect(saveEdit).toBeEnabled();
    expect(fetchMock.mock.calls.filter(([input]) => String(input).includes("draft-1"))).toHaveLength(1);
  });

  it("does not start a cooldown for a non-retryable API error", async () => {
    const item = {
      id: "draft-1", kind: "COVER_DRAFT", variant: "SHORT", section: null, before: null,
      content: "Draft copy.", reason: null, risk: null, status: "DRAFT", revision_number: 1,
      validation_result: "PASS", claim_usages: [], revisions: [], approvals: [],
    };
    const preparation = { id: "prep-1", vacancy_id: "vacancy-1", status: "DRAFT", stale: false, items: [item] };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/vacancies/vacancy-1/preparation" && options?.method === "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/preparations/prep-1" && options?.method !== "POST") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/draft-items/draft-1" && options?.method === "PATCH") {
        return Response.json({ error: { code: "LLM_PROVIDER_CONFIGURATION", retryable: false } }, { status: 503, headers: { "Retry-After": "42" } });
      }
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<ApplicationDraftPanel vacancyId="vacancy-1" vacancyTitle="Backend Engineer" />);
    await screen.findByRole("button", { name: "Save edit and revalidate" });
    fireEvent.change(screen.getByLabelText("Draft content"), { target: { value: "Edited draft copy." } });
    const saveEdit = screen.getByRole("button", { name: "Save edit and revalidate" });
    fireEvent.click(saveEdit);

    expect(await screen.findByRole("alert")).not.toHaveTextContent("You can retry after");
    expect(screen.queryByText(/Wait at least/)).not.toBeInTheDocument();
    expect(saveEdit).toBeEnabled();
    expect(fetchMock.mock.calls.filter(([input]) => String(input).includes("draft-1"))).toHaveLength(1);
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

  it("returns to the new last page after resolving the final filtered incident", async () => {
    const finalIncident = { ...incident, id: "incident-last" };
    let pageTwoRequests = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const url = new URL(String(input), "http://localhost");
      if (url.pathname.endsWith("/diagnostics/incidents")) {
        const page = Number(url.searchParams.get("page") ?? 1);
        if (page === 2 && url.searchParams.get("status") === "OPEN") {
          pageTwoRequests++;
          return pageTwoRequests === 1
            ? listResponse([finalIncident], 2, 2, 26)
            : listResponse([], 2, 1, 25);
        }
        if (url.searchParams.get("status") === "OPEN" && pageTwoRequests > 1) return listResponse([incident], 1, 1, 25);
        return listResponse([incident], 1, 2, 26);
      }
      if (url.pathname.endsWith("/incident-last") && options?.method === "PATCH") {
        return Response.json({ data: { incident: { ...finalIncident, status: "RESOLVED" }, occurrences: [occurrence] } });
      }
      if (url.pathname.endsWith("/incident-last")) {
        return Response.json({ data: { incident: finalIncident, occurrences: [occurrence] } });
      }
      throw new Error(String(input));
    });

    render(<Diagnostics />);
    await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    fireEvent.click(screen.getByRole("button", { name: "More filters" }));
    fireEvent.change(screen.getByLabelText("Status"), { target: { value: "OPEN" } });
    fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
    await screen.findByRole("button", { name: "Next" });
    fireEvent.click(screen.getByRole("button", { name: "Next" }));
    await screen.findByText(/Page 2 of 2/);
    fireEvent.click(await screen.findByRole("button", { name: /Vacancy analysis failed/ }));
    fireEvent.click(await screen.findByRole("button", { name: "Resolve" }));
    await screen.findByText(/Status: RESOLVED/);
    fireEvent.click(screen.getByRole("button", { name: /All incidents/ }));

    expect(await screen.findByText(/Page 1 of 1 · 25 incidents/)).toBeInTheDocument();
    expect(await screen.findByRole("button", { name: /Vacancy analysis failed/ })).toBeInTheDocument();
    expect(screen.queryByText(/Page 2 of 1/)).not.toBeInTheDocument();
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

  it("matches advanced filter input limits to diagnostics API validation", async () => {
    mockData();
    render(<Diagnostics />);
    await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    fireEvent.click(screen.getByRole("button", { name: "More filters" }));
    for (const [field, limit] of Object.entries({
      provider: 64, service: 32, environment: 32, error_code: 96,
      request_id: 128, job_id: 128, llm_run_id: 26, application_id: 26,
    })) {
      expect(screen.getByLabelText(field.replaceAll("_", " "))).toHaveAttribute("maxLength", String(limit));
    }
  });

  it.each(["reload", "sort", "filter", "pagination"])("keeps the latest %s list request when an older response arrives later", async (action) => {
    const slow = deferred<Response>();
    const stale = { ...incident, id: "stale", latest_provider: "Stale" };
    const fresh = { ...incident, id: "fresh", latest_provider: "Fresh" };
    let requests = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      if (!String(input).includes("incidents?")) throw new Error(String(input));
      requests++;
      if (requests === 1) return listResponse([incident], 1, action === "pagination" ? 2 : 1);
      if (requests === 2) return slow.promise;
      return listResponse([fresh], 1, action === "pagination" ? 2 : 1);
    });
    render(<Diagnostics />);
    await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    if (action === "reload") {
      fireEvent.click(screen.getByRole("button", { name: "Reload" }));
      fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    } else if (action === "sort") {
      fireEvent.change(screen.getByLabelText("Sort"), { target: { value: "severity" } });
      fireEvent.change(screen.getByLabelText("Sort"), { target: { value: "last_seen" } });
    } else if (action === "filter") {
      fireEvent.change(screen.getByLabelText("Status"), { target: { value: "OPEN" } });
      fireEvent.click(screen.getByRole("button", { name: "Apply filters" }));
      fireEvent.click(screen.getByRole("button", { name: "Clear all" }));
    } else {
      fireEvent.click(screen.getByRole("button", { name: "Next" }));
      fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    }
    await waitFor(() => expect(requests).toBe(3));
    const row = await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    expect(row).toHaveTextContent("Fresh");
    await act(async () => { slow.resolve(listResponse([stale], action === "pagination" ? 2 : 1)); await slow.promise; });
    expect(screen.getByRole("button", { name: /Vacancy analysis failed/ })).toHaveTextContent("Fresh");
    expect(screen.queryByText("Stale")).not.toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
    if (action === "sort") {
      expect(screen.getByLabelText("Sort")).toHaveValue("last_seen");
      expect(screen.getByRole("button", { name: "Remove sort filter" })).toHaveTextContent("last_seen");
    }
    if (action === "filter") expect(screen.queryByRole("button", { name: "Remove status filter" })).not.toBeInTheDocument();
    if (action === "pagination") expect(screen.getByText(/Page 1 of 2/)).toBeInTheDocument();
  });

  it("does not show a superseded list failure", async () => {
    const slow = deferred<Response>();
    let requests = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation(async () => {
      requests++;
      if (requests === 1) return listResponse([incident]);
      if (requests === 2) return slow.promise;
      return listResponse([{ ...incident, latest_provider: "Fresh" }]);
    });
    render(<Diagnostics />);
    await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    fireEvent.click(screen.getByRole("button", { name: "Reload" }));
    await waitFor(() => expect(screen.getByRole("button", { name: /Vacancy analysis failed/ })).toHaveTextContent("Fresh"));
    await act(async () => { slow.resolve(Response.json({ error: { code: "INTERNAL_ERROR", retryable: true } }, { status: 503 })); await slow.promise; });
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  it.each([false, true])("keeps detail B when slow detail A finishes after a new selection (failure=%s)", async (failure) => {
    const slow = deferred<Response>();
    const other = { ...incident, id: "incident-2", error_code: "QUEUE_JOB_FAILED", latest_operation: null, latest_provider: null };
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path.includes("incidents?")) return listResponse([incident, other]);
      if (path.endsWith("/incident-1")) return slow.promise;
      if (path.endsWith("/incident-2")) return Response.json({ data: { incident: other, occurrences: [{ ...occurrence, id: "event-2", operation: null }] } });
      throw new Error(path);
    });
    render(<Diagnostics />);
    fireEvent.click(await screen.findByRole("button", { name: /Vacancy analysis failed/ }));
    expect(screen.getByText(/Loading incident details for incident-1/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: /All incidents/ }));
    fireEvent.click(await screen.findByRole("button", { name: /Background job failed/ }));
    expect(await screen.findByRole("heading", { name: "Background job failed" })).toBeInTheDocument();
    await act(async () => { slow.resolve(failure
      ? Response.json({ error: { code: "INTERNAL_ERROR", retryable: true } }, { status: 503 })
      : Response.json({ data: { incident, occurrences: [occurrence] } })); await slow.promise; });
    expect(screen.getByRole("heading", { name: "Background job failed" })).toBeInTheDocument();
    expect(screen.queryByRole("heading", { name: "Vacancy analysis failed" })).not.toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  it("does not reopen detail after returning to the list", async () => {
    const slow = deferred<Response>();
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => String(input).includes("incidents?") ? listResponse([incident]) : slow.promise);
    render(<Diagnostics />);
    fireEvent.click(await screen.findByRole("button", { name: /Vacancy analysis failed/ }));
    fireEvent.click(screen.getByRole("button", { name: /All incidents/ }));
    await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    await act(async () => { slow.resolve(Response.json({ data: { incident, occurrences: [occurrence] } })); await slow.promise; });
    expect(screen.queryByRole("heading", { name: "Vacancy analysis failed" })).not.toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
  });

  it.each([true, false])("returns keyboard focus to the row when present=%s", async (present) => {
    let lists = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      if (String(input).includes("incidents?")) return listResponse(++lists === 1 || present ? [incident] : []);
      return Response.json({ data: { incident, occurrences: [occurrence] } });
    });
    render(<Diagnostics />);
    const row = await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    row.focus(); fireEvent.click(row, { detail: 0 });
    await screen.findByRole("heading", { name: "Vacancy analysis failed" });
    const back = screen.getByRole("button", { name: /All incidents/ });
    back.focus(); fireEvent.click(back, { detail: 0 });
    if (present) await waitFor(() => expect(screen.getByRole("button", { name: /Vacancy analysis failed/ })).toHaveFocus());
    else await waitFor(() => expect(screen.getByRole("heading", { name: "Incidents" })).toHaveFocus());
    expect(document.activeElement).not.toBe(document.body);
  });

  it("does not force return focus after a mouse click", async () => {
    mockData();
    await openDetail();
    const back = screen.getByRole("button", { name: /All incidents/ });
    back.focus(); fireEvent.click(back, { detail: 1 });
    const row = await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    expect(row).not.toHaveFocus();
    expect(screen.getByRole("heading", { name: "Incidents" })).not.toHaveFocus();
  });

  it.each([false, true])("does not let a pending status mutation reopen detail after returning (failure=%s)", async (failure) => {
    const patch = deferred<Response>();
    let lists = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      if (options?.method === "PATCH") return patch.promise;
      if (String(input).includes("incidents?")) { lists++; return listResponse([incident]); }
      return Response.json({ data: { incident, occurrences: [occurrence] } });
    });
    await openDetail();
    fireEvent.click(screen.getByRole("button", { name: "Resolve" }));
    fireEvent.click(screen.getByRole("button", { name: /All incidents/ }));
    await screen.findByRole("button", { name: /Vacancy analysis failed/ });
    await act(async () => { patch.resolve(failure
      ? Response.json({ error: { code: "INTERNAL_ERROR", retryable: true } }, { status: 503 })
      : Response.json({ data: { incident: { ...incident, status: "RESOLVED" }, occurrences: [occurrence] } })); await patch.promise; });
    if (failure) expect(lists).toBe(2);
    else await waitFor(() => expect(lists).toBe(3));
    expect(screen.queryByRole("heading", { name: "Vacancy analysis failed" })).not.toBeInTheDocument();
    expect(screen.queryByRole("alert")).not.toBeInTheDocument();
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
    expect(screen.getByRole("alert")).toHaveFocus();
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

  it("shows a recorded temporary provider retry delay", async () => {
    mockData([incident], incident, { ...occurrence, retry_after_seconds: 41 });
    await openDetail();
    expect(screen.getByText("Retry after 41 seconds.")).toBeInTheDocument();
    expect(screen.getByRole("region", { name: "Diagnosis and next action" })).toHaveTextContent("Wait at least 41 seconds, then retry the operation.");
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
    expect(await screen.findByText("1 matching incidents")).toBeInTheDocument();
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

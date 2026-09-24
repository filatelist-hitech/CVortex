import { act, cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { StrictMode } from "react";
import { afterEach, describe, expect, it, vi } from "vitest";
import Home from "../page";
import RegisterPage from "../register/page";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  window.history.replaceState(null, "", "/");
});

describe("access shell", () => {
  it("renders the approved brand and sign-in form", () => {
    render(<Home />);

    expect(screen.getByRole("heading", { name: "CVortex" })).toBeInTheDocument();
    expect(screen.getByText("Your career, in context.")).toBeInTheDocument();
    expect(screen.getAllByRole("button", { name: "Sign in" })).toHaveLength(2);
    expect(screen.getByLabelText("Email")).toBeInTheDocument();
  });

  it("consumes a registration fragment, removes it from history, and submits it only in the registration body", async () => {
    window.history.replaceState(null, "", "/register#token=fragment-secret");
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/me") return new Response(null, { status: 401 });
      if (path === "/sanctum/csrf-cookie") return new Response(null, { status: 204 });
      if (path === "/api/v1/auth/register") return Response.json({ data: { id: "01JTEST", email: "invitee@example.test", role: "user", status: "ACTIVE" } }, { status: 201 });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<StrictMode><RegisterPage /></StrictMode>);

    await waitFor(() => expect(window.location.pathname).toBe("/register"));
    expect(window.location.hash).toBe("");
    expect(window.location.search).toBe("");
    expect(window.location.toString()).not.toContain("fragment-secret");
    expect(screen.getByRole("button", { name: "Create account" })).toBeEnabled();

    fireEvent.change(screen.getByLabelText("Email"), { target: { value: "invitee@example.test" } });
    fireEvent.change(screen.getByLabelText("Password"), { target: { value: "a very long safe passphrase" } });
    fireEvent.change(screen.getByLabelText("Confirm password"), { target: { value: "a very long safe passphrase" } });
    fireEvent.click(screen.getByRole("button", { name: "Create account" }));

    await waitFor(() => expect(screen.getByText("invitee@example.test")).toBeInTheDocument());
    const registrationRequest = fetchMock.mock.calls.find(([input]) => String(input) === "/api/v1/auth/register");
    expect(registrationRequest).toBeDefined();
    expect(JSON.parse(String(registrationRequest?.[1]?.body))).toMatchObject({ invitation_token: "fragment-secret" });
    expect(window.location.toString()).not.toContain("fragment-secret");
    expect(new URL(window.location.href).searchParams.toString()).not.toContain("fragment-secret");

  });

  it("renders pending evidence distinctly and sends explicit human review actions", async () => {
    const overview = {
      facts: [{ id: "fact-1", fact_type: "skill", assertion_original: "Familiar with Laravel.", assertion_approved: null, source_excerpt: "Familiar with Laravel.", provenance_type: "paste_extraction", status: "PENDING" }],
      claims: [],
      sources: [{ id: "source-1", extraction_status: "COMPLETED", error_code: null }],
    };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "career@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: overview });
      if (path === "/api/v1/career/facts/fact-1/review" && options?.method === "PATCH") return Response.json({ data: { ...overview.facts[0], status: "CONFIRMED" } });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);

    expect(await screen.findByText("Pending confirmation")).toBeInTheDocument();
    expect(screen.getByText("Evidence available")).toBeInTheDocument();
    expect(screen.getByText("Claims appear only after explicit confirmation.")).toBeInTheDocument();
    expect(screen.queryByText("Confirmed", { selector: ".confirmed-badge" })).not.toBeInTheDocument();
    fireEvent.change(screen.getByLabelText("Edit before confirming"), { target: { value: "Human-approved Laravel familiarity." } });
    fireEvent.click(screen.getAllByRole("button", { name: "Edit and Confirm" })[0]);

    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith("/api/v1/career/facts/fact-1/review", expect.objectContaining({ method: "PATCH" })));
    const request = fetchMock.mock.calls.find(([input]) => String(input).includes("fact-1/review"));
    expect(JSON.parse(String(request?.[1]?.body))).toEqual({ action: "edit_confirm", assertion: "Human-approved Laravel familiarity." });
  });

  it("keeps manual entry available when extraction reports a provider error", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "career@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: { facts: [], claims: [], sources: [] } });
      if (path === "/api/v1/career/extractions") return Response.json({ message: "raw provider exception", error: { code: "PROVIDER_ERROR" } }, { status: 503 });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    await screen.findByText("No confirmed facts yet.");
    fireEvent.change(screen.getByLabelText("Career or resume text"), { target: { value: "Synthetic career text." } });
    fireEvent.click(screen.getByRole("button", { name: "Extract pending facts" }));

    expect(await screen.findByText("Career extraction is temporarily unavailable. Manual fact entry is still available.")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Add confirmed fact" })).toBeEnabled();
  });

  it("never renders an arbitrary backend exception message", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "career@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: { facts: [], claims: [], sources: [] } });
      if (path === "/api/v1/career/extractions") return Response.json({ message: "SQLSTATE private career text leaked here" }, { status: 500 });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    await screen.findByText("No confirmed facts yet.");
    fireEvent.change(screen.getByLabelText("Career or resume text"), { target: { value: "Synthetic career text." } });
    fireEvent.click(screen.getByRole("button", { name: "Extract pending facts" }));

    expect(await screen.findByText("Request failed. Please try again.")).toBeInTheDocument();
    expect(screen.queryByText(/SQLSTATE private/)).not.toBeInTheDocument();
  });

  it("executes the visible first-value extraction and review flow", async () => {
    type TestFact = { id: string; fact_type: string; assertion_original: string; assertion_approved: string | null; source_excerpt: string; provenance_type: "paste_extraction"; status: "PENDING" | "CONFIRMED" | "REJECTED" };
    const facts: TestFact[] = [];
    const claims: Array<{ id: string; statement: string; truth_status: "PASS" }> = [];
    const sources: Array<{ id: string; extraction_status: "COMPLETED"; error_code: null }> = [];
    let finishExtraction: (() => void) | undefined;
    const extractionGate = new Promise<void>((resolve) => { finishExtraction = resolve; });

    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "career@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: { facts, claims, sources } });
      if (path === "/api/v1/career/extractions") {
        await extractionGate;
        facts.push(
          { id: "confirm", fact_type: "experience", assertion_original: "Confirmed source.", assertion_approved: null, source_excerpt: "Confirmed source.", provenance_type: "paste_extraction", status: "PENDING" },
          { id: "edit", fact_type: "experience", assertion_original: "Editable source.", assertion_approved: null, source_excerpt: "Editable source.", provenance_type: "paste_extraction", status: "PENDING" },
          { id: "reject", fact_type: "experience", assertion_original: "Rejected source.", assertion_approved: null, source_excerpt: "Rejected source.", provenance_type: "paste_extraction", status: "PENDING" },
          { id: "pending", fact_type: "experience", assertion_original: "Still pending.", assertion_approved: null, source_excerpt: "Still pending.", provenance_type: "paste_extraction", status: "PENDING" },
        );
        sources.push({ id: "source", extraction_status: "COMPLETED", error_code: null });
        return Response.json({ data: sources[0] }, { status: 202 });
      }
      if (path.includes("/review")) {
        const id = path.split("/").at(-2);
        const fact = facts.find((candidate) => candidate.id === id);
        const body = JSON.parse(String(options?.body)) as { action: "confirm" | "edit_confirm" | "reject"; assertion?: string };
        if (!fact) throw new Error("Missing test fact");
        if (body.action === "reject") fact.status = "REJECTED";
        else {
          fact.status = "CONFIRMED";
          fact.assertion_approved = body.action === "edit_confirm" ? body.assertion ?? null : fact.assertion_original;
          claims.push({ id: `claim-${id}`, statement: fact.assertion_approved ?? fact.assertion_original, truth_status: "PASS" });
        }
        return Response.json({ data: fact });
      }
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    await screen.findByText("No pending candidates. Paste text above or add a manual fact.");
    fireEvent.change(screen.getByLabelText("Career or resume text"), { target: { value: "Synthetic multi-fact source." } });
    fireEvent.click(screen.getByRole("button", { name: "Extract pending facts" }));
    expect(await screen.findByRole("button", { name: "Extracting…" })).toBeDisabled();
    finishExtraction?.();

    expect(await screen.findByText("Latest extraction: COMPLETED")).toBeInTheDocument();
    expect(screen.getAllByText("Evidence available")).toHaveLength(4);
    fireEvent.click(screen.getAllByRole("button", { name: "Confirm" })[0]);
    await waitFor(() => expect(screen.getByText("Confirmed source.", { selector: ".confirmed-card .assertion" })).toBeInTheDocument());

    fireEvent.change(screen.getAllByLabelText("Edit before confirming")[0], { target: { value: "Human approved edit." } });
    fireEvent.click(screen.getAllByRole("button", { name: "Edit and Confirm" })[0]);
    await waitFor(() => expect(screen.getByText("Human approved edit.", { selector: ".confirmed-card .assertion" })).toBeInTheDocument());

    fireEvent.click(screen.getAllByRole("button", { name: "Reject" })[0]);
    await waitFor(() => expect(screen.queryByText("Rejected source.", { selector: ".pending-card .assertion" })).not.toBeInTheDocument());
    expect(screen.getByText("Still pending.", { selector: ".pending-card .assertion" })).toBeInTheDocument();
    expect(screen.getAllByText("PASS")).toHaveLength(2);
  });

  it("renders explainable vacancy analysis while keeping raw source text inert", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "vacancy@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: { facts: [], claims: [], sources: [] } });
      if (path === "/api/v1/vacancies") return Response.json({ data: [{ id: "vacancy-1", title: "Backend Engineer", company: "Example", source_url: "https://jobs.example.test/1", analysis_status: "COMPLETED", error_code: null, snapshot_version: 1, recommendation: "APPLY", analysis_stale: false }] });
      if (path === "/api/v1/vacancies/vacancy-1") return Response.json({ data: {
        id: "vacancy-1", title: "Backend Engineer", company: "Example", source_url: "https://jobs.example.test/1", source_type: "PASTED_TEXT", analysis_status: "COMPLETED", error_code: null, snapshot_version: 1, recommendation: "APPLY", analysis_stale: false,
        snapshot: { id: "snapshot-1", version: 1, raw_text: "<script>window.pwned=true</script> Laravel required.", source_url: "https://jobs.example.test/1", imported_at: "2026-09-19T00:00:00Z" },
        requirements: [{ id: "requirement-1", dimension: "TECHNICAL", importance: "MANDATORY", label: "Laravel", source_excerpt: "Laravel required.", confidence: 0.9 }],
        analysis: { recommendation: "APPLY", key_reasons: ["Technical: confirmed match."], material_gaps: [], uncertainties: [{ requirement_id: "r2", label: "Salary", reason: "candidate data is absent" }], stale: false, dimensions: [
          { dimension: "TECHNICAL", result: "MATCH", explanation: "Confirmed evidence supports Laravel.", origin: "DETERMINISTIC", vacancy_requirement_ids: ["requirement-1"], candidate_evidence: [{ type: "CareerFact", id: "fact-1", statement: "Laravel", status: "CONFIRMED", provenance_type: "user_manual" }] },
          ...["EXPERIENCE", "DOMAIN", "LANGUAGE", "LOCATION", "WORK_FORMAT", "SALARY"].map((dimension) => ({ dimension, result: "UNKNOWN", explanation: "Confirmed candidate data is insufficient.", origin: "DETERMINISTIC", vacancy_requirement_ids: [], candidate_evidence: [] })),
        ] },
      } });
      throw new Error(`Unexpected request: ${path}`);
    });

    const { container } = render(<Home />);

    expect(await screen.findByRole("heading", { name: "Should I apply?" })).toBeInTheDocument();
    expect(await screen.findByRole("heading", { name: "APPLY" })).toBeInTheDocument();
    expect(screen.getByText("This is a prioritization class, not an interview probability or ATS score.")).toBeInTheDocument();
    expect(screen.getByText("Confirmed evidence supports Laravel.")).toBeInTheDocument();
    expect(screen.getByText("CareerFact · CONFIRMED")).toBeInTheDocument();
    expect(screen.getByText("<script>window.pwned=true</script> Laravel required.")).toBeInTheDocument();
    expect(container.querySelector("script")).toBeNull();
  });

  it("retries a failed vacancy analysis even when no analysis payload exists", async () => {
    const failedDetail = {
      id: "vacancy-failed", title: "Backend Engineer", company: null, source_url: null, source_type: "PASTED_TEXT", analysis_status: "FAILED", error_code: "SEMANTIC_REJECTED", snapshot_version: 1, recommendation: null, analysis_stale: false,
      snapshot: { id: "snapshot-failed", version: 1, raw_text: "Laravel is required.", source_url: null, imported_at: "2026-09-21T00:00:00Z" }, requirements: [], analysis: null,
    };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "vacancy@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: { facts: [], claims: [], sources: [] } });
      if (path === "/api/v1/vacancies") return Response.json({ data: [{ id: "vacancy-failed", title: "Backend Engineer", company: null, source_url: null, analysis_status: "FAILED", error_code: "SEMANTIC_REJECTED", snapshot_version: 1, recommendation: null, analysis_stale: false }] });
      if (path === "/api/v1/vacancies/vacancy-failed") return Response.json({ data: failedDetail });
      if (path === "/api/v1/vacancies/vacancy-failed/reanalyze" && options?.method === "POST") return Response.json({ data: {} }, { status: 202 });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);

    fireEvent.click(await screen.findByRole("button", { name: "Retry analysis" }));
    await waitFor(() => expect(fetchMock).toHaveBeenCalledWith("/api/v1/vacancies/vacancy-failed/reanalyze", expect.objectContaining({ method: "POST" })));
  });

  it("selects the newly imported vacancy after refreshing the saved list", async () => {
    let poll: (() => void) | undefined;
    vi.spyOn(window, "setInterval").mockImplementation(((handler: TimerHandler, timeout?: number) => {
      if (timeout === 1500 && typeof handler === "function") poll = handler as () => void;
      return 1;
    }) as typeof window.setInterval);
    vi.spyOn(window, "clearInterval").mockImplementation(() => undefined);
    const oldSummary = { id: "vacancy-old", title: "Old vacancy", company: null, source_url: null, analysis_status: "PENDING" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false };
    const newSummary = { id: "vacancy-new", title: "New vacancy", company: null, source_url: null, analysis_status: "PENDING" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false };
    const summaries = [oldSummary, newSummary];
    const detail = (id: string) => ({
      ...summaries.find((item) => item.id === id)!, source_type: "PASTED_TEXT",
      snapshot: { id: `snapshot-${id}`, version: 1, raw_text: "Laravel required.", source_url: null, imported_at: "2026-09-22T00:00:00Z" }, requirements: [], analysis: null,
    });
    let resolvePoll!: (response: Response) => void;
    const pollList = new Promise<Response>((resolve) => { resolvePoll = resolve; });
    let listCalls = 0;
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/me") return Response.json({ data: { id: "user-1", email: "vacancy@example.test", role: "user", status: "ACTIVE" } });
      if (path === "/api/v1/career") return Response.json({ data: { facts: [], claims: [], sources: [] } });
      if (path === "/api/v1/vacancies" && !options?.method) {
        listCalls += 1;
        return listCalls === 1 ? Response.json({ data: [oldSummary] }) : listCalls === 2 ? pollList : Response.json({ data: summaries });
      }
      if (path === "/api/v1/vacancies" && options?.method === "POST") return Response.json({ data: { id: "vacancy-new" } }, { status: 202 });
      if (path === "/api/v1/vacancies/vacancy-old") return Response.json({ data: detail("vacancy-old") });
      if (path === "/api/v1/vacancies/vacancy-new") return Response.json({ data: detail("vacancy-new") });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    await screen.findByRole("button", { name: /Old vacancy/ });
    act(() => poll?.());
    await waitFor(() => expect(listCalls).toBe(2));
    fireEvent.change(screen.getByLabelText("Vacancy text"), { target: { value: "Laravel required." } });
    fireEvent.click(screen.getByRole("button", { name: "Preserve and analyze" }));

    expect(await screen.findByRole("heading", { name: "New vacancy" })).toBeInTheDocument();
    expect(fetchMock.mock.calls.some(([path]) => String(path) === "/api/v1/vacancies/vacancy-new")).toBe(true);
    await act(async () => { resolvePoll(Response.json({ data: [oldSummary] })); });
    expect(screen.getByRole("button", { name: /New vacancy/ })).toHaveClass("vacancy-selected");
    expect(screen.getByRole("heading", { name: "New vacancy" })).toBeInTheDocument();
  });

  it("keeps a later explicit choice when an earlier import response arrives", async () => {
    const summaries = [
      { id: "vacancy-a", title: "Vacancy A", company: null, source_url: null, analysis_status: "COMPLETED" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false },
      { id: "vacancy-b", title: "Vacancy B", company: null, source_url: null, analysis_status: "COMPLETED" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false },
      { id: "vacancy-new", title: "Vacancy New", company: null, source_url: null, analysis_status: "PENDING" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false },
    ];
    const detail = (id: string) => ({ ...summaries.find((item) => item.id === id)!, source_type: "PASTED_TEXT" as const,
      snapshot: { id: `snapshot-${id}`, version: 1, raw_text: "Laravel required.", source_url: null, imported_at: "2026-09-23T00:00:00Z" }, requirements: [], analysis: null });
    let resolveImport!: (response: Response) => void;
    const importResponse = new Promise<Response>((resolve) => { resolveImport = resolve; });
    let imported = false;
    vi.spyOn(globalThis, "fetch").mockImplementation((input, options) => {
      const path = String(input);
      if (path === "/api/v1/me") return Promise.resolve(Response.json({ data: { id: "user-1", email: "vacancy@example.test", role: "user", status: "ACTIVE" } }));
      if (path === "/api/v1/career") return Promise.resolve(Response.json({ data: { facts: [], claims: [], sources: [] } }));
      if (path === "/api/v1/vacancies" && options?.method === "POST") return importResponse;
      if (path === "/api/v1/vacancies") return Promise.resolve(Response.json({ data: imported ? summaries : summaries.slice(0, 2) }));
      if (path.startsWith("/api/v1/vacancies/")) return Promise.resolve(Response.json({ data: detail(path.split("/").at(-1)!) }));
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    fireEvent.click(await screen.findByRole("button", { name: /Vacancy A/ }));
    fireEvent.change(screen.getByLabelText("Vacancy text"), { target: { value: "Laravel required." } });
    fireEvent.click(screen.getByRole("button", { name: "Preserve and analyze" }));
    fireEvent.click(screen.getByRole("button", { name: /Vacancy B/ }));
    await screen.findByRole("heading", { name: "Vacancy B" });
    imported = true;
    await act(async () => { resolveImport(Response.json({ data: { id: "vacancy-new" } }, { status: 202 })); });

    expect(screen.getByRole("button", { name: /Vacancy B/ })).toHaveClass("vacancy-selected");
    expect(screen.getByRole("heading", { name: "Vacancy B" })).toBeInTheDocument();
  });

  it("keeps a newer explicit selection when a polling list request resolves", async () => {
    let poll: (() => void) | undefined;
    vi.spyOn(window, "setInterval").mockImplementation(((handler: TimerHandler, timeout?: number) => {
      if (timeout === 1500 && typeof handler === "function") poll = handler as () => void;
      return 1;
    }) as typeof window.setInterval);
    vi.spyOn(window, "clearInterval").mockImplementation(() => undefined);
    const summaries = [
      { id: "vacancy-a", title: "Vacancy A", company: null, source_url: null, analysis_status: "PENDING" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false },
      { id: "vacancy-b", title: "Vacancy B", company: null, source_url: null, analysis_status: "COMPLETED" as const, error_code: null, snapshot_version: 1, recommendation: "APPLY" as const, analysis_stale: false },
    ];
    const detail = (id: string) => ({
      ...summaries.find((item) => item.id === id)!, source_type: "PASTED_TEXT" as const,
      snapshot: { id: `snapshot-${id}`, version: 1, raw_text: "Laravel required.", source_url: null, imported_at: "2026-09-22T00:00:00Z" }, requirements: [], analysis: null,
    });
    let resolvePoll!: (response: Response) => void;
    const pollList = new Promise<Response>((resolve) => { resolvePoll = resolve; });
    let listCalls = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation((input) => {
      const path = String(input);
      if (path === "/api/v1/me") return Promise.resolve(Response.json({ data: { id: "user-1", email: "vacancy@example.test", role: "user", status: "ACTIVE" } }));
      if (path === "/api/v1/career") return Promise.resolve(Response.json({ data: { facts: [], claims: [], sources: [] } }));
      if (path === "/api/v1/vacancies") {
        listCalls += 1;
        return listCalls === 1 ? Promise.resolve(Response.json({ data: summaries })) : pollList;
      }
      if (path === "/api/v1/vacancies/vacancy-a") return Promise.resolve(Response.json({ data: detail("vacancy-a") }));
      if (path === "/api/v1/vacancies/vacancy-b") return Promise.resolve(Response.json({ data: detail("vacancy-b") }));
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    fireEvent.click(await screen.findByRole("button", { name: /Vacancy A/ }));
    act(() => poll?.());
    await waitFor(() => expect(listCalls).toBe(2));
    fireEvent.click(screen.getByRole("button", { name: /Vacancy B/ }));
    expect(await screen.findByRole("heading", { name: "Vacancy B" })).toBeInTheDocument();

    await act(async () => { resolvePoll(Response.json({ data: [summaries[0]] })); });

    expect(screen.getByRole("button", { name: /Vacancy B/ })).toHaveClass("vacancy-selected");
    expect(screen.getByRole("heading", { name: "Vacancy B" })).toBeInTheDocument();
  });

  it("ignores an older polling list response when refreshes overlap", async () => {
    let poll: (() => void) | undefined;
    vi.spyOn(window, "setInterval").mockImplementation(((handler: TimerHandler, timeout?: number) => {
      if (timeout === 1500 && typeof handler === "function") poll = handler as () => void;
      return 1;
    }) as typeof window.setInterval);
    vi.spyOn(window, "clearInterval").mockImplementation(() => undefined);
    const summaries = [
      { id: "vacancy-a", title: "Vacancy A", company: null, source_url: null, analysis_status: "PENDING" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false },
      { id: "vacancy-b", title: "Vacancy B", company: null, source_url: null, analysis_status: "COMPLETED" as const, error_code: null, snapshot_version: 1, recommendation: "APPLY" as const, analysis_stale: false },
    ];
    const detailB = { ...summaries[1], source_type: "PASTED_TEXT" as const, snapshot: { id: "snapshot-b", version: 1, raw_text: "Laravel required.", source_url: null, imported_at: "2026-09-22T00:00:00Z" }, requirements: [], analysis: null };
    let resolveOld!: (response: Response) => void;
    let resolveNew!: (response: Response) => void;
    const oldList = new Promise<Response>((resolve) => { resolveOld = resolve; });
    const newList = new Promise<Response>((resolve) => { resolveNew = resolve; });
    let listCalls = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation((input) => {
      const path = String(input);
      if (path === "/api/v1/me") return Promise.resolve(Response.json({ data: { id: "user-1", email: "vacancy@example.test", role: "user", status: "ACTIVE" } }));
      if (path === "/api/v1/career") return Promise.resolve(Response.json({ data: { facts: [], claims: [], sources: [] } }));
      if (path === "/api/v1/vacancies") {
        listCalls += 1;
        return listCalls === 1 ? Promise.resolve(Response.json({ data: summaries })) : listCalls === 2 ? oldList : newList;
      }
      if (path === "/api/v1/vacancies/vacancy-b") return Promise.resolve(Response.json({ data: detailB }));
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    await screen.findByRole("button", { name: /Vacancy A/ });
    await waitFor(() => expect(poll).toBeTypeOf("function"));
    act(() => { poll!(); poll!(); });
    await waitFor(() => expect(listCalls).toBe(3));
    fireEvent.click(screen.getByRole("button", { name: /Vacancy B/ }));
    await screen.findByRole("heading", { name: "Vacancy B" });

    await act(async () => { resolveNew(Response.json({ data: summaries })); });
    await act(async () => { resolveOld(Response.json({ data: [summaries[0]] })); });

    expect(screen.getByRole("button", { name: /Vacancy B/ })).toHaveClass("vacancy-selected");
    expect(screen.getByRole("heading", { name: "Vacancy B" })).toBeInTheDocument();
  });

  it("falls back when the selected vacancy disappears during a refresh", async () => {
    let poll: (() => void) | undefined;
    vi.spyOn(window, "setInterval").mockImplementation(((handler: TimerHandler, timeout?: number) => {
      if (timeout === 1500 && typeof handler === "function") poll = handler as () => void;
      return 1;
    }) as typeof window.setInterval);
    vi.spyOn(window, "clearInterval").mockImplementation(() => undefined);
    const summaries = [
      { id: "vacancy-a", title: "Vacancy A", company: null, source_url: null, analysis_status: "PENDING" as const, error_code: null, snapshot_version: 1, recommendation: null, analysis_stale: false },
      { id: "vacancy-b", title: "Vacancy B", company: null, source_url: null, analysis_status: "COMPLETED" as const, error_code: null, snapshot_version: 1, recommendation: "APPLY" as const, analysis_stale: false },
    ];
    const detail = (id: string) => ({ ...summaries.find((item) => item.id === id)!, source_type: "PASTED_TEXT" as const, snapshot: { id: `snapshot-${id}`, version: 1, raw_text: "Laravel required.", source_url: null, imported_at: "2026-09-22T00:00:00Z" }, requirements: [], analysis: null });
    let resolvePoll!: (response: Response) => void;
    const pollList = new Promise<Response>((resolve) => { resolvePoll = resolve; });
    let listCalls = 0;
    vi.spyOn(globalThis, "fetch").mockImplementation((input) => {
      const path = String(input);
      if (path === "/api/v1/me") return Promise.resolve(Response.json({ data: { id: "user-1", email: "vacancy@example.test", role: "user", status: "ACTIVE" } }));
      if (path === "/api/v1/career") return Promise.resolve(Response.json({ data: { facts: [], claims: [], sources: [] } }));
      if (path === "/api/v1/vacancies") {
        listCalls += 1;
        return listCalls === 1 ? Promise.resolve(Response.json({ data: summaries })) : pollList;
      }
      if (path === "/api/v1/vacancies/vacancy-a") return Promise.resolve(Response.json({ data: detail("vacancy-a") }));
      if (path === "/api/v1/vacancies/vacancy-b") return Promise.resolve(Response.json({ data: detail("vacancy-b") }));
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<Home />);
    await screen.findByRole("button", { name: /Vacancy A/ });
    act(() => poll?.());
    await waitFor(() => expect(listCalls).toBe(2));
    fireEvent.click(screen.getByRole("button", { name: /Vacancy B/ }));
    await screen.findByRole("heading", { name: "Vacancy B" });

    await act(async () => { resolvePoll(Response.json({ data: [summaries[1]] })); });

    expect(screen.getByRole("button", { name: /Vacancy B/ })).toHaveClass("vacancy-selected");
    expect(screen.getByRole("heading", { name: "Vacancy B" })).toBeInTheDocument();
  });
});

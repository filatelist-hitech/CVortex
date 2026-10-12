import { act, cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import CareerWorkspace from "../career-workspace";
import type { Claim, Fact } from "../career-claim";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));
vi.mock("../vacancy-workspace", () => ({ default: () => null }));
afterEach(() => { cleanup(); vi.restoreAllMocks(); });
const statement = "Built Laravel APIs.";
const fact = (id: string, overrides: Partial<Fact> = {}): Fact => ({ id, fact_type: "skill", assertion_original: statement, assertion_approved: statement, source_excerpt: statement, provenance_type: "user_manual", status: "CONFIRMED", ...overrides });
const claim: Claim = { id: "claim-a", statement, truth_status: "USER_RESOLUTION_REQUIRED", fact_ids: ["manual", "extracted"] };
const facts = [fact("manual"), fact("extracted", { fact_type: "experience", provenance_type: "paste_extraction" })];
async function mount(options: { facts?: Fact[]; claim?: Claim; patch?: () => Promise<Response> } = {}) {
  const snapshot = { facts: options.facts ?? facts, claims: [options.claim ?? claim], sources: [] };
  const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, init) => {
    if (String(input) === "/api/v1/career" && !init?.method) return Response.json({ data: snapshot });
    if (String(input) === "/api/v1/career/claims/claim-a/resolve" && init?.method === "PATCH") {
      return options.patch ? options.patch() : Response.json({ data: { id: claim.id, truth_status: "PASS" } });
    }
    throw new Error("Unexpected request");
  });
  render(<CareerWorkspace />);
  await screen.findByText("Нужен выбор источника").catch(() => screen.findByText("Подтверждено фактами"));
  return { fetchMock, snapshot };
}
function choose() { fireEvent.click(screen.getAllByRole("radio")[0]); }
function submit() { fireEvent.click(screen.getByRole("button", { name: "Подтвердить источник утверждения" })); }
const failure = (status: number, code: string) => Response.json({ error: { code } }, { status });

describe("QA-004 Career claim resolution", () => {
  it("QA004-01 exposes explicit resolution with an explanation", async () => {
    await mount();
    expect(screen.getByText("Выбрать источник утверждения")).toBeInTheDocument();
    expect(screen.getByText(/фактами разных типов/)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Подтвердить источник утверждения" })).toBeDisabled();
  });
  it("QA004-02 PASS has no resolution form", async () => {
    await mount({ claim: { ...claim, truth_status: "PASS" } });
    expect(screen.queryByText("Выбрать источник утверждения")).not.toBeInTheDocument();
  });
  it("QA004-03 renders supporting assertion, type, source and provenance", async () => {
    await mount();
    const group = screen.getByRole("group", { name: "Подтверждённые источники" });
    expect(within(group).getAllByRole("radio")).toHaveLength(2);
    for (const text of ["Тип: Навык", "Тип: Опыт", "Источник: Добавлено вручную", "Источник: Из текста"]) expect(within(group).getByText(text)).toBeInTheDocument();
    expect(within(group).getAllByText(statement)).toHaveLength(4);
  });
  it.each([["QA004-04", "PENDING"], ["QA004-05", "REJECTED"], ["QA004-06", "DEPRECATED"]] as const)("%s excludes %s", async (_, status) => {
    await mount({ facts: [fact("manual", { status }), facts[1]] });
    expect(screen.getAllByRole("radio")).toHaveLength(1);
    expect(screen.getByRole("radio")).toHaveAttribute("value", "extracted");
  });
  it("QA004-07 excludes unrelated confirmed evidence and mismatched assertions", async () => {
    await mount({ facts: [...facts, fact("unrelated"), fact("wrong", { assertion_approved: "Different assertion" })], claim: { ...claim, fact_ids: [...claim.fact_ids, "wrong"] } });
    expect(screen.getAllByRole("radio")).toHaveLength(2);
  });
  it("QA004-08/09 sends the existing PATCH and selected career_fact_id", async () => {
    const { fetchMock } = await mount(); choose(); submit();
    await screen.findByText(/Источник утверждения подтверждён/);
    expect(fetchMock).toHaveBeenCalledWith("/api/v1/career/claims/claim-a/resolve", expect.objectContaining({ method: "PATCH", credentials: "same-origin", body: JSON.stringify({ career_fact_id: "manual" }), headers: expect.objectContaining({ "X-XSRF-TOKEN": "" }) }));
  });
  it("QA004-10 updates the server-authoritative Claim and keeps a result", async () => {
    await mount(); choose(); submit();
    expect(await screen.findByText("Подтверждено фактами")).toBeInTheDocument();
    expect(screen.queryByRole("radio")).not.toBeInTheDocument();
    expect(screen.getByRole("status")).toHaveTextContent(/Одобрение текста остаётся отдельным/);
  });
  it("QA004-11 validation failure retains unresolved state and allows explicit retry", async () => {
    let attempts = 0;
    await mount({ patch: async () => ++attempts === 1 ? failure(422, "VALIDATION_FAILED") : Response.json({ data: { id: claim.id, truth_status: "PASS" } }) });
    choose(); submit();
    expect(await screen.findByRole("alert")).toHaveTextContent("Сервер отклонил выбор");
    expect(screen.getByText("Нужен выбор источника")).toBeInTheDocument();
    expect(screen.getAllByRole("radio")[0]).toBeChecked();
    expect(attempts).toBe(1); submit();
    await screen.findByText("Подтверждено фактами"); expect(attempts).toBe(2);
  });
  it("QA004-12 network failure keeps the selection and error", async () => {
    await mount({ patch: async () => { throw new TypeError("offline"); } }); choose(); submit();
    expect(await screen.findByRole("alert")).toHaveTextContent("Проверьте сеть");
    expect(screen.getAllByRole("radio")[0]).toBeChecked();
    expect(screen.queryByText("Подтверждено фактами")).not.toBeInTheDocument();
  });
  it("QA004-13 blocks duplicate submissions and shows busy state", async () => {
    let complete!: (response: Response) => void;
    const { fetchMock } = await mount({ patch: () => new Promise((resolve) => { complete = resolve; }) }); choose(); submit();
    const button = screen.getByRole("button", { name: "Подтверждение источника…" });
    expect(button).toBeDisabled();
    fireEvent.submit(button.closest("form")!); fireEvent.submit(button.closest("form")!);
    expect(fetchMock.mock.calls.filter(([, init]) => init?.method === "PATCH")).toHaveLength(1);
    expect(screen.getAllByRole("radio")[0]).toBeDisabled();
    await act(async () => complete(Response.json({ data: { id: claim.id, truth_status: "PASS" } })));
  });
  it("QA004-14 uses native labelled keyboard controls and focuses the result", async () => {
    await mount();
    const summary = screen.getByText("Выбрать источник утверждения"); expect(summary.tagName).toBe("SUMMARY");
    const radio = screen.getAllByRole("radio")[0]; expect(radio).toHaveAccessibleName(/Built Laravel APIs.*Тип: Навык.*Источник: Добавлено вручную/);
    radio.focus(); expect(radio).toHaveFocus(); choose(); submit();
    await screen.findByText(/Источник утверждения подтверждён/);
    await waitFor(() => expect(screen.getByRole("status")).toHaveFocus());
  });
  it("QA004-15 opening the page or options never resolves automatically", async () => {
    const { fetchMock } = await mount(); fireEvent.click(screen.getByText("Выбрать источник утверждения"));
    expect(screen.getAllByRole("radio").every((radio) => !(radio as HTMLInputElement).checked)).toBe(true);
    expect(fetchMock.mock.calls.every(([, init]) => !init?.method)).toBe(true);
  });
  it.each([[401, "AUTH_REQUIRED"], [403, "PERMISSION_DENIED"], [404, "NOT_FOUND"], [503, "INTERNAL_ERROR"]])("handles %s without a false PASS", async (status, code) => {
    await mount({ patch: async () => failure(Number(status), String(code)) }); choose(); submit();
    expect(await screen.findByRole("alert")).not.toBeEmptyDOMElement();
    expect(screen.getAllByRole("radio")[0]).toBeChecked();
    expect(screen.getByText("Нужен выбор источника")).toBeInTheDocument();
  });
  it("excludes confirmed superseded facts and empty provenance", async () => {
    await mount({ facts: [facts[0], fact("extracted", { source_excerpt: "" }), fact("replacement", { supersedes_fact_id: "manual" })] });
    expect(screen.queryByRole("radio")).not.toBeInTheDocument();
  });
  it("refresh preserves selection but blocks a now deprecated choice", async () => {
    const { snapshot } = await mount(); choose();
    snapshot.facts = [fact("manual", { status: "DEPRECATED" }), facts[1]];
    fireEvent.click(screen.getByRole("button", { name: "Обновить варианты" }));
    await screen.findByText("Выбранный факт больше недоступен. Выберите другой подтверждённый источник.");
    expect(screen.getByRole("button", { name: "Подтвердить источник утверждения" })).toBeDisabled();
  });
  it("does not infer PASS from a successful transport or a malformed response", async () => {
    await mount({ patch: async () => Response.json({ data: { id: claim.id, truth_status: "BLOCK" } }) }); choose(); submit();
    expect(await screen.findByText("Недостаточно подтверждения")).toBeInTheDocument();
    expect(screen.getByRole("alert")).toHaveTextContent("проверка утверждения не пройдена");
  });
  it("fails closed when supporting IDs are absent and renders statements as text", async () => {
    const markup = '<img src=x onerror="window.qaXss=true">';
    await mount({ facts: [fact("manual", { assertion_approved: markup })], claim: { ...claim, statement: markup, fact_ids: [] } });
    expect(screen.queryByRole("radio")).not.toBeInTheDocument();
    expect(document.querySelector("img")).toBeNull();
    expect(screen.getByText(markup, { selector: ".career-claim .assertion" })).toBeInTheDocument();
  });
  it("a malformed resolution response preserves choice without changing status", async () => {
    await mount({ patch: async () => Response.json({ data: { id: "other-claim", truth_status: "PASS" } }) }); choose(); submit();
    expect(await screen.findByRole("alert")).toHaveTextContent("Не удалось подтвердить источник");
    expect(screen.getAllByRole("radio")[0]).toBeChecked();
    expect(screen.getByText("Нужен выбор источника")).toBeInTheDocument();
  });

});

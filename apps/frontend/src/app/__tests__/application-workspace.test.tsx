import { cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import ApplicationWorkspace from "../application/app-shell";
import AccessShell from "../access-shell";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));
vi.mock("../vacancy-chat", () => ({ default: () => null }));
Object.defineProperty(window, "matchMedia", { writable: true, value: vi.fn().mockImplementation(() => ({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() })) });
HTMLDialogElement.prototype.showModal = function () { this.setAttribute("open", ""); };
HTMLDialogElement.prototype.close = function () { this.removeAttribute("open"); this.dispatchEvent(new Event("close")); };

beforeEach(() => { vi.stubEnv("NEXT_PUBLIC_CVORTEX_WORKSPACE_DEMO", "1"); window.history.replaceState(null, "", "/?demo=b01&opportunity=northstar#materials"); });
afterEach(() => { cleanup(); vi.restoreAllMocks(); vi.unstubAllEnvs(); window.history.replaceState(null, "", "/"); });
async function demo(scenario = "normal") {
  window.history.replaceState(null, "", `/?demo=b01&opportunity=northstar&scenario=${scenario}#materials`);
  render(<ApplicationWorkspace email="synthetic@example.test" role="user" onSignOut={vi.fn()} />);
  await screen.findByText("Уточнить опыт тестирования API");
  return screen.getByRole("button", { name: "Одобрить этот текст" });
}

describe("canonical Application Workspace", () => {
  it("separates acceptance from approval and never sends an API write in demo", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    const approve = await demo();
    expect(approve).toBeDisabled();
    fireEvent.click(screen.getByRole("button", { name: "Принять правку" }));
    expect(screen.getByText("Правка принята, текст не одобрен", { selector: "#review-state" })).toBeInTheDocument();
    expect(approve).toBeEnabled();
    fireEvent.click(approve);
    expect(screen.getByText("Текст одобрен, отклик не отправлен")).toBeInTheDocument();
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it.each(["stale", "blocked", "pending"])("blocks acceptance and approval for %s evidence", async scenario => {
    const approve = await demo(scenario);
    expect(approve).toBeDisabled(); expect(screen.getByRole("button", { name: "Принять правку" })).toBeDisabled();
  });

  it("resets approval and pinned evidence on selection and blocks Cedar conflict", async () => {
    await demo();
    fireEvent.click(screen.getByRole("button", { name: "Принять правку" }));
    fireEvent.click(screen.getByRole("button", { name: "Одобрить этот текст" }));
    fireEvent.click(screen.getByRole("button", { name: "Факты и источник" }));
    fireEvent.click(screen.getByRole("button", { name: "Закрепить" }));
    fireEvent.click(screen.getByRole("button", { name: /Cedar Systems/ }));
    expect(screen.getByText("Одобрение заблокировано")).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Принять правку" })).toBeDisabled();
    expect(screen.queryByRole("heading", { name: "На чём основан текст" })).not.toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Факты и источник" }));
    expect(screen.getByRole("button", { name: "Закрепить" })).toHaveAttribute("aria-pressed", "false");
  });

  it("keeps unsaved edits on navigation and rejects unsupported wording", async () => {
    await demo(); fireEvent.click(screen.getByRole("button", { name: "Изменить" }));
    fireEvent.change(screen.getByLabelText("Изменить предложенный текст"), { target: { value: "Invented leadership" } });
    fireEvent.click(screen.getByRole("button", { name: /Cedar Systems/ }));
    expect(screen.getByRole("heading", { name: "Старший инженер по тестированию" })).toBeInTheDocument();
    fireEvent.click(screen.getByRole("button", { name: "Сохранить и проверить" }));
    expect(screen.getByRole("alert")).toHaveTextContent("Правка заблокирована");
    expect(screen.getByRole("button", { name: "Принять правку" })).toBeDisabled();
    fireEvent.change(screen.getByLabelText("Изменить предложенный текст"), { target: { value: "Писал регрессионные тесты API на Python и pytest." } });
    fireEvent.click(screen.getByRole("button", { name: "Сохранить и проверить" }));
    expect(screen.getByRole("button", { name: "Принять правку" })).toBeEnabled();
    expect(screen.getByRole("button", { name: "Одобрить этот текст" })).toBeDisabled();
  });

  it("filters the list without losing selection", async () => {
    await demo(); fireEvent.change(screen.getByRole("searchbox"), { target: { value: "cedar" } });
    expect(screen.queryByRole("button", { name: /Northstar Labs/ })).not.toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Старший инженер по тестированию" })).toBeInTheDocument();
    fireEvent.change(screen.getByRole("searchbox"), { target: { value: "absent" } });
    expect(screen.getByText(/Ничего не найдено/)).toBeVisible();
  });

  it("requires a real authenticated session even with the demo flag and URL", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(null, { status: 401 }));
    render(<AccessShell />);
    await waitFor(() => expect(fetchMock).toHaveBeenCalled());
    expect(screen.queryByText("Northstar Labs")).not.toBeInTheDocument();
    expect(screen.getByLabelText("Электронная почта")).toBeInTheDocument();
  });

  it("ignores the demo URL when its deployment flag is absent", async () => {
    vi.stubEnv("NEXT_PUBLIC_CVORTEX_WORKSPACE_DEMO", "");
    const fetchMock = vi.spyOn(globalThis, "fetch").mockResolvedValue(Response.json({ data: [] }));
    render(<ApplicationWorkspace email="synthetic@example.test" role="user" onSignOut={vi.fn()} />);
    await screen.findByText("Сохранённых вакансий пока нет. Добавьте вакансию в обзоре.");
    expect(screen.queryByText("Northstar Labs")).not.toBeInTheDocument();
    expect(fetchMock).toHaveBeenCalledWith("/api/v1/vacancies", expect.objectContaining({ credentials: "same-origin" }));
  });

  it("uses the actual role in its account menu without demo privilege switches", async () => {
    await demo(); fireEvent.click(screen.getByRole("button", { name: /Профиль/ }));
    const account = screen.getByRole("dialog");
    expect(within(account).getByText("synthetic@example.test")).toBeInTheDocument();
    expect(within(account).queryByRole("link", { name: "Диагностика" })).not.toBeInTheDocument();
    expect(within(account).queryByRole("combobox")).not.toBeInTheDocument();
  });

  it("keeps live draft actions on existing contracts and protects unsaved text", async () => {
    vi.stubEnv("NEXT_PUBLIC_CVORTEX_WORKSPACE_DEMO", "");
    window.history.replaceState(null, "", "/?opportunity=live#materials");
    const vacancy = { id: "live", title: "Synthetic engineer", company: "Synthetic company", analysis_status: "COMPLETED", analysis_stale: false, requirements: [], snapshot: { raw_text: "Synthetic source" }, analysis: { recommendation: "APPLY", dimensions: [], key_reasons: [] } };
    const draft = { id: "draft", kind: "RESUME_RECOMMENDATION", section: "Опыт", before: "Synthetic original", content: "Synthetic confirmed text", reason: "Synthetic reason", risk: "Synthetic risk", status: "DRAFT", revision_number: 1, validation_result: "PASS", claim_usages: [], revisions: [], approvals: [] };
    const preparation = { id: "prep", vacancy_id: "live", stale: false, status: "DRAFT", items: [draft] };
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: { default_provider: "gemini", gemini: { default_model: "gemini-2.0-flash" } } });
      if (path === "/api/v1/vacancies") return Response.json({ data: [vacancy] });
      if (path === "/api/v1/vacancies/live") return Response.json({ data: vacancy });
      if (path === "/api/v1/vacancies/live/preparation" || path === "/api/v1/applications/preparations/prep") return Response.json({ data: preparation });
      if (path === "/api/v1/applications/draft-items/draft") {
        const body = JSON.parse(String(options?.body));
        if (body.action === "edit") draft.content = body.content;
        if (body.action === "accept") draft.status = "ACCEPTED";
        return Response.json({ data: preparation });
      }
      if (path === "/api/v1/applications/draft-items/draft/approve") { draft.status = "APPROVED"; return Response.json({ data: preparation }); }
      throw new Error("Unexpected request " + path);
    });
    render(<ApplicationWorkspace email="synthetic@example.test" role="user" onSignOut={vi.fn()} />);
    const text = await screen.findByDisplayValue("Synthetic confirmed text");
    await waitFor(() => expect(screen.getByRole("button", { name: "Принять правку" })).toBeEnabled());
    fireEvent.change(text, { target: { value: "Synthetic edited text" } });
    fireEvent.click(screen.getByRole("link", { name: "Требования и мой опыт" }));
    expect(text).toBeVisible();
    expect(screen.getByRole("alert")).toHaveTextContent("несохранённая правка");
    expect(screen.getByRole("button", { name: "Принять правку" })).toBeDisabled();
    fireEvent.click(screen.getByRole("button", { name: "Сохранить и проверить" }));
    await waitFor(() => expect(screen.getByRole("button", { name: "Принять правку" })).toBeEnabled());
    fireEvent.click(screen.getByRole("button", { name: "Принять правку" }));
    const approve = screen.getByRole("button", { name: "Одобрить этот текст" });
    await waitFor(() => expect(approve).toBeEnabled());
    fireEvent.click(approve);
    await screen.findByText("Текст одобрен, отклик не отправлен");
    expect(fetchMock).toHaveBeenCalledWith("/api/v1/applications/draft-items/draft", expect.objectContaining({ method: "PATCH", body: JSON.stringify({ action: "edit", content: "Synthetic edited text" }) }));
    expect(fetchMock).toHaveBeenCalledWith("/api/v1/applications/draft-items/draft/approve", expect.objectContaining({ method: "POST" }));
    expect(fetchMock.mock.calls.some(([input]) => /submit|send/.test(String(input)))).toBe(false);
  });
});

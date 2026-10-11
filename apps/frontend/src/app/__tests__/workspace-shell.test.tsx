import { act, cleanup, fireEvent, render, screen, waitFor, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import WorkspaceShell from "../workspace-shell";
import AccessShell from "../access-shell";

vi.mock("next/navigation", () => ({ useRouter: () => ({ replace: vi.fn() }) }));

// Keep these integration checks bounded to access and shell mounting.
vi.mock("../career-workspace", () => ({ default: () => <main id="workspace-content"><h1>Career surface</h1><h2 id="vacancy-title">Saved vacancies</h2></main> }));
vi.mock("../diagnostics", () => ({ default: () => <h1>Diagnostics surface</h1> }));

Object.defineProperty(window, "matchMedia", { writable: true, value: vi.fn().mockImplementation(() => ({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() })) });
HTMLDialogElement.prototype.showModal = function () { this.setAttribute("open", ""); };
HTMLDialogElement.prototype.close = function () { this.removeAttribute("open"); this.dispatchEvent(new Event("close")); };

afterEach(() => { cleanup(); vi.restoreAllMocks(); window.history.replaceState(null, "", "/"); });

function openAccount() { fireEvent.click(screen.getByRole("button", { name: /Профиль/ })); }

function shell(role = "user", destination: "career" | "diagnostics" = "career", onSignOut = vi.fn().mockResolvedValue(undefined)) {
  render(<WorkspaceShell email="synthetic@example.test" role={role} destination={destination} onSignOut={onSignOut}><main id="workspace-content" tabIndex={-1}>Content</main></WorkspaceShell>);
  return onSignOut;
}

function userFetch(role = "user") {
  return vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
    if (String(input) === "/api/v1/me") return Response.json({ data: { id: "fixture", email: "synthetic@example.test", role, status: "ACTIVE" } });
    if (String(input) === "/api/v1/auth/logout" && options?.method === "POST") return new Response(null, { status: 204 });
    throw new Error(`Unexpected request: ${String(input)}`);
  });
}

describe("B02 authenticated global shell", () => {
  it("discloses unavailable destinations without links or requests", () => {
    const fetchMock = vi.spyOn(globalThis, "fetch");
    shell();
    const navigation = screen.getByRole("navigation", { name: "Основное меню" });
    for (const name of ["Сегодня", "Работодатели", "Настройки"]) {
      expect(within(navigation).getByRole("button", { name })).toBeDisabled();
      expect(within(navigation).getByRole("button", { name })).toHaveAccessibleDescription("Недоступно");
      expect(within(navigation).queryByRole("link", { name })).not.toBeInTheDocument();
    }
    expect(within(navigation).getByRole("link", { name: "Карьера" })).toHaveAttribute("href", "/career");
    expect(within(navigation).getByRole("link", { name: "Вакансии" })).toHaveAttribute("href", "/");
    expect(fetchMock).not.toHaveBeenCalled();
  });

  it("tracks the existing deep link and Back/Forward events semantically", () => {
    shell();
    const career = screen.getByRole("link", { name: "Карьера" });
    const opportunities = screen.getByRole("link", { name: "Вакансии" });
    expect(career).toHaveAttribute("aria-current", "page");
    act(() => { window.history.pushState(null, "", "/#vacancy-title"); window.dispatchEvent(new HashChangeEvent("hashchange")); });
    expect(opportunities).toHaveAttribute("aria-current", "location");
    expect(career).not.toHaveAttribute("aria-current");
    act(() => { window.history.replaceState(null, "", "/"); window.dispatchEvent(new PopStateEvent("popstate")); });
    expect(career).toHaveAttribute("aria-current", "page");
    expect(opportunities).not.toHaveAttribute("aria-current");
  });

  it("provides a skip target and native focusable links without a compact trap", () => {
    shell();
    const skip = screen.getByRole("link", { name: "Перейти к содержимому" });
    expect(skip).toHaveAttribute("href", "#workspace-content");
    const career = screen.getByRole("link", { name: "Карьера" });
    career.focus();
    expect(career).toHaveFocus();
    expect(screen.getByRole("main")).toHaveAttribute("tabindex", "-1");
    expect(screen.queryByRole("dialog")).not.toBeInTheDocument();
  });

  it("keeps diagnostics an admin utility and current only at its route", () => {
    shell("admin", "diagnostics");
    openAccount();
    expect(screen.getByRole("link", { name: "Диагностика" })).toHaveAttribute("aria-current", "page");
    expect(screen.getByRole("link", { name: "Карьера" })).not.toHaveAttribute("aria-current");
    expect(within(screen.getByRole("navigation")).queryByRole("link", { name: "Диагностика" })).not.toBeInTheDocument();
  });

  it("preserves signed-in access integration and POST logout", async () => {
    const fetchMock = userFetch();
    render(<AccessShell />);
    expect(await screen.findByRole("navigation", { name: "Основное меню" })).toBeInTheDocument();
    expect(screen.queryByRole("link", { name: "Диагностика" })).not.toBeInTheDocument();
    openAccount();
    fireEvent.click(screen.getByRole("button", { name: "Выйти" }));
    await waitFor(() => expect(screen.queryByRole("navigation", { name: "Основное меню" })).not.toBeInTheDocument());
    expect(fetchMock).toHaveBeenCalledWith("/api/v1/auth/logout", expect.objectContaining({ method: "POST", credentials: "same-origin" }));
  });

  it("mounts the same shell around the admin diagnostics surface", async () => {
    userFetch("admin");
    render(<AccessShell destination="diagnostics" />);
    expect(await screen.findByRole("heading", { name: "Diagnostics surface" })).toBeInTheDocument();
    expect(screen.getByRole("navigation", { name: "Основное меню" })).toBeInTheDocument();
    openAccount();
    expect(screen.getByRole("link", { name: "Диагностика" })).toHaveAttribute("aria-current", "page");
  });

  it("denies direct diagnostics navigation to a normal user", async () => {
    const fetchMock = userFetch();
    render(<AccessShell destination="diagnostics" />);
    expect(await screen.findByRole("heading", { name: "Доступ запрещён" })).toBeInTheDocument();
    expect(screen.queryByRole("navigation", { name: "Основное меню" })).not.toBeInTheDocument();
    expect(fetchMock.mock.calls).toHaveLength(1);
  });

  it("keeps public sign-in outside the authenticated shell", async () => {
    vi.spyOn(globalThis, "fetch").mockResolvedValue(new Response(null, { status: 401 }));
    render(<AccessShell />);
    await waitFor(() => expect(screen.getByLabelText("Электронная почта")).toBeInTheDocument());
    expect(screen.queryByRole("navigation", { name: "Основное меню" })).not.toBeInTheDocument();
  });

  it("retains the session and offers retry when sign out fails", async () => {
    const onSignOut = vi.fn().mockRejectedValue(new Error("untrusted detail"));
    shell("user", "career", onSignOut);
    openAccount();
    fireEvent.click(screen.getByRole("button", { name: "Выйти" }));
    expect(await screen.findByRole("alert")).toHaveTextContent("Не удалось выйти. Попробуйте ещё раз.");
    expect(screen.queryByText("untrusted detail")).not.toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Выйти" })).toBeEnabled();
  });
});

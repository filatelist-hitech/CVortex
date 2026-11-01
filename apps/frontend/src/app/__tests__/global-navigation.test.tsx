import { cleanup, fireEvent, render, screen, within } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { GlobalSidebar } from "../components/navigation/global-sidebar";
import { UserMenu } from "../components/navigation/user-menu";
import { SettingsLayout } from "../components/navigation/settings-layout";
import { GLOBAL_NAV_ITEMS, resolveActiveNavId } from "../navigation-config";

Object.defineProperty(window, "matchMedia", {
  writable: true,
  value: vi.fn().mockImplementation(() => ({
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })),
});

HTMLDialogElement.prototype.showModal = function () {
  this.setAttribute("open", "");
};
HTMLDialogElement.prototype.close = function () {
  this.removeAttribute("open");
  this.dispatchEvent(new Event("close"));
};

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  window.history.replaceState(null, "", "/");
});

describe("Unified Navigation System", () => {
  describe("navigation-config", () => {
    it("resolves active nav ID across destinations and hashes correctly", () => {
      expect(resolveActiveNavId("today", "")).toBe("today");
      expect(resolveActiveNavId("vacancies", "")).toBe("vacancies");
      expect(resolveActiveNavId("vacancy-import", "")).toBe("import");
      expect(resolveActiveNavId("discover", "")).toBe("discover");
      expect(resolveActiveNavId("career", "")).toBe("career");
      expect(resolveActiveNavId("career", "#vacancy-title")).toBe("vacancies");
      expect(resolveActiveNavId("settings", "")).toBe("settings");
      expect(resolveActiveNavId("diagnostics", "")).toBeNull();
    });

    it("defines consistent navigation items preserving product scope", () => {
      const ids = GLOBAL_NAV_ITEMS.map((item) => item.id);
      expect(ids).toEqual([
        "today",
        "vacancies",
        "import",
        "discover",
        "career",
        "employers",
        "settings",
      ]);
    });
  });

  describe("GlobalSidebar component", () => {
    it("renders all global items with correct labels and accessibility attributes", () => {
      render(<GlobalSidebar destination="today" currentLabel="Сегодня" />);
      const nav = screen.getByRole("navigation", { name: "Основное меню" });

      expect(within(nav).getByRole("link", { name: "Сегодня" })).toHaveAttribute("aria-current", "page");
      expect(within(nav).getByRole("link", { name: "Вакансии" })).toHaveAttribute("href", "/vacancies");
      expect(within(nav).getByRole("link", { name: "Импорт вакансии" })).toHaveAttribute("href", "/vacancies/import");
      expect(within(nav).getByRole("link", { name: "Поиск (Discover)" })).toHaveAttribute("href", "/vacancies/discover");
      expect(within(nav).getByRole("link", { name: "Карьера" })).toHaveAttribute("href", "/career");
      expect(within(nav).getByRole("link", { name: "Настройки" })).toHaveAttribute("href", "/settings");

      const employersBtn = within(nav).getByRole("button", { name: "Работодатели" });
      expect(employersBtn).toBeDisabled();
      expect(employersBtn).toHaveAccessibleDescription("Недоступно");
    });

    it("respects navigation guard when allowNavigation returns false", () => {
      const allowNav = vi.fn().mockReturnValue(false);
      render(<GlobalSidebar destination="vacancies" allowNavigation={allowNav} />);
      const link = screen.getByRole("link", { name: "Сегодня" });
      fireEvent.click(link);
      expect(allowNav).toHaveBeenCalled();
    });
  });

  describe("UserMenu component redesign", () => {
    it("renders user identity, role badge, and account sections for regular user", () => {
      const onSignOut = vi.fn();
      render(
        <UserMenu
          email="regular@example.test"
          role="user"
          onSignOut={onSignOut}
          currentDestination="today"
        />
      );

      // Trigger
      const trigger = screen.getByRole("button", { name: /Профиль/ });
      expect(trigger).toHaveAttribute("aria-expanded", "false");
      fireEvent.click(trigger);
      expect(trigger).toHaveAttribute("aria-expanded", "true");

      // Dialog content
      const dialog = screen.getByRole("dialog");
      expect(within(dialog).getByText("regular@example.test")).toBeInTheDocument();
      expect(within(dialog).getByText("Пользователь")).toBeInTheDocument();

      // Account links
      expect(within(dialog).getByRole("button", { name: "Мой профиль" })).toBeInTheDocument();
      expect(within(dialog).getByRole("link", { name: "Настройки AI" })).toHaveAttribute("href", "/settings");

      // Admin section must NOT be visible to regular user
      expect(within(dialog).queryByRole("link", { name: "Диагностика" })).not.toBeInTheDocument();
      expect(within(dialog).queryByRole("link", { name: "Настройки системы" })).not.toBeInTheDocument();

      // Close button closes dialog
      const closeBtn = screen.getByRole("button", { name: "Закрыть профиль" });
      fireEvent.click(closeBtn);
      expect(trigger).toHaveAttribute("aria-expanded", "false");
    });

    it("renders admin utilities section when user role is admin", () => {
      render(
        <UserMenu
          email="admin@example.test"
          role="admin"
          onSignOut={vi.fn()}
          currentDestination="diagnostics"
        />
      );

      fireEvent.click(screen.getByRole("button", { name: /Профиль/ }));
      const dialog = screen.getByRole("dialog");

      expect(within(dialog).getByText("admin@example.test")).toBeInTheDocument();
      expect(within(dialog).getByText("Администратор")).toBeInTheDocument();

      const diagLink = within(dialog).getByRole("link", { name: "Диагностика" });
      expect(diagLink).toHaveAttribute("href", "/diagnostics");
      expect(diagLink).toHaveAttribute("aria-current", "page");

      const sysSettingsLink = within(dialog).getByRole("link", { name: "Настройки системы" });
      expect(sysSettingsLink).toHaveAttribute("href", "/admin/settings");
    });

    it("triggers onSignOut on logout click and shows loading state", async () => {
      let resolveSignOut: () => void = () => {};
      const onSignOut = vi.fn().mockImplementation(() => new Promise<void>((res) => { resolveSignOut = res; }));

      render(
        <UserMenu
          email="user@example.test"
          role="user"
          onSignOut={onSignOut}
        />
      );

      fireEvent.click(screen.getByRole("button", { name: /Профиль/ }));
      const logoutBtn = screen.getByRole("button", { name: "Выйти" });
      fireEvent.click(logoutBtn);

      expect(logoutBtn).toHaveTextContent("Выход…");
      expect(logoutBtn).toBeDisabled();
      expect(onSignOut).toHaveBeenCalled();

      resolveSignOut();
    });

    it("displays error message when sign out fails", async () => {
      const onSignOut = vi.fn().mockRejectedValue(new Error("Network failed"));

      render(
        <UserMenu
          email="user@example.test"
          role="user"
          onSignOut={onSignOut}
        />
      );

      fireEvent.click(screen.getByRole("button", { name: /Профиль/ }));
      const logoutBtn = screen.getByRole("button", { name: "Выйти" });
      fireEvent.click(logoutBtn);

      expect(await screen.findByRole("alert")).toHaveTextContent("Не удалось выйти. Попробуйте ещё раз.");
      expect(logoutBtn).toBeEnabled();
    });
  });

  describe("SettingsLayout component", () => {
    it("renders user-accessible tabs for standard user", () => {
      render(
        <SettingsLayout
          currentTab="ai"
          role="user"
          title="Настройки AI"
          description="Управление AI"
        >
          <div>Content</div>
        </SettingsLayout>
      );

      const nav = screen.getByRole("navigation", { name: "Разделы настроек" });
      expect(within(nav).getByRole("link", { name: "AI-провайдеры" })).toHaveAttribute("aria-current", "page");
      expect(within(nav).queryByRole("link", { name: "Системные настройки" })).not.toBeInTheDocument();
      expect(within(nav).queryByRole("link", { name: "Диагностика" })).not.toBeInTheDocument();
    });

    it("renders all tabs for admin role and highlights current tab", () => {
      render(
        <SettingsLayout
          currentTab="system"
          role="admin"
          title="Системные настройки"
          description="Конфигурация"
        >
          <div>System Content</div>
        </SettingsLayout>
      );

      const nav = screen.getByRole("navigation", { name: "Разделы настроек" });
      expect(within(nav).getByRole("link", { name: "AI-провайдеры" })).toBeInTheDocument();
      expect(within(nav).getByRole("link", { name: "Системные настройки" })).toHaveAttribute("aria-current", "page");
      expect(within(nav).getByRole("link", { name: "Диагностика" })).toBeInTheDocument();
    });
  });
});

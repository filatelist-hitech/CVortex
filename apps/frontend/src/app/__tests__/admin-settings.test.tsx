import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, beforeEach, describe, expect, it, vi } from "vitest";
import AdminSystemSettings from "../admin-settings";
import AccessShell from "../access-shell";

const mocks = vi.hoisted(() => ({
  router: { replace: vi.fn() },
}));

vi.mock("next/navigation", () => ({
  useRouter: () => mocks.router,
}));

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const sampleSettings = [
  {
    key: "general.app_name",
    group: "general",
    subgroup: "branding",
    label: "Название сервиса",
    description: "Публичное название приложения.",
    type: "string",
    options: null,
    default: "CVortex",
    value: "CVortex",
    source: "default",
    is_secret: false,
    is_configured: false,
    is_overridden: false,
    admin_editable: true,
    runtime_mutable: true,
    requires_restart: false,
    env_locked: false,
    env_fallback_key: "APP_NAME",
    dangerous: false,
    testable: false,
    sort_order: 10,
    version: 1,
    updated_at: null,
    updated_by: null,
  },
  {
    key: "auth.invitation_ttl_days",
    group: "auth",
    subgroup: "invitations",
    label: "Срок действия инвайтов (дней)",
    description: "Количество дней, в течение которых приглашение активно.",
    type: "integer",
    options: null,
    default: 7,
    value: 14,
    source: "database",
    is_secret: false,
    is_configured: false,
    is_overridden: true,
    admin_editable: true,
    runtime_mutable: true,
    requires_restart: false,
    env_locked: false,
    env_fallback_key: "AUTH_INVITATION_TTL_DAYS",
    dangerous: false,
    testable: false,
    sort_order: 20,
    version: 2,
    updated_at: "2026-10-24T20:00:00Z",
    updated_by: "AdminUser",
  },
  {
    key: "ai.providers.openai.api_key",
    group: "ai_providers",
    subgroup: "openai",
    label: "OpenAI API Key",
    description: "Секретный ключ OpenAI.",
    type: "secret",
    options: null,
    default: null,
    value: null,
    source: "database",
    is_secret: true,
    is_configured: true,
    is_overridden: true,
    admin_editable: true,
    runtime_mutable: true,
    requires_restart: false,
    env_locked: false,
    env_fallback_key: "OPENAI_API_KEY",
    dangerous: false,
    testable: true,
    sort_order: 30,
    version: 3,
    updated_at: "2026-10-24T21:00:00Z",
    updated_by: "AdminUser",
  },
  {
    key: "general.default_timezone",
    group: "general",
    subgroup: "localization",
    label: "Часовой пояс",
    description: "Часовой пояс сервера по умолчанию.",
    type: "enum",
    options: [
      { value: "UTC", label: "UTC" },
      { value: "Europe/Moscow", label: "Europe/Moscow" },
    ],
    default: "UTC",
    value: "UTC",
    source: "environment",
    is_secret: false,
    is_configured: false,
    is_overridden: false,
    admin_editable: true,
    runtime_mutable: true,
    requires_restart: false,
    env_locked: false,
    env_fallback_key: "APP_TIMEZONE",
    dangerous: false,
    testable: false,
    sort_order: 40,
    version: 1,
    updated_at: null,
    updated_by: null,
  },
  {
    key: "storage.default_disk",
    group: "storage",
    subgroup: "storage",
    label: "Основной диск хранилища",
    description: "Диск хранения сгенерированных резюме и документов.",
    type: "enum",
    options: [{ value: "local", label: "Локальный диск (Docker Volume)" }],
    default: "local",
    value: "local",
    source: "default",
    is_secret: false,
    is_configured: true,
    is_overridden: false,
    admin_editable: false,
    runtime_mutable: false,
    requires_restart: true,
    env_locked: false,
    env_fallback_key: "FILESYSTEM_DISK",
    dangerous: false,
    testable: true,
    test_target: "storage",
    sort_order: 10,
    version: 1,
    updated_at: null,
    updated_by: null,
  },
];

const sampleEnvironment = {
  app_name: "CVortex",
  app_env: "testing",
  app_debug: false,
  app_url: "http://localhost:8080",
  php_version: "8.4.25",
  laravel_version: "12.0.0",
  database: {
    default: "pgsql",
    status: "connected",
    runtime_role: "cvortex_app",
  },
  redis: {
    client: "phpredis",
    status: "connected",
  },
  cache: { default: "redis" },
  queue: { default: "redis" },
  session: { driver: "database", secure: false },
  storage: { default: "private" },
  encryption: { configured: true, cipher: "AES-256-CBC" },
  logging: { channel: "stderr", level: "info" },
};

describe("AdminSystemSettings Component", () => {
  let fetchSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    fetchSpy = vi.spyOn(globalThis, "fetch").mockImplementation(async (input: RequestInfo | URL, options?: RequestInit) => {
      const url = String(input);
      if (url.includes("/api/v1/admin/settings/environment")) {
        return Response.json({ data: sampleEnvironment });
      }
      if (url.includes("/api/v1/admin/settings/reset") && options?.method === "POST") {
        return Response.json({
          data: { ...sampleSettings[1], is_overridden: false, source: "default", value: 7 },
          message: "Настройка сброшена к исходному значению.",
        });
      }
      if (url.includes("/api/v1/admin/settings/test") && options?.method === "POST") {
        const body = options.body ? JSON.parse(String(options.body)) : {};
        if (body.target === "storage") {
          return Response.json({
            data: { success: true, message: "Файловое хранилище (local) доступно для чтения и записи.", latency_ms: 15 },
          });
        }
        return Response.json({
          data: { success: true, message: "Подключение к OpenAI успешно выполнено.", latency_ms: 120 },
        });
      }
      if (url.includes("/api/v1/admin/settings") && options?.method === "PATCH") {
        return Response.json({ data: [], message: "Настройки успешно сохранены." });
      }
      if (url.includes("/api/v1/admin/settings")) {
        return Response.json({ data: sampleSettings, meta: { total: 5, overrides_count: 2 } });
      }
      return Response.json({ data: null });
    });
  });

  it("renders settings with categories and badges", async () => {
    render(<AdminSystemSettings />);

    expect(await screen.findByRole("heading", { name: "Системные настройки" })).toBeInTheDocument();

    // Check category sidebar
    expect(screen.getByRole("button", { name: /Общие/ })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Аутентификация/ })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Среда и диагностика/ })).toBeInTheDocument();

    // In General group by default: check app_name
    expect(screen.getByText("Название сервиса")).toBeInTheDocument();
    expect(screen.getByDisplayValue("CVortex")).toBeInTheDocument();
    expect(screen.getByText("Application default")).toBeInTheDocument();
  });

  it("navigates between categories and displays correct items", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    // Switch to Auth category
    const authButton = screen.getByRole("button", { name: /Аутентификация/ });
    fireEvent.click(authButton);

    expect(await screen.findByText("Срок действия инвайтов (дней)")).toBeInTheDocument();
    expect(screen.getByDisplayValue("14")).toBeInTheDocument();
    expect(screen.getByText("Database override")).toBeInTheDocument();
  });

  it("filters settings by search query", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    const searchInput = screen.getByPlaceholderText(/Поиск/);
    fireEvent.change(searchInput, { target: { value: "инвайт" } });

    // Should find the invitation setting even from another category
    expect(await screen.findByText("Срок действия инвайтов (дней)")).toBeInTheDocument();
    expect(screen.queryByText("Название сервиса")).not.toBeInTheDocument();

    // Clear search
    const clearButton = screen.getByRole("button", { name: "Очистить поиск" });
    fireEvent.click(clearButton);

    expect(await screen.findByText("Название сервиса")).toBeInTheDocument();
  });

  it("stages changes and shows sticky action bar with discard and save", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    const input = screen.getByDisplayValue("CVortex");
    fireEvent.change(input, { target: { value: "SuperCV" } });

    // Sticky bar should appear
    expect(await screen.findByText(/несохранённое изменение/)).toBeInTheDocument();

    // Test discard
    const discardButton = screen.getByRole("button", { name: "Отменить" });
    fireEvent.click(discardButton);

    // Staged edit should be reverted
    expect(screen.getByDisplayValue("CVortex")).toBeInTheDocument();
    expect(screen.queryByText(/несохранённое изменение/)).not.toBeInTheDocument();

    // Stage change again and save
    const inputAgain = screen.getByDisplayValue("CVortex");
    fireEvent.change(inputAgain, { target: { value: "CVortex Pro" } });

    const saveButton = screen.getByRole("button", { name: "Сохранить изменения" });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        "/api/v1/admin/settings",
        expect.objectContaining({
          method: "PATCH",
          body: JSON.stringify({
            settings: [
              {
                key: "general.app_name",
                value: "CVortex Pro",
                expected_version: 1,
              },
            ],
          }),
        })
      );
    });
  });

  it("handles secret field masking, updates, and clear action", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    // Navigate to AI Providers
    fireEvent.click(screen.getByRole("button", { name: /Провайдеры ИИ/ }));

    expect(await screen.findByText("OpenAI API Key")).toBeInTheDocument();
    expect(screen.getByText("Сконфигурирован (зашифрован)")).toBeInTheDocument();

    // Password input should not reveal value
    const secretInput = screen.getByPlaceholderText(/введите новый для замены/);
    expect(secretInput).toHaveValue("");

    // Test clearing secret
    const clearButton = screen.getByRole("button", { name: "Очистить" });
    fireEvent.click(clearButton);

    expect(screen.getByText("Будет удалён")).toBeInTheDocument();

    // Save cleared secret
    const saveButton = screen.getByRole("button", { name: "Сохранить изменения" });
    fireEvent.click(saveButton);

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        "/api/v1/admin/settings",
        expect.objectContaining({
          method: "PATCH",
          body: JSON.stringify({
            settings: [
              {
                key: "ai.providers.openai.api_key",
                clear_secret: true,
                expected_version: 3,
              },
            ],
          }),
        })
      );
    });
  });

  it("allows resetting an override to default", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    // Navigate to Auth category
    fireEvent.click(screen.getByRole("button", { name: /Аутентификация/ }));
    await screen.findByText("Срок действия инвайтов (дней)");

    const resetButton = screen.getByRole("button", { name: "Сбросить переопределение" });
    fireEvent.click(resetButton);

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        "/api/v1/admin/settings/reset",
        expect.objectContaining({
          method: "POST",
          body: JSON.stringify({ key: "auth.invitation_ttl_days" }),
        })
      );
    });
  });

  it("runs connection tests for testable settings", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    // Navigate to AI Providers
    fireEvent.click(screen.getByRole("button", { name: /Провайдеры ИИ/ }));
    await screen.findByText("OpenAI API Key");

    const testButton = screen.getByRole("button", { name: "Проверить соединение" });
    fireEvent.click(testButton);

    expect(await screen.findByText("Подключение к OpenAI успешно выполнено.")).toBeInTheDocument();
    expect(screen.getByText("(120 ms)")).toBeInTheDocument();
  });

  it("runs storage connection test and displays independent result and latency", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    // Navigate to Storage category
    fireEvent.click(screen.getByRole("button", { name: /Файлы и шаблоны/ }));
    expect(await screen.findByText("Основной диск хранилища")).toBeInTheDocument();

    const testButton = screen.getByRole("button", { name: "Проверить соединение" });
    fireEvent.click(testButton);

    await waitFor(() => {
      expect(fetchSpy).toHaveBeenCalledWith(
        "/api/v1/admin/settings/test",
        expect.objectContaining({
          method: "POST",
          body: JSON.stringify({ target: "storage" }),
        })
      );
    });

    expect(await screen.findByText("Файловое хранилище (local) доступно для чтения и записи.")).toBeInTheDocument();
    expect(screen.getByText("(15 ms)")).toBeInTheDocument();
  });

  it("handles connection test failure gracefully with error box", async () => {
    fetchSpy.mockImplementationOnce(async (input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes("/api/v1/admin/settings/environment")) {
        return Response.json({ data: sampleEnvironment });
      }
      if (url.includes("/api/v1/admin/settings")) {
        return Response.json({ data: sampleSettings, meta: { total: 5, overrides_count: 2 } });
      }
      return Response.json({ data: null });
    });

    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    // Mock failure for the test POST
    fetchSpy.mockImplementationOnce(async () => {
      return new Response(JSON.stringify({ error: { message: "Connection refused on storage disk" } }), { status: 500 });
    });

    fireEvent.click(screen.getByRole("button", { name: /Файлы и шаблоны/ }));
    expect(await screen.findByText("Основной диск хранилища")).toBeInTheDocument();

    const testButton = screen.getByRole("button", { name: "Проверить соединение" });
    fireEvent.click(testButton);

    const errorBox = await screen.findByText(/Не удалось выполнить запрос|Сбой проверки соединения/);
    expect(errorBox).toBeInTheDocument();
    expect(errorBox.closest(".test-result-box")).toHaveClass("error");
  });

  it("renders read-only environment diagnostics tab", async () => {
    render(<AdminSystemSettings />);
    await screen.findByRole("heading", { name: "Системные настройки" });

    // Navigate to Environment category
    fireEvent.click(screen.getByRole("button", { name: /Среда и диагностика/ }));

    expect(await screen.findByRole("heading", { name: "Среда и диагностика развертывания" })).toBeInTheDocument();
    expect(screen.getByText(/8\.4\.25/)).toBeInTheDocument();
    expect(screen.getByText(/12\.0\.0/)).toBeInTheDocument();
    expect(screen.getByText(/cvortex_app/)).toBeInTheDocument();
    expect(screen.getByText(/AES-256-CBC/)).toBeInTheDocument();
  });
});

describe("AccessShell Admin System Settings Route Guard", () => {
  it("forbids non-admin users from accessing /admin/settings", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input: RequestInfo | URL) => {
      const url = String(input);
      if (url.includes("/api/v1/me")) {
        return Response.json({ data: { id: "user-1", email: "user@example.com", role: "candidate" } });
      }
      return Response.json({ data: null });
    });

    render(<AccessShell destination="admin-settings" />);

    expect(await screen.findByRole("heading", { name: "Доступ запрещён" })).toBeInTheDocument();
    expect(screen.getByText("Настройки системы доступны только администраторам.")).toBeInTheDocument();
  });
});

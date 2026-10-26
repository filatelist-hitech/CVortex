import { test, expect } from "../fixtures/test-fixtures";

test.describe("Admin — Runtime Configuration & System Settings", () => {
  const mockSettings = [
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
      updated_by: "admin",
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
      test_target: "openai",
      sort_order: 30,
      version: 3,
      updated_at: "2026-10-24T21:00:00Z",
      updated_by: "admin",
    },
  ];

  const mockDiagnostics = {
    app_name: "CVortex",
    app_env: "testing",
    app_debug: false,
    app_url: "http://localhost:8080",
    php_version: "8.4.25",
    laravel_version: "12.0.0",
    database: { default: "pgsql", status: "connected", runtime_role: "cvortex_app" },
    redis: { client: "phpredis", status: "connected" },
    cache: { default: "redis" },
    queue: { default: "redis" },
    session: { driver: "database", secure: false },
    storage: { default: "private" },
    encryption: { configured: true, cipher: "AES-256-CBC" },
    logging: { channel: "stderr", level: "info" },
  };

  test.describe("Authorized Admin Session", () => {
    test.beforeEach(async ({ page }) => {
      // Authenticate as Admin
      await page.route("**/api/v1/me", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            data: {
              id: "admin-uuid-01",
              email: "admin@cvortex.local",
              role: "admin",
            },
          }),
        });
      });

      // Default mock routes for settings and environment
      await page.route("**/api/v1/admin/settings/environment", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({ data: mockDiagnostics }),
        });
      });

      await page.route("**/api/v1/admin/settings", async (route) => {
        if (route.request().method() === "GET") {
          await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify({
              data: mockSettings,
              meta: { total: mockSettings.length, overrides_count: 2 },
            }),
          });
        } else {
          await route.continue();
        }
      });
    });

    test("smoke: renders system settings categories, badges, and controls @smoke @critical", async ({ page }) => {
      await page.goto("/admin/settings");

      await expect(page.locator(".admin-settings-container")).toBeVisible();
      await expect(page.getByRole("heading", { name: "Системные настройки" })).toBeVisible();

      // Check category list
      await expect(page.getByRole("button", { name: /Общие/ })).toBeVisible();
      await expect(page.getByRole("button", { name: /Аутентификация/ })).toBeVisible();
      await expect(page.getByRole("button", { name: /Среда и диагностика/ })).toBeVisible();

      // Check General setting
      await expect(page.getByText("Название сервиса")).toBeVisible();
      await expect(page.getByText("Application default")).toBeVisible();
    });

    test("critical: stages edit, shows sticky bar, and saves settings @critical", async ({ page }) => {
      let patchPayload: unknown = null;

      await page.route("**/api/v1/admin/settings", async (route) => {
        if (route.request().method() === "PATCH") {
          patchPayload = JSON.parse(route.request().postData() || "{}");
          await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify({
              data: [
                {
                  ...mockSettings[0],
                  value: "CVortex Pro",
                  is_overridden: true,
                  source: "database",
                  version: 2,
                },
              ],
              message: "Настройки успешно сохранены.",
            }),
          });
        } else {
          await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify({
              data: mockSettings,
              meta: { total: mockSettings.length, overrides_count: 2 },
            }),
          });
        }
      });

      await page.goto("/admin/settings");

      const input = page.locator("#input-general\\.app_name");
      await expect(input).toBeVisible();
      await input.fill("CVortex Pro");

      // Verify sticky bar appeared
      const stickyBar = page.locator(".settings-sticky-action-bar");
      await expect(stickyBar).toBeVisible();
      await expect(stickyBar.getByText("1 несохранённое изменение")).toBeVisible();

      // Click save
      await stickyBar.getByRole("button", { name: "Сохранить изменения" }).click();

      // Verify notification
      await expect(page.getByText("Настройки успешно сохранены.")).toBeVisible();
      expect(patchPayload).toEqual({
        settings: [
          {
            key: "general.app_name",
            value: "CVortex Pro",
            expected_version: 1,
          },
        ],
      });
    });

    test("critical: displays masked secret and handles clear action @critical", async ({ page }) => {
      let patchPayload: unknown = null;

      await page.route("**/api/v1/admin/settings", async (route) => {
        if (route.request().method() === "PATCH") {
          patchPayload = JSON.parse(route.request().postData() || "{}");
          await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify({
              data: [],
              message: "Настройки успешно сохранены.",
            }),
          });
        } else {
          await route.fulfill({
            status: 200,
            contentType: "application/json",
            body: JSON.stringify({
              data: mockSettings,
              meta: { total: mockSettings.length, overrides_count: 2 },
            }),
          });
        }
      });

      await page.goto("/admin/settings");

      // Go to AI Providers
      await page.getByRole("button", { name: /Провайдеры ИИ/ }).click();

      await expect(page.getByText("OpenAI API Key")).toBeVisible();
      await expect(page.getByText("Сконфигурирован (зашифрован)")).toBeVisible();

      // Secret input should be masked
      const secretInput = page.locator("#input-ai\\.providers\\.openai\\.api_key");
      await expect(secretInput).toHaveAttribute("type", "password");
      await expect(secretInput).toHaveValue("");

      // Click Clear
      await page.getByRole("button", { name: "Очистить" }).click();
      await expect(page.getByText("Будет удалён")).toBeVisible();

      // Save via sticky bar
      const stickyBar = page.locator(".settings-sticky-action-bar");
      await stickyBar.getByRole("button", { name: "Сохранить изменения" }).click();

      expect(patchPayload).toEqual({
        settings: [
          {
            key: "ai.providers.openai.api_key",
            clear_secret: true,
            expected_version: 3,
          },
        ],
      });
    });

    test("smoke: displays read-only environment diagnostics @smoke", async ({ page }) => {
      await page.goto("/admin/settings");

      // Switch to environment diagnostics tab
      await page.getByRole("button", { name: /Среда и диагностика/ }).click();

      await expect(page.getByRole("heading", { name: "Среда и диагностика развертывания" })).toBeVisible();
      await expect(page.getByText("PHP 8.4.25")).toBeVisible();
      await expect(page.getByText("Laravel 12.0.0")).toBeVisible();
      await expect(page.getByText("cvortex_app")).toBeVisible();
      await expect(page.getByText(/AES-256-CBC/)).toBeVisible();
    });
  });

  test.describe("Forbidden Non-Admin Access", () => {
    test("critical: non-admin user is rejected with 403 Forbidden screen @security @critical", async ({ page }) => {
      // Authenticate as normal candidate
      await page.route("**/api/v1/me", async (route) => {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            data: {
              id: "candidate-uuid-01",
              email: "candidate@cvortex.local",
              role: "candidate",
            },
          }),
        });
      });

      await page.goto("/admin/settings");

      await expect(page.getByRole("heading", { name: "Доступ запрещён" })).toBeVisible();
      await expect(page.getByText("Настройки системы доступны только администраторам.")).toBeVisible();
      await expect(page.locator(".admin-settings-container")).not.toBeVisible();
    });
  });
});

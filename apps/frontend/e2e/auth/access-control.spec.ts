import { test, expect } from "../fixtures/test-fixtures";

test.describe("Access Control & Role Boundaries", () => {
  test("security: unauthenticated visitor cannot access /career @smoke @security", async ({ page }) => {
    await page.goto("/career");
    await page.waitForLoadState("domcontentloaded");

    // Must show login screen, not career facts
    await expect(page.locator("#cvortex-title")).toHaveText("CVortex");
    await expect(page.getByLabel("Электронная почта")).toBeVisible();
    await expect(page.getByText("Факты вашей карьеры")).not.toBeVisible();
  });

  test("security: unauthenticated visitor cannot access /diagnostics @security", async ({ page }) => {
    await page.goto("/diagnostics");
    await page.waitForLoadState("domcontentloaded");

    // Must show login screen
    await expect(page.getByLabel("Электронная почта")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Журнал инцидентов" })).not.toBeVisible();
  });

  test("security: non-admin user is rejected from /diagnostics with 403 screen @critical @security", async ({ page }) => {
    // Mock authenticated user as regular user role
    await page.route("**/api/v1/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "user-regular-01",
            email: "standard-user@test.cvortex",
            role: "user",
            status: "active",
          },
        }),
      });
    });

    await page.goto("/diagnostics");

    await expect(page.getByRole("heading", { name: "Доступ запрещён" })).toBeVisible();
    await expect(page.getByText("Диагностика доступна только администраторам.")).toBeVisible();
    await expect(page.getByRole("link", { name: "Вернуться к рабочему пространству" })).toBeVisible();
  });

  test("security: admin user has access to /diagnostics and sees admin utilities @critical @security", async ({ page }) => {
    // Mock authenticated admin
    await page.route("**/api/v1/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "user-admin-01",
            email: "admin@test.cvortex",
            role: "admin",
            status: "active",
          },
        }),
      });
    });

    await page.route("**/api/v1/diagnostics/incidents*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            data: [],
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: 0,
          },
        }),
      });
    });

    await page.goto("/diagnostics");

    // The diagnostics workspace must be loaded
    await expect(page.locator(".diagnostics-shell")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Error Center" })).toBeVisible();
  });
});

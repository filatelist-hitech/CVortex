import { test, expect } from "../fixtures/test-fixtures";

test.describe("Responsive UI & Viewport Navigation", () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate
    await page.route("**/api/v1/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "user-resp-01",
            email: "responsive@test.cvortex",
            role: "user",
            status: "active",
          },
        }),
      });
    });

    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ data: { facts: [], claims: [], sources: [] } }) });
    });
  });

  test("responsive: mobile viewport 390px has no horizontal overflow @responsive", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/career");
    await page.waitForLoadState("domcontentloaded");

    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);

    expect(scrollWidth).toBeLessThanOrEqual(clientWidth);
  });

  test("responsive: mobile navigation details drawer opens and closes cleanly @responsive", async ({ page }) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto("/career");

    const menuSummary = page.locator("summary", { hasText: /меню/i });
    await expect(menuSummary).toBeVisible();

    // Open menu
    await menuSummary.click();
    const nav = page.locator("nav[aria-label='Основное меню']");
    await expect(nav).toBeVisible();

    // Close menu button
    const closeBtn = page.getByRole("button", { name: "Закрыть меню" });
    await expect(closeBtn).toBeVisible();
    await closeBtn.click();
  });

  test("responsive: compact mobile viewport 320px preserves layout integrity @responsive", async ({ page }) => {
    await page.setViewportSize({ width: 320, height: 568 });
    await page.goto("/career");

    // Title and actions must still fit
    await expect(page.getByRole("heading", { name: "Факты вашей карьеры" })).toBeVisible();
    const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
    const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);

    expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1); // Allow 1px rounding
  });
});

import { test, expect } from "../fixtures/test-fixtures";

test.describe("Accessibility (A11y) — Automated Scans & Focus Traps", () => {
  test("a11y: login page conforms to WCAG 2.1 AA standards @a11y", async ({ page, checkA11y }) => {
    await page.goto("/");
    await page.waitForLoadState("domcontentloaded");

    await checkA11y(page, "Login Screen");
  });

  test("a11y: career workspace conforms to WCAG 2.1 AA standards @a11y", async ({ page, checkA11y }) => {
    await page.route("**/api/v1/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: { id: "a11y-user", email: "a11y@test.cvortex", role: "user", status: "active" },
        }),
      });
    });

    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            facts: [
              {
                id: "f-1",
                fact_type: "skill",
                status: "CONFIRMED",
                assertion_original: "PostgreSQL transaction design",
                provenance_type: "user_manual",
                source_excerpt: "Hand crafted",
              },
            ],
            claims: [],
            sources: [],
          },
        }),
      });
    });

    await page.goto("/career");
    await page.waitForLoadState("domcontentloaded");

    await checkA11y(page, "Career Workspace");
  });

  test("a11y: account dialog traps focus and restores focus on Escape @a11y @critical", async ({ page }) => {
    await page.route("**/api/v1/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: { id: "a11y-user", email: "a11y@test.cvortex", role: "user", status: "active" },
        }),
      });
    });

    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ data: { facts: [], claims: [], sources: [] } }) });
    });

    await page.goto("/career");

    const trigger = page.locator(".workspace-account-trigger, #account-trigger");
    await expect(trigger).toBeVisible();
    await trigger.click();

    const dialog = page.locator("dialog.workspace-account-dialog, dialog#account-dialog");
    await expect(dialog).toBeVisible();

    // Press Escape
    await page.keyboard.press("Escape");

    // Dialog must close
    await expect(dialog).not.toBeVisible();

    // Focus must return to trigger
    await expect(trigger).toBeFocused();
  });
});

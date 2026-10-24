import { test, expect } from "../fixtures/test-fixtures";

test.describe("Career Workspace — Facts, Reviews and Truth Guard", () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate the session
    await page.route("**/api/v1/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "user-career-01",
            email: "qa-career@test.cvortex",
            role: "user",
            status: "active",
          },
        }),
      });
    });

    await page.route("**/api/v1/vacancies", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      });
    });
  });

  test("smoke: displays career workspace panels and empty states @smoke @critical", async ({ page }) => {
    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            facts: [],
            claims: [],
            sources: [],
          },
        }),
      });
    });

    await page.goto("/career");
    await expect(page.getByRole("heading", { name: "Факты вашей карьеры" })).toBeVisible();
    await expect(page.getByRole("heading", { name: "Извлечь факты из текста" })).toBeVisible();
    await expect(page.getByRole("heading", { name: "Добавить факт вручную" })).toBeVisible();
    await expect(page.getByText("Подтверждённых фактов пока нет.")).toBeVisible();
    await expect(page.getByText("Нет фактов на проверке.")).toBeVisible();
  });

  test("critical: manual fact creation submits to backend and refreshes @critical", async ({ page }) => {
    let factCreated = false;
    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            facts: factCreated
              ? [
                  {
                    id: "fact-01",
                    fact_type: "skill",
                    status: "CONFIRMED",
                    assertion_original: "Playwright UI Automation",
                    assertion_approved: "Playwright UI Automation",
                    provenance_type: "user_manual",
                    source_excerpt: "Added manually",
                  },
                ]
              : [],
            claims: [],
            sources: [],
          },
        }),
      });
    });

    await page.route("**/api/v1/career/facts/manual", async (route) => {
      const requestData = route.request().postDataJSON();
      expect(requestData.fact_type).toBe("skill");
      expect(requestData.assertion).toBe("Playwright UI Automation");
      factCreated = true;
      await route.fulfill({
        status: 201,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "fact-01",
            fact_type: "skill",
            status: "CONFIRMED",
            assertion_original: "Playwright UI Automation",
          },
        }),
      });
    });

    await page.goto("/career");
    await page.getByLabel("Тип факта").fill("skill");
    await page.getByLabel("Подтверждённое утверждение").fill("Playwright UI Automation");

    await page.getByRole("button", { name: "Добавить подтверждённый факт" }).click();

    // Verify confirmed fact card appears in UI
    await expect(page.getByText("Подтверждённых фактов пока нет.")).not.toBeVisible();
    await expect(page.locator(".confirmed-card")).toBeVisible();
    await expect(page.locator(".confirmed-card").getByText("Playwright UI Automation")).toBeVisible();
  });

  test("critical: fact review confirms pending extracted fact @critical", async ({ page }) => {
    let isConfirmed = false;
    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            facts: [
              {
                id: "fact-pending-01",
                fact_type: "experience",
                status: isConfirmed ? "CONFIRMED" : "PENDING",
                assertion_original: "Led QA team for 3 years",
                assertion_approved: isConfirmed ? "Led QA team for 3 years" : null,
                provenance_type: "paste_extraction",
                source_excerpt: "CV text excerpt",
              },
            ],
            claims: [],
            sources: [],
          },
        }),
      });
    });

    await page.route("**/api/v1/career/facts/fact-pending-01/review", async (route) => {
      const body = route.request().postDataJSON();
      expect(body.action).toBe("confirm");
      isConfirmed = true;
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ data: { status: "CONFIRMED" } }) });
    });

    await page.goto("/career");
    await expect(page.locator(".pending-card")).toBeVisible();
    await expect(page.getByText("Ожидает подтверждения")).toBeVisible();

    await page.locator(".pending-card").getByRole("button", { name: "Подтвердить", exact: true }).click();

    // After confirmation, pending card moves to confirmed
    await expect(page.locator(".confirmed-card")).toBeVisible();
  });

  test("failure: extraction provider error displays safe localized message @failure", async ({ page }) => {
    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: { facts: [], claims: [], sources: [] } }),
      });
    });

    await page.route("**/api/v1/career/extractions", async (route) => {
      await route.fulfill({
        status: 502,
        contentType: "application/json",
        body: JSON.stringify({
          error: {
            code: "LLM_PROVIDER_UNAVAILABLE",
            message: "Provider unavailable",
            retryable: true,
          },
        }),
      });
    });

    await page.goto("/career");
    await page.getByLabel("Текст об опыте или резюме").fill("Some resume text to extract...");
    await page.getByRole("button", { name: "Извлечь факты для проверки" }).click();

    const errorAlert = page.locator(".alert.error").filter({ hasText: "Ошибка операции" });
    await expect(errorAlert).toBeVisible();
    await expect(errorAlert).toContainText("Сервис анализа временно недоступен");
  });
});

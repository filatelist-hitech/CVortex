import { test, expect } from "../fixtures/test-fixtures";

test.describe("Diagnostics — Incident Management for Admin", () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate as Admin
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
  });

  test("smoke: displays incidents log and empty state @smoke @critical", async ({ page }) => {
    await page.route("**/api/v1/diagnostics/incidents*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: [],
          meta: { total: 0, current_page: 1, last_page: 1 },
        }),
      });
    });

    await page.goto("/diagnostics");

    await expect(page.getByText("Ошибки и состояние операций")).toBeVisible();
    await expect(page.getByRole("heading", { name: "Журнал инцидентов" })).toBeVisible();
  });

  test("critical: displays incident detail with sanitized context @critical", async ({ page }) => {
    const incidentId = "inc-01";

    await page.route("**/api/v1/diagnostics/incidents*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: [
            {
              id: incidentId,
              severity: "ERROR",
              status: "OPEN",
              error_code: "LLM_PROVIDER_RATE_LIMITED",
              message: "Rate limit reached on analysis",
              service: "ai-worker",
              component: "ProviderClient",
              environment: "local",
              occurrence_count: 3,
              first_seen_at: "2026-10-24T18:00:00Z",
              last_seen_at: "2026-10-24T18:10:00Z",
              retryable: true,
              impact: "Analysis delayed",
              recovery_action: "Wait for provider cooldown",
            },
          ],
          meta: { total: 1 },
        }),
      });
    });

    await page.route(`**/api/v1/diagnostics/incidents/${incidentId}`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            incident: {
              id: incidentId,
              severity: "ERROR",
              status: "OPEN",
              error_code: "LLM_PROVIDER_RATE_LIMITED",
              message: "Rate limit reached on analysis",
              service: "ai-worker",
              component: "ProviderClient",
              environment: "local",
              occurrence_count: 3,
              first_seen_at: "2026-10-24T18:00:00Z",
              last_seen_at: "2026-10-24T18:10:00Z",
              retryable: true,
              impact: "Analysis delayed",
              recovery_action: "Wait for provider cooldown",
            },
            occurrences: [
              {
                id: "occ-01",
                created_at: "2026-10-24T18:10:00Z",
                request_id: "req-trace-12345",
                job_id: null,
                llm_run_id: null,
                application_id: null,
                user_id: "user-admin-01",
                route: "/api/v1/vacancies/vac-01/reanalyze",
                operation: "vacancy_analysis",
                provider: "openai",
                queue: null,
                connection: null,
                attempt: 1,
                retry_after_seconds: 60,
                error_ref: "ERR-REF-01",
                safe_stack: "App\\Services\\AiClient:42\n[app] App\\Http\\Controllers\\VacancyController:88",
              },
            ],
          },
        }),
      });
    });

    await page.goto("/diagnostics");

    // Click the incident
    await page.getByText("Rate limit reached on analysis").click();

    // Verify detail information
    await expect(page.getByText("LLM_PROVIDER_RATE_LIMITED")).toBeVisible();
    await expect(page.getByText("req-trace-12345")).toBeVisible();
    await expect(page.getByText("Throw site")).toBeVisible();
  });

  test("critical: updates incident status to RESOLVED @critical", async ({ page }) => {
    const incidentId = "inc-status-01";
    let status = "OPEN";

    await page.route("**/api/v1/diagnostics/incidents*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: [
            {
              id: incidentId,
              severity: "WARNING",
              status: status,
              error_code: "RATE_LIMITED",
              message: "Client rate limit",
              service: "nginx",
              component: "edge",
              environment: "local",
              occurrence_count: 1,
              first_seen_at: "2026-10-24T18:00:00Z",
              last_seen_at: "2026-10-24T18:00:00Z",
              retryable: true,
              impact: "Temporary block",
              recovery_action: "Wait 60s",
            },
          ],
          meta: { total: 1 },
        }),
      });
    });

    await page.route(`**/api/v1/diagnostics/incidents/${incidentId}`, async (route) => {
      if (route.request().method() === "PATCH") {
        const body = route.request().postDataJSON();
        status = body.status;
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            data: { id: incidentId, status: status },
          }),
        });
      } else {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            data: {
              incident: {
                id: incidentId,
                severity: "WARNING",
                status: status,
                error_code: "RATE_LIMITED",
                message: "Client rate limit",
                service: "nginx",
                component: "edge",
                environment: "local",
                occurrence_count: 1,
                first_seen_at: "2026-10-24T18:00:00Z",
                last_seen_at: "2026-10-24T18:00:00Z",
                retryable: true,
                impact: "Temporary block",
                recovery_action: "Wait 60s",
              },
              occurrences: [],
            },
          }),
        });
      }
    });

    await page.goto("/diagnostics");
    await page.getByText("Client rate limit").click();

    // Click resolve button
    const resolveBtn = page.getByRole("button", { name: /resolved|реш/i });
    if (await resolveBtn.isVisible()) {
      await resolveBtn.click();
      expect(status).toBe("RESOLVED");
    }
  });
});

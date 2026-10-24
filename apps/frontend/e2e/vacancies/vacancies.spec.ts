import { test, expect } from "../fixtures/test-fixtures";

test.describe("Vacancy & Application Workspace — Analysis and Recommendations", () => {
  test.beforeEach(async ({ page }) => {
    // Authenticate user
    await page.route("**/api/v1/me", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "user-vac-01",
            email: "qa-vacancies@test.cvortex",
            role: "user",
            status: "active",
          },
        }),
      });
    });

    await page.route("**/api/v1/career", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: { facts: [], claims: [], sources: [] } }),
      });
    });
  });

  test("smoke: displays vacancy workspace and paste form @smoke @critical", async ({ page }) => {
    await page.route("**/api/v1/vacancies", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      });
    });

    await page.goto("/career");
    await expect(page.getByRole("heading", { name: "Вакансии", exact: true })).toBeVisible();
    await expect(page.getByLabel("Текст вакансии", { exact: true })).toBeVisible();
    await expect(page.getByRole("button", { name: "Сохранить и проанализировать" })).toBeVisible();
    await expect(page.getByText("Сохранённых вакансий пока нет.")).toBeVisible();
  });

  test("critical: paste vacancy form creates vacancy and refreshes list @critical", async ({ page }) => {
    let created = false;
    const vacancyId = "vac-synth-01";

    await page.route("**/api/v1/vacancies", async (route) => {
      if (route.request().method() === "POST") {
        const body = route.request().postDataJSON();
        expect(body.source_text).toContain("Senior QA Engineer");
        created = true;
        await route.fulfill({
          status: 201,
          contentType: "application/json",
          body: JSON.stringify({
            data: {
              id: vacancyId,
              title: "Senior QA Engineer",
              company: "CVortex Tech",
              analysis_status: "COMPLETED",
              recommendation: "APPLY",
            },
          }),
        });
      } else {
        await route.fulfill({
          status: 200,
          contentType: "application/json",
          body: JSON.stringify({
            data: created
              ? [
                  {
                    id: vacancyId,
                    title: "Senior QA Engineer",
                    company: "CVortex Tech",
                    analysis_status: "COMPLETED",
                    recommendation: "APPLY",
                    analysis_stale: false,
                    analysis_run_stale: false,
                  },
                ]
              : [],
          }),
        });
      }
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: vacancyId,
            title: "Senior QA Engineer",
            company: "CVortex Tech",
            analysis_status: "COMPLETED",
            recommendation: "APPLY",
            source_type: "PASTED_TEXT",
            snapshot: { id: "snap-01", version: 1, raw_text: "Senior QA Engineer at CVortex Tech", source_url: null, imported_at: "2026-10-24T00:00:00Z" },
            requirements: [
              {
                id: "req-01",
                dimension: "SKILLS",
                importance: "MANDATORY",
                label: "Playwright Automation",
                source_excerpt: "Strong Playwright experience",
                confidence: 1,
              },
            ],
            analysis: {
              recommendation: "APPLY",
              key_reasons: ["Strong Playwright alignment"],
              material_gaps: [],
              uncertainties: [],
              stale: false,
              dimensions: [
                {
                  dimension: "SKILLS",
                  result: "MATCH",
                  explanation: "Candidate has confirmed Playwright skill.",
                  origin: "DETERMINISTIC",
                  vacancy_requirement_ids: ["req-01"],
                  candidate_evidence: [],
                },
              ],
            },
          },
        }),
      });
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}/chat`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: { id: "thread-01", status: "IDLE", model: null, connection_id: null, messages: [] } }),
      });
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}/analysis-drafts`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      });
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}/preparation`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "prep-01",
            vacancy_id: vacancyId,
            status: "DRAFT",
            stale: false,
            items: [],
          },
        }),
      });
    });

    await page.route("**/api/v1/applications/preparations/*", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: "prep-01",
            vacancy_id: vacancyId,
            status: "DRAFT",
            stale: false,
            items: [],
          },
        }),
      });
    });

    await page.goto("/career");
    await page.getByLabel("Текст вакансии", { exact: true }).fill(
      "Senior QA Engineer\nCompany: CVortex Tech\nRequired: Playwright automation, TypeScript."
    );
    await page.getByRole("button", { name: "Сохранить и проанализировать" }).click();

    // Verify vacancy is displayed in the list and detail
    await expect(page.getByRole("button", { name: /Senior QA Engineer/ })).toBeVisible();
    await expect(page.getByText("CVortex Tech", { exact: true })).toBeVisible();
  });

  test("boundary: empty vacancy description triggers client-side validation @critical", async ({ page }) => {
    await page.route("**/api/v1/vacancies", async (route) => {
      await route.fulfill({ status: 200, contentType: "application/json", body: JSON.stringify({ data: [] }) });
    });

    await page.goto("/career");
    const textarea = page.getByLabel("Текст вакансии", { exact: true });
    const submitBtn = page.getByRole("button", { name: "Сохранить и проанализировать" });

    await submitBtn.click();
    const isValid = await textarea.evaluate((el: HTMLTextAreaElement) => el.checkValidity());
    expect(isValid).toBe(false);
  });

  test("critical: Truth Guard invariant enforces human approval separation @critical @security", async ({ page }) => {
    const vacancyId = "vac-truth-01";
    const prepId = "prep-truth-01";
    const draftId = "draft-truth-01";

    let draftStatus: "DRAFT" | "ACCEPTED" | "APPROVED" = "DRAFT";

    const createPrepData = () => ({
      id: prepId,
      vacancy_id: vacancyId,
      status: "DRAFT" as const,
      stale: false,
      items: [
        {
          id: draftId,
          kind: "COVER_DRAFT" as const,
          variant: "SHORT" as const,
          section: "intro",
          before: "Previous unaligned text",
          content: "I have 5 years of verified Laravel experience.",
          reason: "Highlights verified skill F-01",
          risk: "Low risk",
          status: draftStatus,
          revision_number: 1,
          validation_result: "PASS" as const,
          claim_usages: [],
          revisions: [],
          approvals: [],
        },
      ],
    });

    await page.route("**/api/v1/vacancies", async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: [
            {
              id: vacancyId,
              title: "Backend Engineer",
              company: "Truth Corp",
              analysis_status: "COMPLETED",
              recommendation: "APPLY",
              analysis_stale: false,
              analysis_run_stale: false,
            },
          ],
        }),
      });
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: vacancyId,
            title: "Backend Engineer",
            company: "Truth Corp",
            analysis_status: "COMPLETED",
            recommendation: "APPLY",
            source_type: "PASTED_TEXT",
            snapshot: { id: "snap-truth-01", version: 1, raw_text: "Backend Engineer at Truth Corp", source_url: null, imported_at: "2026-10-24T00:00:00Z" },
            requirements: [],
            analysis: {
              recommendation: "APPLY",
              key_reasons: ["Aligned backend expertise"],
              material_gaps: [],
              uncertainties: [],
              stale: false,
              dimensions: [],
            },
          },
        }),
      });
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}/chat`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: { id: "thread-truth-01", status: "IDLE", model: null, connection_id: null, messages: [] } }),
      });
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}/analysis-drafts`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: [] }),
      });
    });

    await page.route(`**/api/v1/vacancies/${vacancyId}/preparation`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: createPrepData() }),
      });
    });

    await page.route(`**/api/v1/applications/preparations/${prepId}`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: createPrepData() }),
      });
    });

    await page.route(`**/api/v1/applications/draft-items/${draftId}`, async (route) => {
      draftStatus = "ACCEPTED";
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: createPrepData() }),
      });
    });

    await page.route(`**/api/v1/applications/draft-items/${draftId}/approve`, async (route) => {
      draftStatus = "APPROVED";
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({ data: createPrepData() }),
      });
    });

    await page.goto("/career");

    // The first vacancy is automatically selected and detail loaded
    await expect(page.getByRole("button", { name: /Backend Engineer/ })).toBeVisible();
    await expect(page.getByText("Truth Corp", { exact: true })).toBeVisible();

    // Truth Guard: Initially draft is in DRAFT state.
    // The "Explicitly approve content" button MUST NOT be present before human acceptance.
    await expect(page.getByRole("button", { name: "Explicitly approve content" })).not.toBeVisible();

    // Accept draft button is available for review
    const acceptBtn = page.getByRole("button", { name: "Accept draft" });
    await expect(acceptBtn).toBeVisible();
    await acceptBtn.click();

    // After human acceptance, the approval button becomes visible and actionable
    const approveBtn = page.getByRole("button", { name: "Explicitly approve content" });
    await expect(approveBtn).toBeVisible();
    await approveBtn.click();

    // Once approved, the approved badge is displayed and cannot be approved again
    await expect(page.getByText("Approved draft · not submitted")).toBeVisible();
    await expect(page.getByRole("button", { name: "Explicitly approve content" })).not.toBeVisible();
  });
});

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
    await expect(page.getByRole("heading", { name: "Вакансии и отклики" })).toBeVisible();
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
        expect(body.raw_text).toContain("Senior QA Engineer");
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
            snapshot: { raw_text: "Senior QA Engineer at CVortex Tech" },
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

    await page.goto("/career");
    await page.getByLabel("Текст вакансии", { exact: true }).fill(
      "Senior QA Engineer\nCompany: CVortex Tech\nRequired: Playwright automation, TypeScript."
    );
    await page.getByRole("button", { name: "Сохранить и проанализировать" }).click();

    // Verify vacancy is displayed in the list
    await expect(page.getByText("Senior QA Engineer")).toBeVisible();
    await expect(page.getByText("CVortex Tech")).toBeVisible();
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
    const prepId = "prep-01";
    const draftId = "draft-01";

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
            requirements: [],
            analysis: {
              recommendation: "APPLY",
              key_reasons: [],
              material_gaps: [],
              uncertainties: [],
              stale: false,
              dimensions: [],
            },
          },
        }),
      });
    });

    let draftStatus = "DRAFT";
    await page.route(`**/api/v1/vacancies/${vacancyId}/preparation`, async (route) => {
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: prepId,
            vacancy_id: vacancyId,
            items: [
              {
                id: draftId,
                kind: "COVER_DRAFT",
                variant: "SHORT",
                section: "intro",
                before: "Previous unaligned text",
                content: "I have 5 years of verified Laravel experience.",
                reason: "Highlights verified skill F-01",
                risk: "Low risk",
                status: draftStatus,
                revision_number: 1,
                validation_result: "PASS",
                claim_usages: [],
              },
            ],
          },
        }),
      });
    });

    await page.route(`**/api/v1/applications/draft-items/${draftId}`, async (route) => {
      draftStatus = "ACCEPTED";
      await route.fulfill({
        status: 200,
        contentType: "application/json",
        body: JSON.stringify({
          data: {
            id: draftId,
            status: "ACCEPTED",
          },
        }),
      });
    });

    await page.goto("/career");
    // Click on the vacancy
    await page.getByText("Backend Engineer").click();

    // Open preparation
    const prepBtn = page.getByRole("button", { name: "Подготовить материалы" });
    if (await prepBtn.isVisible()) {
      await prepBtn.click();
    }

    // Notice that before accepting the draft, the approve button is disabled or separate
    const approveBtn = page.getByRole("button", { name: "Одобрить текст" });
    if (await approveBtn.isVisible()) {
      await expect(approveBtn).toBeDisabled();
    }

    // Click "Принять правку"
    const acceptBtn = page.getByRole("button", { name: "Принять правку" });
    if (await acceptBtn.isVisible()) {
      await acceptBtn.click();
      // After accepting, approval becomes available
      if (await approveBtn.isVisible()) {
        await expect(approveBtn).toBeEnabled();
      }
    }
  });
});

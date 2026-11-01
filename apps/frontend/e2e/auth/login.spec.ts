import { test, expect } from "../fixtures/test-fixtures";

test.describe("Authentication — Login and Session Lifecycle", () => {
  test("smoke: displays login interface with brand elements and accessibility @smoke @critical", async ({ page }) => {
    await page.goto("/");
    await expect(page.locator("#cvortex-title")).toHaveText("CVortex");
    await expect(page.getByText("Your career, in context.")).toBeVisible();

    const emailInput = page.getByLabel("Электронная почта");
    const passwordInput = page.getByLabel("Пароль", { exact: true });
    const submitBtn = page.locator("form").getByRole("button", { name: "Войти" });

    await expect(emailInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
    await expect(submitBtn).toBeVisible();
    await expect(submitBtn).toBeEnabled();
  });

  test("negative: invalid password displays server error without stack traces @critical", async ({ page, createSyntheticEmail }) => {
    await page.goto("/");

    const syntheticEmail = createSyntheticEmail("wrong-pass");
    await page.getByLabel("Электронная почта").fill(syntheticEmail);
    // Min length is 15
    await page.getByLabel("Пароль", { exact: true }).fill("wrong-password-15chars");
    await page.locator("form").getByRole("button", { name: "Войти" }).click();

    const errorMessage = page.locator(".error, [role='alert']");
    await expect(errorMessage).toBeVisible();
    const errorText = await errorMessage.textContent();

    // Verify error does not leak DB/server stack trace or secrets
    expect(errorText).not.toContain("SQLSTATE");
    expect(errorText).not.toContain("exception");
    expect(errorText).not.toContain("stack trace");
  });

  test("boundary: empty inputs trigger HTML5 validation @critical", async ({ page }) => {
    await page.goto("/");

    const emailInput = page.getByLabel("Электронная почта");
    const submitBtn = page.locator("form").getByRole("button", { name: "Войти" });

    await submitBtn.click();
    const isEmailValid = await emailInput.evaluate((el: HTMLInputElement) => el.checkValidity());
    expect(isEmailValid).toBe(false);
  });

  test("boundary: password shorter than 15 characters triggers validation @critical", async ({ page }) => {
    await page.goto("/");

    await page.getByLabel("Электронная почта").fill("user@example.com");
    const passwordInput = page.getByLabel("Пароль", { exact: true });
    await passwordInput.fill("short-pass");

    const isValid = await passwordInput.evaluate((el: HTMLInputElement) => el.checkValidity());
    expect(isValid).toBe(false);
  });

  test("critical: login page does not expose registration tab @smoke", async ({ page }) => {
    await page.goto("/");

    await expect(page.locator(".tabs")).not.toBeVisible();
    await expect(page.getByRole("button", { name: "Регистрация" })).not.toBeVisible();
    await expect(page.locator("form").getByRole("button", { name: "Войти" })).toBeEnabled();
  });
});

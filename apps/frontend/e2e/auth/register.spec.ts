import { test, expect } from "../fixtures/test-fixtures";

test.describe("Authentication — Invite-only Registration", () => {
  test("security: registration without invitation token is disabled @smoke @security", async ({ page }) => {
    await page.goto("/register");

    // Without token fragment, the form must indicate requirement of operator invitation
    await expect(page.getByText("Откройте ссылку регистрации, полученную от оператора.")).toBeVisible();

    const submitBtn = page.getByRole("button", { name: "Создать аккаунт" });
    await expect(submitBtn).toBeDisabled();
  });

  test("negative: registration with invalid invitation token displays server error @critical", async ({ page, createSyntheticEmail }) => {
    // 64-char fake hex token
    const invalidToken = "a".repeat(64);
    await page.goto(`/register#token=${invalidToken}`);

    // With token, the guidance error is hidden and button becomes enabled once filled
    const emailInput = page.getByLabel("Электронная почта");
    const passwordInput = page.getByLabel("Пароль", { exact: true });
    const confirmInput = page.getByLabel("Подтвердите пароль");

    await emailInput.fill(createSyntheticEmail("reg-fail"));
    await passwordInput.fill("valid-secret-password-15chars");
    await confirmInput.fill("valid-secret-password-15chars");

    const submitBtn = page.locator("form").getByRole("button", { name: "Создать аккаунт" });
    await expect(submitBtn).toBeEnabled();
    await submitBtn.click();

    // The backend rejects the nonexistent/invalid invitation token
    const errorAlert = page.locator(".error[role='alert']");
    await expect(errorAlert).toBeVisible();
    await expect(errorAlert).toHaveText(/не удалось|ошибка|проверьте|токен|приглашен/i);
  });

  test("boundary: registration password mismatch is rejected @critical", async ({ page, createSyntheticEmail }) => {
    const fakeToken = "b".repeat(64);
    await page.goto(`/register#token=${fakeToken}`);

    await page.getByLabel("Электронная почта").fill(createSyntheticEmail("mismatch"));
    await page.getByLabel("Пароль", { exact: true }).fill("password-alpha-15chars");
    await page.getByLabel("Подтвердите пароль").fill("password-beta-15chars");

    await page.locator("form").getByRole("button", { name: "Создать аккаунт" }).click();

    const errorAlert = page.locator(".error[role='alert']");
    await expect(errorAlert).toBeVisible();
  });
});

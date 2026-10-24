import { test as base, expect, type Page } from "@playwright/test";
import AxeBuilder from "@axe-core/playwright";

export interface TestUser {
  email: string;
  password: string;
  role: "user" | "admin";
}

export class AuthHelper {
  constructor(private readonly page: Page) {}

  async login(email: string, password: string): Promise<void> {
    await this.page.goto("/");
    await this.page.waitForLoadState("domcontentloaded");

    // If already logged in, log out first
    const accountTrigger = this.page.locator("#account-trigger, .workspace-account-trigger");
    if (await accountTrigger.isVisible().catch(() => false)) {
      await this.logout();
    }

    const emailInput = this.page.getByLabel("Электронная почта");
    await expect(emailInput).toBeVisible();
    await emailInput.fill(email);

    const passwordInput = this.page.getByLabel("Пароль", { exact: true });
    await passwordInput.fill(password);

    await this.page.locator("form").getByRole("button", { name: "Войти", exact: true }).click();
  }

  async logout(): Promise<void> {
    const trigger = this.page.locator("#account-trigger, .workspace-account-trigger");
    if (await trigger.isVisible().catch(() => false)) {
      await trigger.click();
      const logoutBtn = this.page.getByRole("button", { name: /выйти/i });
      if (await logoutBtn.isVisible().catch(() => false)) {
        await logoutBtn.click();
        await expect(this.page.getByLabel("Электронная почта")).toBeVisible();
      }
    }
  }

  async register(token: string, email: string, password: string): Promise<void> {
    await this.page.goto(`/register#token=${token}`);
    await this.page.waitForLoadState("domcontentloaded");

    const emailInput = this.page.getByLabel("Электронная почта");
    await expect(emailInput).toBeVisible();
    await emailInput.fill(email);

    const passwordInput = this.page.getByLabel("Пароль", { exact: true });
    await passwordInput.fill(password);

    const confirmInput = this.page.getByLabel("Подтвердите пароль");
    await confirmInput.fill(password);

    await this.page.getByRole("button", { name: "Создать аккаунт" }).click();
  }
}

export interface ExtendedFixtures {
  authHelper: AuthHelper;
  checkA11y: (page: Page, contextName?: string) => Promise<void>;
  createSyntheticEmail: (prefix?: string) => string;
}

export const test = base.extend<ExtendedFixtures>({
  authHelper: async ({ page }, use) => {
    await use(new AuthHelper(page));
  },
  createSyntheticEmail: async ({}, use) => {
    const generator = (prefix = "user") => {
      const id = Math.random().toString(36).substring(2, 9);
      return `${prefix}-${id}@qa-synthetic.test`;
    };
    await use(generator);
  },
  checkA11y: async ({}, use) => {
    const checker = async (page: Page, contextName = "page") => {
      // Analyze the page with axe-core
      const accessibilityScanResults = await new AxeBuilder({ page })
        .withTags(["wcag2a", "wcag2aa"])
        .disableRules(["color-contrast"]) // Optional fine-tuning if needed by design tokens
        .analyze();

      const criticalViolations = accessibilityScanResults.violations.filter(
        v => v.impact === "critical" || v.impact === "serious"
      );

      if (criticalViolations.length > 0) {
        console.warn(
          `[A11Y WARNING] ${contextName} has ${criticalViolations.length} critical/serious violations:`,
          criticalViolations.map(v => `${v.id}: ${v.description}`)
        );
      }
      expect(criticalViolations, `A11y violations found in ${contextName}`).toEqual([]);
    };
    await use(checker);
  },
});

export { expect };

"use client";

import { useEffect, useState, useSyncExternalStore } from "react";
import { useRouter } from "next/navigation";
import Link from "next/link";
import CareerWorkspace from "./career-workspace";
import Diagnostics from "./diagnostics";
import AdminSystemSettings from "./admin-settings";
import WorkspaceShell from "./workspace-shell";
import AiProviderSettings from "./ai-provider-settings";
import TodayWorkspace from "./today-workspace";
import VacancyImportWorkspace from "./vacancy-import";
import DiscoverInbox from "./discover-inbox";
import { VortexSwissLogo } from "./vortex-logo";

type User = { id: string; email: string; role: string; status: string };

const subscribeHydration = () => () => {};
const clientHydrated = () => true;
const serverHydrated = () => false;

const csrf = () => decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");

const safeErrorMessages: Record<string, string> = {
  PROVIDER_ERROR: "Не удалось извлечь факты. Их можно добавить вручную.",
  INVALID_EXTRACTION_RESULT: "Извлечение вернуло неподдерживаемые данные. Факты не подтверждены.",
  CAREER_OPERATION_FAILED: "Не удалось выполнить операцию с карьерой.",
  VACANCY_OPERATION_FAILED: "Не удалось выполнить операцию с вакансией.",
  LLM_OUTPUT_INVALID: "Анализ вернул неподдерживаемые данные. Результат не принят.",
  LLM_REQUEST_REFUSED: "Провайдер не обработал запрос. Проверьте его перед повтором.",
  LLM_RESPONSE_INCOMPLETE: "Провайдер вернул неполный результат. Проверьте размер исходного текста и ограничения операции.",
  LLM_PROVIDER_FAILED: "Не удалось выполнить анализ. Обратитесь к администратору.",
  LLM_PROVIDER_CONFIGURATION: "Администратору нужно настроить провайдера анализа.",
  LLM_PROVIDER_RATE_LIMITED: "Достигнут лимит запросов к провайдеру анализа. Подождите перед повтором.",
  LLM_PROVIDER_UNAVAILABLE: "Сервис анализа временно недоступен.",
  INTERNAL_ERROR: "Не удалось выполнить операцию.",
  PERMISSION_DENIED: "У вас нет доступа к этому действию.",
  AUTH_REQUIRED: "Войдите и повторите запрос.",
  VALIDATION_FAILED: "Проверьте введённые данные.",
};

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly retryable: boolean,
    public readonly code: string,
    public readonly retryAfterSeconds: number | null = null,
  ) {
    super(message);
    this.name = "ApiError";
  }
}

function errorExplanation(code: string, retryable: boolean): string {
  if (code === "LLM_PROVIDER_UNAVAILABLE") {
    return retryable ? safeErrorMessages[code] : "Ошибка провайдера анализа. Обратитесь к администратору.";
  }
  if (code === "LLM_PROVIDER_RATE_LIMITED") {
    return retryable ? safeErrorMessages[code] : "Провайдер отклонил запрос. Обратитесь к администратору.";
  }
  if (code === "RATE_LIMITED") {
    return retryable ? "Слишком много запросов. Подождите перед повтором." : "Запрос отклонён из-за ограничения частоты.";
  }

  return safeErrorMessages[code] ?? (retryable ? "Сервис временно недоступен." : "Не удалось выполнить запрос.");
}

export async function api(path: string, options: RequestInit = {}) {
  let response: Response;
  try {
    response = await fetch(path, {
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(options.method && options.method !== "GET" ? { "X-XSRF-TOKEN": csrf() } : {}),
    },
    ...options,
    });
  } catch (caught) {
    if (caught && typeof caught === "object" && "name" in caught && (caught as { name: string }).name === "AbortError") {
      throw caught;
    }
    throw new ApiError("Не удалось подключиться. Проверьте сеть. [NETWORK_ERROR] Можно повторить запрос.", true, "NETWORK_ERROR");
  }
  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const code = typeof body?.error?.code === "string" ? body.error.code : "";
    const reference = typeof body?.error?.request_id === "string" ? body.error.request_id : response.headers.get("X-Request-ID");
    const safeCode = /^[A-Z][A-Z0-9_]{2,95}$/.test(code) ? code : "REQUEST_FAILED";
    const retryable = body?.error?.retryable === true;
    const retryAfterHeader = response.headers.get("Retry-After")?.trim() ?? "";
    const parsedRetryAfter = /^(0|[1-9]\d*)$/.test(retryAfterHeader) ? Number(retryAfterHeader) : Number.NaN;
    const retryAfterSeconds = retryable && Number.isSafeInteger(parsedRetryAfter) && parsedRetryAfter <= 86400
      ? parsedRetryAfter
      : null;
    const explanation = errorExplanation(safeCode, retryable);
    const retryGuidance = retryable
      ? retryAfterSeconds !== null && retryAfterSeconds > 0
        ? ` Можно повторить запрос через ${retryAfterSeconds} с.`
        : " Можно повторить запрос."
      : "";
    throw new ApiError(`${explanation}${reference ? ` [${safeCode}] Код запроса: ${reference}` : ""}${retryGuidance}`, retryable, safeCode, retryAfterSeconds);
  }
  return response.status === 204 ? null : response.json();
}

function EyeIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z" />
      <circle cx="12" cy="12" r="3" />
    </svg>
  );
}

function EyeOffIcon() {
  return (
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      <path d="M9.88 9.88a3 3 0 1 0 4.24 4.24" />
      <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68" />
      <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61" />
      <line x1="2" y1="2" x2="22" y2="22" />
    </svg>
  );
}

export default function AccessShell({ registrationRoute = false, destination = "today", initialVacancyId }: { registrationRoute?: boolean; destination?: "today" | "career" | "diagnostics" | "vacancies" | "vacancy-import" | "admin-settings" | "settings" | "discover"; initialVacancyId?: string }) {
  const router = useRouter();
  const hydrated = useSyncExternalStore(subscribeHydration, clientHydrated, serverHydrated);
  const [token, setToken] = useState("");
  const [user, setUser] = useState<User | null>(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);
  const [showPassword, setShowPassword] = useState(false);

  useEffect(() => {
    if (registrationRoute) {
      const invitationToken = new URLSearchParams(window.location.hash.slice(1)).get("token") ?? "";
      // The fragment is an external browser value; copy it once into transient memory after mount.
      // eslint-disable-next-line react-hooks/set-state-in-effect
      if (invitationToken) setToken(invitationToken);
      window.history.replaceState(null, "", window.location.pathname + window.location.search);
    }

    void api("/api/v1/me").then((result) => setUser(result.data)).catch(() => undefined);
  }, [registrationRoute]);

  async function submit(form: HTMLFormElement) {
    setBusy(true);
    setMessage("");
    const values = Object.fromEntries(new FormData(form));
    try {
      await fetch("/sanctum/csrf-cookie", { credentials: "same-origin", headers: { Accept: "application/json" } });
      const result = await api(registrationRoute ? "/api/v1/auth/register" : "/api/v1/auth/login", {
        method: "POST",
        body: JSON.stringify(registrationRoute ? { ...values, invitation_token: token } : values),
      });
      setUser(result.data);
      form.reset();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Не удалось выполнить запрос.");
    } finally {
      setBusy(false);
    }
  }

  if (user) {
    const onSignOut = async () => {
      await api("/api/v1/auth/logout", { method: "POST" });
      setUser(null);
      router.replace("/");
    };
    if ((destination === "diagnostics" || destination === "admin-settings") && user.role !== "admin") {
      return <main className="shell"><section className="status-card"><h1>Доступ запрещён</h1><p>{destination === "admin-settings" ? "Настройки системы доступны только администраторам." : "Диагностика доступна только администраторам."}</p><Link href="/">Вернуться к рабочему пространству</Link></section></main>;
    }
    return <WorkspaceShell email={user.email} role={user.role} destination={destination} onSignOut={onSignOut} initialVacancyId={initialVacancyId}>
      {destination === "diagnostics"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell diagnostics-shell"><Diagnostics /></main>
        : destination === "admin-settings"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell admin-settings-shell"><AdminSystemSettings /></main>
        : destination === "settings"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell settings-shell"><AiProviderSettings role={user.role} /></main>
        : destination === "discover"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell discover-shell"><DiscoverInbox /></main>
        : destination === "today"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell today-shell"><TodayWorkspace email={user.email} role={user.role} /></main>
        : destination === "vacancy-import"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell vacancy-import-shell"><VacancyImportWorkspace /></main>
        : <CareerWorkspace key={user.id} userId={user.id} />}
    </WorkspaceShell>;
  }

  if (registrationRoute) {
    const registrationUnavailable = !token;

    return (
      <main className="shell auth-shell">
        <section className="status-card auth-card" aria-labelledby="cvortex-title">
          <header className="auth-header">
            <div className="auth-logo-badge">
              <VortexSwissLogo size={36} />
            </div>
            <span className="auth-system-badge">Personal Job Search OS</span>
            <h1 id="cvortex-title" className="auth-title">CVortex</h1>
            <p className="tagline auth-tagline">Регистрация по приглашению</p>
          </header>

          <form
            method="post"
            action="/api/v1/auth/register"
            className="auth-form"
            onSubmit={(event) => {
              event.preventDefault();
              void submit(event.currentTarget);
            }}
          >
            <div className="auth-field">
              <label htmlFor="auth-email" className="auth-label">Электронная почта</label>
              <div className="auth-input-wrapper">
                <input
                  id="auth-email"
                  required
                  type="email"
                  name="email"
                  autoComplete="email"
                  placeholder="name@example.com"
                  className="auth-input"
                />
              </div>
            </div>

            <div className="auth-field">
              <div className="auth-label-row">
                <label htmlFor="auth-password" className="auth-label">Пароль</label>
                <span className="auth-hint">от 15 символов</span>
              </div>
              <div className="auth-input-wrapper">
                <input
                  id="auth-password"
                  required
                  type={showPassword ? "text" : "password"}
                  name="password"
                  minLength={15}
                  maxLength={128}
                  autoComplete="new-password"
                  placeholder="•••••••••••••••"
                  className="auth-input auth-input-with-action"
                />
                {hydrated && (
                  <button
                    type="button"
                    className="auth-password-toggle"
                    onClick={() => setShowPassword(!showPassword)}
                    aria-label={showPassword ? "Скрыть пароль" : "Показать пароль"}
                    tabIndex={0}
                  >
                    {showPassword ? <EyeOffIcon /> : <EyeIcon />}
                  </button>
                )}
              </div>
            </div>

            <div className="auth-field">
              <label htmlFor="auth-password-confirm" className="auth-label">Подтвердите пароль</label>
              <div className="auth-input-wrapper">
                <input
                  id="auth-password-confirm"
                  required
                  type={showPassword ? "text" : "password"}
                  name="password_confirmation"
                  minLength={15}
                  maxLength={128}
                  autoComplete="new-password"
                  placeholder="•••••••••••••••"
                  className="auth-input"
                />
              </div>
            </div>

            {registrationUnavailable && (
              <div className="auth-error-alert" role="alert">
                <svg className="auth-error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <p className="error">Откройте ссылку регистрации, полученную от оператора.</p>
              </div>
            )}

            {message && (
              <div className="auth-error-alert" role="alert">
                <svg className="auth-error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <circle cx="12" cy="12" r="10" />
                  <line x1="12" y1="8" x2="12" y2="12" />
                  <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
                <span className="error">{message}</span>
              </div>
            )}

            <button
              type="submit"
              className="auth-submit-btn"
              disabled={!hydrated || busy || registrationUnavailable}
            >
              {!hydrated ? "Загрузка…" : busy ? "Выполнение…" : "Создать аккаунт"}
            </button>
          </form>

          <footer className="auth-footer">
            <svg className="auth-footer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
              <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
              <path d="M7 11V7a5 5 0 0 1 10 0v4" />
            </svg>
            <span>Защищённая среда данных • Доступ по приглашению</span>
          </footer>
        </section>
      </main>
    );
  }

  // Pure Authorization / Login Page (Zero registration on login page)
  return (
    <main className="shell auth-shell">
      <section className="status-card auth-card" aria-labelledby="cvortex-title">
        <header className="auth-header">
          <div className="auth-logo-badge">
            <VortexSwissLogo size={36} />
          </div>
          <span className="auth-system-badge">Personal Job Search OS</span>
          <h1 id="cvortex-title" className="auth-title">CVortex</h1>
          <p className="tagline auth-tagline">Your career, in context.</p>
        </header>

        <form
          method="post"
          action="/api/v1/auth/login"
          className="auth-form"
          onSubmit={(event) => {
            event.preventDefault();
            void submit(event.currentTarget);
          }}
        >
          <div className="auth-field">
            <label htmlFor="auth-email" className="auth-label">Электронная почта</label>
            <div className="auth-input-wrapper">
              <input
                id="auth-email"
                required
                type="email"
                name="email"
                autoComplete="email"
                placeholder="name@company.com"
                className="auth-input"
              />
            </div>
          </div>

          <div className="auth-field">
            <div className="auth-label-row">
              <label htmlFor="auth-password" className="auth-label">Пароль</label>
              <span className="auth-hint">от 15 символов</span>
            </div>
            <div className="auth-input-wrapper">
              <input
                id="auth-password"
                required
                type={showPassword ? "text" : "password"}
                name="password"
                minLength={15}
                maxLength={128}
                autoComplete="current-password"
                placeholder="•••••••••••••••"
                className="auth-input auth-input-with-action"
              />
              {hydrated && (
                <button
                  type="button"
                  className="auth-password-toggle"
                  onClick={() => setShowPassword(!showPassword)}
                  aria-label={showPassword ? "Скрыть пароль" : "Показать пароль"}
                  tabIndex={0}
                >
                  {showPassword ? <EyeOffIcon /> : <EyeIcon />}
                </button>
              )}
            </div>
          </div>

          {message && (
            <div className="auth-error-alert" role="alert">
              <svg className="auth-error-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <circle cx="12" cy="12" r="10" />
                <line x1="12" y1="8" x2="12" y2="12" />
                <line x1="12" y1="16" x2="12.01" y2="16" />
              </svg>
              <span className="error">{message}</span>
            </div>
          )}

          <button
            type="submit"
            className="auth-submit-btn"
            disabled={!hydrated || busy}
          >
            {!hydrated ? (
              "Загрузка…"
            ) : busy ? (
              <>
                <svg className="auth-spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                  <path d="M21 12a9 9 0 1 1-6.219-8.56" />
                </svg>
                <span>Вход в систему…</span>
              </>
            ) : (
              "Войти"
            )}
          </button>
        </form>

        <footer className="auth-footer">
          <svg className="auth-footer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2" />
            <path d="M7 11V7a5 5 0 0 1 10 0v4" />
          </svg>
          <span>Защищённый доступ • Приватная среда данных</span>
        </footer>
      </section>
    </main>
  );
}

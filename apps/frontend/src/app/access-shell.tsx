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

type User = { id: string; email: string; role: string; status: string };
type Mode = "login" | "register";

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

export default function AccessShell({ registrationRoute = false, destination = "today" }: { registrationRoute?: boolean; destination?: "today" | "career" | "diagnostics" | "vacancies" | "vacancy-import" | "admin-settings" | "settings" }) {
  const router = useRouter();
  const hydrated = useSyncExternalStore(subscribeHydration, clientHydrated, serverHydrated);
  const [mode, setMode] = useState<Mode>(registrationRoute ? "register" : "login");
  const [token, setToken] = useState("");
  const [user, setUser] = useState<User | null>(null);
  const [message, setMessage] = useState("");
  const [busy, setBusy] = useState(false);

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
      const result = await api(mode === "login" ? "/api/v1/auth/login" : "/api/v1/auth/register", {
        method: "POST",
        body: JSON.stringify(mode === "login" ? values : { ...values, invitation_token: token }),
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
    return <WorkspaceShell email={user.email} role={user.role} destination={destination} onSignOut={onSignOut}>
      {destination === "diagnostics"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell diagnostics-shell"><Diagnostics /></main>
        : destination === "admin-settings"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell admin-settings-shell"><AdminSystemSettings /></main>
        : destination === "settings"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell settings-shell"><AiProviderSettings /></main>
        : destination === "today"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell today-shell"><TodayWorkspace email={user.email} role={user.role} /></main>
        : destination === "vacancy-import"
        ? <main id="workspace-content" tabIndex={-1} className="career-shell vacancy-import-shell"><VacancyImportWorkspace /></main>
        : <CareerWorkspace key={user.id} userId={user.id} />}
    </WorkspaceShell>;
  }

  const registrationUnavailable = mode === "register" && (!registrationRoute || !token);

  return <main className="shell"><section className="status-card" aria-labelledby="cvortex-title"><div className="brand-mark" aria-hidden="true" /><p className="eyebrow">Вход в CVortex</p><h1 id="cvortex-title">CVortex</h1><p className="tagline">Your career, in context.</p><div className="tabs"><button type="button" className={mode === "login" ? "selected" : ""} onClick={() => setMode("login")}>Войти</button><button type="button" className={mode === "register" ? "selected" : ""} onClick={() => setMode("register")}>Регистрация</button></div><form method="post" action={mode === "login" ? "/api/v1/auth/login" : "/api/v1/auth/register"} onSubmit={(event) => { event.preventDefault(); void submit(event.currentTarget); }}><label>Электронная почта<input required type="email" name="email" autoComplete="email" /></label><label>Пароль<input required type="password" name="password" minLength={15} maxLength={128} autoComplete={mode === "login" ? "current-password" : "new-password"} /></label>{mode === "register" && <><label>Подтвердите пароль<input required type="password" name="password_confirmation" minLength={15} maxLength={128} autoComplete="new-password" /></label>{registrationUnavailable && <p className="error">Откройте ссылку регистрации, полученную от оператора.</p>}</>} {message && <p className="error" role="alert">{message}</p>}<button disabled={!hydrated || busy || registrationUnavailable}>{!hydrated ? "Загрузка…" : busy ? "Выполнение…" : mode === "login" ? "Войти" : "Создать аккаунт"}</button></form></section></main>;
}

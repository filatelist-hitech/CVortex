"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import CareerWorkspace from "./career-workspace";

type User = { id: string; email: string; role: string; status: string };
type Mode = "login" | "register";

const csrf = () => decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");

const safeErrorMessages: Record<string, string> = {
  PROVIDER_ERROR: "Career extraction could not be completed. Manual fact entry is still available.",
  INVALID_EXTRACTION_RESULT: "Career extraction returned unsupported data. Nothing was trusted.",
  CAREER_OPERATION_FAILED: "The Career operation could not be completed.",
  VACANCY_OPERATION_FAILED: "The vacancy operation could not be completed.",
  LLM_OUTPUT_INVALID: "Analysis returned unsupported data. Nothing was trusted.",
  INTERNAL_ERROR: "The operation could not be completed.",
  PERMISSION_DENIED: "You do not have access to this action.",
  AUTH_REQUIRED: "Please sign in and try again.",
  VALIDATION_FAILED: "Please check the submitted information.",
};

export class ApiError extends Error {
  constructor(message: string, public readonly retryable: boolean, public readonly code: string) {
    super(message);
    this.name = "ApiError";
  }
}

function errorExplanation(code: string, retryable: boolean): string {
  if (code === "GENERATION_UNAVAILABLE") {
    return retryable ? "Draft generation is temporarily unavailable." : "Draft generation needs administrator configuration.";
  }
  if (code === "VALIDATION_UNAVAILABLE") {
    return retryable ? "Draft truth validation is temporarily unavailable." : "Draft truth validation needs administrator configuration.";
  }
  if (code === "LLM_PROVIDER_UNAVAILABLE") {
    return retryable ? "The analysis service is temporarily unavailable." : "The analysis provider needs administrator configuration.";
  }
  if (code === "RATE_LIMITED") {
    return retryable ? "Too many requests. Wait before trying again." : "The request was rate limited.";
  }

  return safeErrorMessages[code] ?? (retryable ? "The service is temporarily unavailable." : "The request could not be completed.");
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
  } catch {
    throw new ApiError("The connection failed. Check your network. [NETWORK_ERROR] You can retry.", true, "NETWORK_ERROR");
  }
  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const code = typeof body?.error?.code === "string" ? body.error.code : "";
    const reference = typeof body?.error?.request_id === "string" ? body.error.request_id : response.headers.get("X-Request-ID");
    const safeCode = /^[A-Z][A-Z0-9_]{2,95}$/.test(code) ? code : "REQUEST_FAILED";
    const retryable = body?.error?.retryable === true;
    const explanation = errorExplanation(safeCode, retryable);
    throw new ApiError(`${explanation}${reference ? ` [${safeCode}] Reference: ${reference}` : ""}${retryable ? " You can retry." : ""}`, retryable, safeCode);
  }
  return response.status === 204 ? null : response.json();
}

export default function AccessShell({ registrationRoute = false }: { registrationRoute?: boolean }) {
  const router = useRouter();
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
      setMessage(error instanceof Error ? error.message : "Request failed.");
    } finally {
      setBusy(false);
    }
  }

  if (user) {
    return <CareerWorkspace email={user.email} role={user.role} onSignOut={async () => { await api("/api/v1/auth/logout", { method: "POST" }); setUser(null); router.replace("/"); }} />;
  }

  const registrationUnavailable = mode === "register" && (!registrationRoute || !token);

  return <main className="shell"><section className="status-card" aria-labelledby="cvortex-title"><div className="brand-mark" aria-hidden="true" /><p className="eyebrow">Access core</p><h1 id="cvortex-title">CVortex</h1><p className="tagline">Your career, in context.</p><div className="tabs"><button type="button" className={mode === "login" ? "selected" : ""} onClick={() => setMode("login")}>Sign in</button><button type="button" className={mode === "register" ? "selected" : ""} onClick={() => setMode("register")}>Register</button></div><form onSubmit={(event) => { event.preventDefault(); void submit(event.currentTarget); }}><label>Email<input required type="email" name="email" autoComplete="email" /></label><label>Password<input required type="password" name="password" minLength={15} maxLength={128} autoComplete={mode === "login" ? "current-password" : "new-password"} /></label>{mode === "register" && <><label>Confirm password<input required type="password" name="password_confirmation" minLength={15} maxLength={128} autoComplete="new-password" /></label>{registrationUnavailable && <p className="error">Open the registration link provided by your operator.</p>}</>} {message && <p className="error" role="alert">{message}</p>}<button disabled={busy || registrationUnavailable}>{busy ? "Working…" : mode === "login" ? "Sign in" : "Create account"}</button></form></section></main>;
}

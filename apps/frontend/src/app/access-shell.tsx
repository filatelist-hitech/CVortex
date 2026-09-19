"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import CareerWorkspace from "./career-workspace";

type User = { id: string; email: string; role: string; status: string };
type Mode = "login" | "register";

const csrf = () => decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");

const safeErrorMessages: Record<string, string> = {
  PROVIDER_ERROR: "Career extraction is temporarily unavailable. Manual fact entry is still available.",
  INVALID_EXTRACTION_RESULT: "Career extraction returned unsupported data. Nothing was trusted.",
  CAREER_OPERATION_FAILED: "The Career operation could not be completed. Please try again.",
};

export async function api(path: string, options: RequestInit = {}) {
  const response = await fetch(path, {
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      ...(options.method && options.method !== "GET" ? { "X-XSRF-TOKEN": csrf() } : {}),
    },
    ...options,
  });
  if (!response.ok) {
    const body = await response.json().catch(() => null);
    const code = typeof body?.error?.code === "string" ? body.error.code : "";
    throw new Error(safeErrorMessages[code] ?? "Request failed. Please try again.");
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
    return <CareerWorkspace email={user.email} onSignOut={async () => { await api("/api/v1/auth/logout", { method: "POST" }); setUser(null); router.replace("/"); }} />;
  }

  const registrationUnavailable = mode === "register" && (!registrationRoute || !token);

  return <main className="shell"><section className="status-card" aria-labelledby="cvortex-title"><div className="brand-mark" aria-hidden="true" /><p className="eyebrow">Access core</p><h1 id="cvortex-title">CVortex</h1><p className="tagline">Your career, in context.</p><div className="tabs"><button type="button" className={mode === "login" ? "selected" : ""} onClick={() => setMode("login")}>Sign in</button><button type="button" className={mode === "register" ? "selected" : ""} onClick={() => setMode("register")}>Register</button></div><form onSubmit={(event) => { event.preventDefault(); void submit(event.currentTarget); }}><label>Email<input required type="email" name="email" autoComplete="email" /></label><label>Password<input required type="password" name="password" minLength={15} maxLength={128} autoComplete={mode === "login" ? "current-password" : "new-password"} /></label>{mode === "register" && <><label>Confirm password<input required type="password" name="password_confirmation" minLength={15} maxLength={128} autoComplete="new-password" /></label>{registrationUnavailable && <p className="error">Open the registration link provided by your operator.</p>}</>} {message && <p className="error" role="alert">{message}</p>}<button disabled={busy || registrationUnavailable}>{busy ? "Working…" : mode === "login" ? "Sign in" : "Create account"}</button></form></section></main>;
}

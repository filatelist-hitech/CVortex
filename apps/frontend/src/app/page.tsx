"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";

type User = { id: string; email: string; role: string; status: string };
type Mode = "login" | "register";

const csrf = () => decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");
async function api(path: string, options: RequestInit = {}) {
  const response = await fetch(path, { credentials: "same-origin", headers: { Accept: "application/json", "Content-Type": "application/json", ...(options.method && options.method !== "GET" ? { "X-XSRF-TOKEN": csrf() } : {}) }, ...options });
  if (!response.ok) { const body = await response.json().catch(() => null); throw new Error(body?.message ?? "Request failed. Please try again."); }
  return response.status === 204 ? null : response.json();
}

export default function Home() {
  const router = useRouter();
  const invitation = typeof window === "undefined" ? "" : new URLSearchParams(window.location.hash.slice(1)).get("token") ?? "";
  const [mode, setMode] = useState<Mode>(invitation ? "register" : "login"); const [token] = useState(invitation); const [user, setUser] = useState<User | null>(null); const [message, setMessage] = useState(""); const [busy, setBusy] = useState(false);
  useEffect(() => { if (invitation) window.history.replaceState(null, "", window.location.pathname); api("/api/v1/me").then((result) => setUser(result.data)).catch(() => undefined); }, [invitation]);
  async function submit(form: HTMLFormElement) { setBusy(true); setMessage(""); const values = Object.fromEntries(new FormData(form)); try { await fetch("/sanctum/csrf-cookie", { credentials: "same-origin", headers: { Accept: "application/json" } }); const result = await api(mode === "login" ? "/api/v1/auth/login" : "/api/v1/auth/register", { method: "POST", body: JSON.stringify(mode === "login" ? values : { ...values, invitation_token: token }) }); setUser(result.data); form.reset(); } catch (error) { setMessage(error instanceof Error ? error.message : "Request failed."); } finally { setBusy(false); } }
  if (user) return <main className="shell"><section className="status-card" aria-labelledby="app-title"><p className="eyebrow">Authenticated shell</p><h1 id="app-title">CVortex</h1><p className="tagline">{user.email}</p><dl className="identity"><dt>Role</dt><dd>{user.role}</dd><dt>Status</dt><dd>{user.status}</dd></dl><button type="button" onClick={async () => { await api("/api/v1/auth/logout", { method: "POST" }); setUser(null); router.replace("/"); }}>Sign out</button></section></main>;
  return <main className="shell"><section className="status-card" aria-labelledby="cvortex-title"><div className="brand-mark" aria-hidden="true" /><p className="eyebrow">Access core</p><h1 id="cvortex-title">CVortex</h1><p className="tagline">Your career, in context.</p><div className="tabs"><button type="button" className={mode === "login" ? "selected" : ""} onClick={() => setMode("login")}>Sign in</button><button type="button" className={mode === "register" ? "selected" : ""} onClick={() => setMode("register")}>Register</button></div><form onSubmit={(event) => { event.preventDefault(); void submit(event.currentTarget); }}><label>Email<input required type="email" name="email" autoComplete="email" /></label><label>Password<input required type="password" name="password" minLength={15} maxLength={128} autoComplete={mode === "login" ? "current-password" : "new-password"} /></label>{mode === "register" && <><label>Confirm password<input required type="password" name="password_confirmation" minLength={15} maxLength={128} autoComplete="new-password" /></label>{!token && <p className="error">Open the registration link provided by your operator.</p>}</>} {message && <p className="error" role="alert">{message}</p>}<button disabled={busy || (mode === "register" && !token)}>{busy ? "Working…" : mode === "login" ? "Sign in" : "Create account"}</button></form></section></main>;
}

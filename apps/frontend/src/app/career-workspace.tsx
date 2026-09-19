"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { api } from "./access-shell";
import VacancyWorkspace from "./vacancy-workspace";

type Fact = {
  id: string;
  fact_type: string;
  assertion_original: string;
  assertion_approved: string | null;
  source_excerpt: string;
  provenance_type: "paste_extraction" | "user_manual";
  status: "PENDING" | "CONFIRMED" | "REJECTED" | "DEPRECATED";
};
type Claim = { id: string; statement: string; truth_status: "PASS" | "BLOCK" | "USER_RESOLUTION_REQUIRED" };
type Source = { id: string; extraction_status: "PENDING" | "RUNNING" | "COMPLETED" | "FAILED"; error_code: string | null };
type Overview = { facts: Fact[]; claims: Claim[]; sources: Source[] };

export default function CareerWorkspace({ email, onSignOut }: { email: string; onSignOut: () => Promise<void> }) {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState("");
  const [edits, setEdits] = useState<Record<string, string>>({});

  const refresh = useCallback(async () => {
    try {
      const result = await api("/api/v1/career");
      setOverview(result.data);
      setError("");
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Career data could not be loaded.");
    }
  }, []);

  useEffect(() => {
    // Initial owner-scoped API hydration is the external synchronization performed by this effect.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void refresh();
  }, [refresh]);

  useEffect(() => {
    if (!overview?.sources.some((source) => source.extraction_status === "PENDING" || source.extraction_status === "RUNNING")) return;
    const timer = window.setInterval(() => void refresh(), 1500);
    return () => window.clearInterval(timer);
  }, [overview?.sources, refresh]);

  async function submit(event: FormEvent<HTMLFormElement>, path: string, operation: string) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(operation);
    setError("");
    try {
      await api(path, { method: "POST", body: JSON.stringify(Object.fromEntries(new FormData(form))) });
      form.reset();
      await refresh();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "The request failed.");
    } finally {
      setBusy("");
    }
  }

  async function review(fact: Fact, action: "confirm" | "edit_confirm" | "reject") {
    setBusy(fact.id + action);
    setError("");
    try {
      await api(`/api/v1/career/facts/${fact.id}/review`, {
        method: "PATCH",
        body: JSON.stringify({ action, ...(action === "edit_confirm" ? { assertion: edits[fact.id] ?? fact.assertion_original } : {}) }),
      });
      await refresh();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "The review action failed.");
    } finally {
      setBusy("");
    }
  }

  const pending = overview?.facts.filter((fact) => fact.status === "PENDING") ?? [];
  const confirmed = overview?.facts.filter((fact) => fact.status === "CONFIRMED") ?? [];

  return <main className="career-shell">
    <header className="career-header"><div><p className="eyebrow">Career core</p><h1>Your confirmed career</h1><p className="tagline">Evidence first. Psychedelic confidence later.</p></div><div className="account"><span>{email}</span><button type="button" className="secondary" onClick={() => void onSignOut()}>Sign out</button></div></header>
    {error && <div className="alert error" role="alert"><strong>Action failed</strong><span>{error}</span><button type="button" className="secondary" onClick={() => void refresh()}>Retry</button></div>}
    <section className="career-grid" aria-busy={!overview}>
      <div className="panel"><h2>Extract from pasted text</h2><p className="muted">Pasted text is untrusted data. Extraction can only create pending candidates.</p><form onSubmit={(event) => void submit(event, "/api/v1/career/extractions", "extract")}><label>Career or resume text<textarea name="source_text" required maxLength={30000} rows={9} /></label><button disabled={busy !== ""}>{busy === "extract" ? "Extracting…" : "Extract pending facts"}</button></form>{overview?.sources[0] && <p className={`source-status ${overview.sources[0].extraction_status.toLowerCase()}`} role="status">Latest extraction: {overview.sources[0].extraction_status}{overview.sources[0].error_code ? ` — ${overview.sources[0].error_code}` : ""}</p>}</div>
      <div className="panel"><h2>Add a fact manually</h2><p className="muted">Manual entries are explicitly human-confirmed and do not require an LLM.</p><form onSubmit={(event) => void submit(event, "/api/v1/career/facts/manual", "manual")}><label>Fact type<input name="fact_type" required pattern="[a-z][a-z0-9_]{1,63}" placeholder="skill" /></label><label>Confirmed assertion<textarea name="assertion" required maxLength={1000} rows={4} /></label><button disabled={busy !== ""}>{busy === "manual" ? "Saving…" : "Add confirmed fact"}</button></form></div>
    </section>
    {!overview && <p className="loading" role="status">Loading private career data…</p>}
    {overview && <>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Human review required</p><h2>Pending facts</h2></div><span className="count">{pending.length}</span></div>{pending.length === 0 ? <p className="empty">No pending candidates. Paste text above or add a manual fact.</p> : <div className="fact-list">{pending.map((fact) => <article className="fact-card pending-card" key={fact.id}><div className="fact-meta"><span className="badge pending-badge">Pending confirmation</span><span>{fact.fact_type}</span></div><p className="assertion">{fact.assertion_original}</p><details open><summary>Evidence available</summary><blockquote>{fact.source_excerpt}</blockquote><p className="muted">Provenance: {fact.provenance_type}</p></details><label>Edit before confirming<textarea rows={3} value={edits[fact.id] ?? fact.assertion_original} onChange={(event) => setEdits((current) => ({ ...current, [fact.id]: event.target.value }))} /></label><div className="actions"><button disabled={busy !== ""} onClick={() => void review(fact, "confirm")}>Confirm</button><button className="secondary" disabled={busy !== ""} onClick={() => void review(fact, "edit_confirm")}>Edit and Confirm</button><button className="danger" disabled={busy !== ""} onClick={() => void review(fact, "reject")}>Reject</button></div></article>)}</div>}</section>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Queryable truth</p><h2>Confirmed facts</h2></div><span className="count confirmed-count">{confirmed.length}</span></div>{confirmed.length === 0 ? <p className="empty">No confirmed facts yet.</p> : <div className="fact-list">{confirmed.map((fact) => <article className="fact-card confirmed-card" key={fact.id}><div className="fact-meta"><span className="badge confirmed-badge">Confirmed</span><span>{fact.fact_type}</span></div><p className="assertion">{fact.assertion_approved ?? fact.assertion_original}</p><details><summary>Evidence and provenance</summary><blockquote>{fact.source_excerpt}</blockquote><p className="muted">{fact.provenance_type}</p></details></article>)}</div>}</section>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Truth Guard</p><h2>Claims</h2></div></div>{overview.claims.length === 0 ? <p className="empty">Claims appear only after explicit confirmation.</p> : <ul className="claim-list">{overview.claims.map((claim) => <li key={claim.id}><span className={`badge ${claim.truth_status === "PASS" ? "confirmed-badge" : "blocked-badge"}`}>{claim.truth_status}</span><span>{claim.statement}</span></li>)}</ul>}</section>
      <VacancyWorkspace />
    </>}
  </main>;
}

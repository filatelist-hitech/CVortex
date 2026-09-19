"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { api } from "./access-shell";

type VacancySummary = {
  id: string;
  title: string | null;
  company: string | null;
  source_url: string | null;
  analysis_status: "PENDING" | "RUNNING" | "COMPLETED" | "FAILED";
  error_code: string | null;
  snapshot_version: number;
  recommendation: Recommendation | null;
  analysis_stale: boolean;
};
type Recommendation = "STRONGLY_APPLY" | "APPLY" | "MAYBE" | "LOW_PRIORITY" | "SKIP";
type Requirement = {
  id: string;
  dimension: string;
  importance: "MANDATORY" | "PREFERRED" | "UNCERTAIN";
  label: string;
  source_excerpt: string;
  confidence: number;
};
type Evidence = { type: "CareerFact" | "Claim"; id: string; statement: string; status: string; provenance_type?: string };
type Dimension = {
  dimension: string;
  result: "MATCH" | "ADJACENT" | "GAP" | "UNKNOWN" | "NOT_APPLICABLE" | "BLOCKER";
  explanation: string;
  origin: "DETERMINISTIC";
  vacancy_requirement_ids: string[];
  candidate_evidence: Evidence[];
};
type Analysis = {
  recommendation: Recommendation;
  key_reasons: string[];
  material_gaps: Array<{ requirement_id: string; label: string; category: string }>;
  uncertainties: Array<{ requirement_id: string; label: string; reason: string }>;
  stale: boolean;
  dimensions: Dimension[];
};
type VacancyDetail = VacancySummary & {
  source_type: "PASTED_TEXT";
  snapshot: { id: string; version: number; raw_text: string; source_url: string | null; imported_at: string };
  requirements: Requirement[];
  analysis: Analysis | null;
};

const dimensionNames: Record<string, string> = {
  TECHNICAL: "Technical",
  EXPERIENCE: "Experience",
  DOMAIN: "Domain",
  LANGUAGE: "Language",
  LOCATION: "Location",
  WORK_FORMAT: "Work format",
  SALARY: "Salary",
};

export default function VacancyWorkspace() {
  const [vacancies, setVacancies] = useState<VacancySummary[]>([]);
  const [selectedId, setSelectedId] = useState("");
  const [detail, setDetail] = useState<VacancyDetail | null>(null);
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  const loadDetail = useCallback(async (id: string) => {
    const result = await api(`/api/v1/vacancies/${id}`);
    setDetail(result.data);
  }, []);

  const refresh = useCallback(async () => {
    try {
      const result = await api("/api/v1/vacancies");
      const items = result.data as VacancySummary[];
      setVacancies(items);
      setError("");
      const id = selectedId || items[0]?.id || "";
      if (id) {
        setSelectedId(id);
        await loadDetail(id);
      } else {
        setDetail(null);
      }
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Vacancies could not be loaded.");
    }
  }, [loadDetail, selectedId]);

  useEffect(() => {
    // Owner-scoped API hydration is the external synchronization performed by this effect.
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void refresh();
  }, [refresh]);

  useEffect(() => {
    if (!vacancies.some((item) => item.analysis_status === "PENDING" || item.analysis_status === "RUNNING")) return;
    const timer = window.setInterval(() => void refresh(), 1500);
    return () => window.clearInterval(timer);
  }, [vacancies, refresh]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(true);
    setError("");
    try {
      const values = Object.fromEntries(new FormData(form));
      const result = await api("/api/v1/vacancies", {
        method: "POST",
        body: JSON.stringify({ source_text: values.source_text, source_url: values.source_url || null }),
      });
      form.reset();
      setSelectedId(result.data.id);
      await refresh();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "The vacancy could not be added.");
    } finally {
      setBusy(false);
    }
  }

  async function select(id: string) {
    setSelectedId(id);
    setError("");
    try {
      await loadDetail(id);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "The vacancy could not be loaded.");
    }
  }

  async function reanalyze() {
    if (!detail) return;
    setBusy(true);
    try {
      await api(`/api/v1/vacancies/${detail.id}/reanalyze`, { method: "POST" });
      await refresh();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Reanalysis could not be started.");
    } finally {
      setBusy(false);
    }
  }

  return <section className="vacancy-workspace" aria-labelledby="vacancy-title">
    <div className="section-title"><div><p className="eyebrow">Vacancy core</p><h2 id="vacancy-title">Should I apply?</h2></div><span className="count">{vacancies.length}</span></div>
    <p className="muted">Paste-only ingestion. The optional URL is metadata and is never fetched in this slice.</p>
    {error && <div className="alert error" role="alert"><strong>Vacancy flow failed</strong><span>{error}</span><button type="button" className="secondary" onClick={() => void refresh()}>Retry</button></div>}
    <div className="vacancy-grid">
      <div className="panel"><h3>Add vacancy</h3><form onSubmit={(event) => void submit(event)}><label>Vacancy text<textarea name="source_text" required maxLength={50000} rows={10} /></label><label>Source URL (metadata only)<input name="source_url" type="url" maxLength={2048} placeholder="https://…" /></label><button disabled={busy}>{busy ? "Working…" : "Preserve and analyze"}</button></form></div>
      <div className="panel"><h3>Saved vacancies</h3>{vacancies.length === 0 ? <p className="empty">No vacancy snapshots yet.</p> : <ul className="vacancy-list">{vacancies.map((item) => <li key={item.id}><button type="button" className={selectedId === item.id ? "vacancy-selected" : "secondary"} onClick={() => void select(item.id)}><span>{item.title || "Untitled vacancy"}</span><small>{item.analysis_status}{item.recommendation ? ` · ${item.recommendation}` : ""}{item.analysis_stale ? " · stale" : ""}</small></button></li>)}</ul>}</div>
    </div>
    {detail && <div className="vacancy-detail">
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Source snapshot v{detail.snapshot.version}</p><h3>{detail.title || "Untitled vacancy"}</h3></div><span className={`badge ${detail.analysis_status.toLowerCase()}`}>{detail.analysis_status}</span></div>{detail.company && <p>{detail.company}</p>}{detail.source_url && <p className="muted">Source URL metadata: {detail.source_url}</p>}<details><summary>Original untrusted source text</summary><pre className="source-copy">{detail.snapshot.raw_text}</pre></details>{detail.error_code && <p className="error">Safe error code: {detail.error_code}</p>}</section>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">CVortex inference</p><h3>Requirements</h3></div><span className="count">{detail.requirements.length}</span></div>{detail.requirements.length === 0 ? <p className="empty">No supported requirements were extracted.</p> : <div className="requirement-list">{detail.requirements.map((requirement) => <article className="requirement-card" key={requirement.id}><div className="fact-meta"><span className={`badge ${requirement.importance.toLowerCase()}`}>{requirement.importance}</span><span>{dimensionNames[requirement.dimension]}</span></div><p className="assertion">{requirement.label}</p><details><summary>Source wording</summary><blockquote>{requirement.source_excerpt}</blockquote><p className="muted">Inference confidence: {Math.round(requirement.confidence * 100)}%</p></details></article>)}</div>}</section>
      {detail.analysis && <>
        <section className={`recommendation ${detail.analysis.recommendation.toLowerCase()}`}><p className="eyebrow">Explainable recommendation</p><h3>{detail.analysis.recommendation.replaceAll("_", " ")}</h3><p>This is a prioritization class, not an interview probability or ATS score.</p>{detail.analysis.stale && <div className="stale-callout"><strong>Analysis is stale.</strong><span>Confirmed Career evidence changed.</span><button type="button" disabled={busy} onClick={() => void reanalyze()}>Reanalyze current facts</button></div>}<ul>{detail.analysis.key_reasons.map((reason) => <li key={reason}>{reason}</li>)}</ul></section>
        <section className="review-section"><p className="eyebrow">Seven dimensions</p><div className="dimension-grid">{detail.analysis.dimensions.map((dimension) => <article className={`dimension-card ${dimension.result.toLowerCase()}`} key={dimension.dimension}><div className="fact-meta"><h3>{dimensionNames[dimension.dimension]}</h3><span className="badge">{dimension.result.replaceAll("_", " ")}</span></div><p>{dimension.explanation}</p><p className="muted">Origin: deterministic policy</p>{dimension.candidate_evidence.length > 0 && <details><summary>Confirmed candidate evidence</summary><ul>{dimension.candidate_evidence.map((evidence) => <li key={`${evidence.type}-${evidence.id}`}><strong>{evidence.type} · {evidence.status}</strong><span>{evidence.statement}</span>{evidence.provenance_type && <small>Provenance: {evidence.provenance_type}</small>}</li>)}</ul></details>}</article>)}</div></section>
        <section className="vacancy-grid"><div className="panel"><h3>Material gaps</h3>{detail.analysis.material_gaps.length === 0 ? <p className="empty">No material gap detected.</p> : <ul>{detail.analysis.material_gaps.map((gap) => <li key={`${gap.requirement_id}-${gap.category}`}><strong>{gap.label}</strong> — {gap.category}</li>)}</ul>}</div><div className="panel"><h3>Unknowns</h3>{detail.analysis.uncertainties.length === 0 ? <p className="empty">No unresolved uncertainty recorded.</p> : <ul>{detail.analysis.uncertainties.map((item) => <li key={`${item.requirement_id}-${item.reason}`}><strong>{item.label}</strong> — {item.reason}</li>)}</ul>}</div></section>
      </>}
    </div>}
  </section>;
}

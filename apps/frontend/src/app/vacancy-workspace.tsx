"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
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
  analysis_run_stale: boolean;
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

type ClaimUsage = {
  assertion: string;
  claim_ids: string[];
  supporting_claims: Array<{ id: string; statement: string; truth_status: string; career_facts: Array<{ id: string; statement: string; status: string; provenance_type: string; source_excerpt: string }> }>;
};
type DraftItem = {
  id: string;
  kind: "RESUME_RECOMMENDATION" | "COVER_DRAFT";
  variant: "SHORT" | "STANDARD" | null;
  section: string | null;
  before: string | null;
  content: string;
  reason: string | null;
  risk: string | null;
  status: "DRAFT" | "ACCEPTED" | "REJECTED" | "BLOCKED" | "APPROVED";
  validation_result: "NOT_VALIDATED" | "PASS" | "BLOCK" | "USER_RESOLUTION_REQUIRED" | "FAILED";
  claim_usages: ClaimUsage[];
  approvals: Array<{ action: string; content_hash: string; validation_result: string; created_at: string }>;
};
type Preparation = { id: string; vacancy_id: string; status: "DRAFT" | "APPROVED"; stale: boolean; items: DraftItem[] };

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
  const selectedIdRef = useRef("");
  const importedSelectionRef = useRef("");
  const selectionVersion = useRef(0);
  const refreshVersion = useRef(0);
  const detailVersion = useRef(0);

  const selectId = useCallback((id: string) => {
    if (importedSelectionRef.current && importedSelectionRef.current !== id) importedSelectionRef.current = "";
    selectedIdRef.current = id;
    selectionVersion.current += 1;
    setSelectedId(id);
  }, []);

  const loadDetail = useCallback(async (id: string) => {
    const requestVersion = ++detailVersion.current;
    try {
      const result = await api(`/api/v1/vacancies/${id}`);
      if (requestVersion === detailVersion.current && id === selectedIdRef.current) setDetail(result.data);
    } catch (caught) {
      if (requestVersion === detailVersion.current && id === selectedIdRef.current) throw caught;
    }
  }, []);

  const refresh = useCallback(async (preferredId?: string) => {
    const requestVersion = ++refreshVersion.current;
    const startingSelectionVersion = selectionVersion.current;
    try {
      const result = await api("/api/v1/vacancies");
      const items = result.data as VacancySummary[];
      if (requestVersion !== refreshVersion.current) return;
      // A response started before the latest explicit selection may update
      // neither the list nor the selection: its snapshot is stale user intent.
      if (selectionVersion.current !== startingSelectionVersion) return;
      setVacancies(items);
      const currentId = selectedIdRef.current;
      if (selectionVersion.current === startingSelectionVersion) setError("");
      const preferenceIsCurrent = selectionVersion.current === startingSelectionVersion;
      const preferredIsPresent = preferredId && items.some((item) => item.id === preferredId);
      const id = currentId && items.some((item) => item.id === currentId)
        ? currentId
        : (preferenceIsCurrent && preferredIsPresent ? preferredId : items[0]?.id) || "";
      if (id) {
        if (id === importedSelectionRef.current) importedSelectionRef.current = "";
        if (id !== currentId) selectId(id);
        await loadDetail(id);
      } else {
        importedSelectionRef.current = "";
        selectId("");
        setDetail(null);
      }
    } catch (caught) {
      if (requestVersion === refreshVersion.current && selectionVersion.current === startingSelectionVersion) {
        setError(caught instanceof Error ? caught.message : "Vacancies could not be loaded.");
      }
    }
  }, [loadDetail, selectId]);

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
    const startingSelectionVersion = selectionVersion.current;
    setBusy(true);
    setError("");
    try {
      const values = Object.fromEntries(new FormData(form));
      const result = await api("/api/v1/vacancies", {
        method: "POST",
        body: JSON.stringify({ source_text: values.source_text, source_url: values.source_url || null }),
      });
      form.reset();
      if (selectionVersion.current === startingSelectionVersion) {
        importedSelectionRef.current = result.data.id;
        selectId(result.data.id);
      }
      await refresh();
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "The vacancy could not be added.");
    } finally {
      setBusy(false);
    }
  }

  async function select(id: string) {
    selectId(id);
    const currentSelectionVersion = selectionVersion.current;
    setDetail(null);
    setError("");
    try {
      await loadDetail(id);
    } catch (caught) {
      if (currentSelectionVersion === selectionVersion.current && selectedIdRef.current === id) {
        setError(caught instanceof Error ? caught.message : "The vacancy could not be loaded.");
      }
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
      <div className="panel"><h3>Saved vacancies</h3>{vacancies.length === 0 ? <p className="empty">No vacancy snapshots yet.</p> : <ul className="vacancy-list">{vacancies.map((item) => <li key={item.id}><button type="button" className={selectedId === item.id ? "vacancy-selected" : "secondary"} onClick={() => void select(item.id)}><span>{item.title || "Untitled vacancy"}</span><small>{item.analysis_status}{item.recommendation ? ` · ${item.recommendation}` : ""}{item.analysis_stale ? " · stale" : ""}{item.analysis_run_stale ? " · recovery available" : ""}</small></button></li>)}</ul>}</div>
    </div>
    {detail && <div className="vacancy-detail">
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Source snapshot v{detail.snapshot.version}</p><h3>{detail.title || "Untitled vacancy"}</h3></div><span className={`badge ${detail.analysis_status.toLowerCase()}`}>{detail.analysis_status}</span></div>{detail.company && <p>{detail.company}</p>}{detail.source_url && <p className="muted">Source URL metadata: {detail.source_url}</p>}<details><summary>Original untrusted source text</summary><pre className="source-copy">{detail.snapshot.raw_text}</pre></details>{detail.error_code && <p className="error">Safe error code: {detail.error_code}</p>}</section>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">CVortex inference</p><h3>Requirements</h3></div><span className="count">{detail.requirements.length}</span></div>{detail.requirements.length === 0 ? <p className="empty">No supported requirements were extracted.</p> : <div className="requirement-list">{detail.requirements.map((requirement) => <article className="requirement-card" key={requirement.id}><div className="fact-meta"><span className={`badge ${requirement.importance.toLowerCase()}`}>{requirement.importance}</span><span>{dimensionNames[requirement.dimension]}</span></div><p className="assertion">{requirement.label}</p><details><summary>Source wording</summary><blockquote>{requirement.source_excerpt}</blockquote><p className="muted">Inference confidence: {Math.round(requirement.confidence * 100)}%</p></details></article>)}</div>}</section>
      {detail.analysis_status === "FAILED" && <section className="review-section"><p className="eyebrow">Analysis failed</p><h3>Retry extraction</h3><p className="muted">The source snapshot is preserved. Retry analysis when the provider is available.</p><button type="button" disabled={busy} onClick={() => void reanalyze()}>{busy ? "Retrying…" : "Retry analysis"}</button></section>}
      {detail.analysis_status === "RUNNING" && detail.analysis_run_stale && <section className="review-section"><p className="eyebrow">Analysis stalled</p><h3>Recover vacancy analysis</h3><p className="muted">The current run stopped making progress. Retry to reclaim this snapshot.</p><button type="button" disabled={busy} onClick={() => void reanalyze()}>{busy ? "Recovering…" : "Recover stale analysis"}</button></section>}
      {detail.analysis && <>
        <section className={`recommendation ${detail.analysis.recommendation.toLowerCase()}`}><p className="eyebrow">Explainable recommendation</p><h3>{detail.analysis.recommendation.replaceAll("_", " ")}</h3><p>This is a prioritization class, not an interview probability or ATS score.</p>{detail.analysis.stale && <div className="stale-callout"><strong>Analysis is stale.</strong><span>Confirmed Career evidence changed.</span><button type="button" disabled={busy} onClick={() => void reanalyze()}>Reanalyze current facts</button></div>}<ul>{detail.analysis.key_reasons.map((reason) => <li key={reason}>{reason}</li>)}</ul></section>
        <section className="review-section"><p className="eyebrow">Seven dimensions</p><div className="dimension-grid">{detail.analysis.dimensions.map((dimension) => <article className={`dimension-card ${dimension.result.toLowerCase()}`} key={dimension.dimension}><div className="fact-meta"><h3>{dimensionNames[dimension.dimension]}</h3><span className="badge">{dimension.result.replaceAll("_", " ")}</span></div><p>{dimension.explanation}</p><p className="muted">Origin: deterministic policy</p>{dimension.candidate_evidence.length > 0 && <details><summary>Confirmed candidate evidence</summary><ul>{dimension.candidate_evidence.map((evidence) => <li key={`${evidence.type}-${evidence.id}`}><strong>{evidence.type} · {evidence.status}</strong><span>{evidence.statement}</span>{evidence.provenance_type && <small>Provenance: {evidence.provenance_type}</small>}</li>)}</ul></details>}</article>)}</div></section>
        <section className="vacancy-grid"><div className="panel"><h3>Material gaps</h3>{detail.analysis.material_gaps.length === 0 ? <p className="empty">No material gap detected.</p> : <ul>{detail.analysis.material_gaps.map((gap) => <li key={`${gap.requirement_id}-${gap.category}`}><strong>{gap.label}</strong> — {gap.category}</li>)}</ul>}</div><div className="panel"><h3>Unknowns</h3>{detail.analysis.uncertainties.length === 0 ? <p className="empty">No unresolved uncertainty recorded.</p> : <ul>{detail.analysis.uncertainties.map((item) => <li key={`${item.requirement_id}-${item.reason}`}><strong>{item.label}</strong> — {item.reason}</li>)}</ul>}</div></section>
      </>}
      {detail.analysis && (detail.analysis.stale || detail.analysis_status !== "COMPLETED"
        ? <section className="review-section"><p className="eyebrow">Preview 0.1 · saved preparation</p><h3>Prepare for {detail.title || "Untitled vacancy"}</h3><p className="empty">Reanalyze this vacancy against current confirmed Career Facts before preparing drafts.</p></section>
        : <ApplicationDraftPanel key={detail.id} vacancyId={detail.id} vacancyTitle={detail.title || "Untitled vacancy"} />)}
    </div>}
  </section>;
}

function ApplicationDraftPanel({ vacancyId, vacancyTitle }: { vacancyId: string; vacancyTitle: string }) {
  const [preparation, setPreparation] = useState<Preparation | null>(null);
  const [edits, setEdits] = useState<Record<string, string>>({});
  const [loading, setLoading] = useState(true);
  const [busy, setBusy] = useState("");
  const [error, setError] = useState("");
  const requestVersion = useRef(0);

  const refresh = useCallback(async (id: string, version = requestVersion.current) => {
    const result = await api(`/api/v1/applications/preparations/${id}`);
    if (version !== requestVersion.current) return;
    const data = result.data as Preparation;
    setPreparation(data);
    setEdits(Object.fromEntries(data.items.map((item) => [item.id, item.content])));
  }, []);

  useEffect(() => {
    let active = true;
    const version = ++requestVersion.current;
    api(`/api/v1/vacancies/${vacancyId}/preparation`, { method: "POST" })
      .then(async (result) => {
        if (!active || version !== requestVersion.current) return;
        const data = result.data as Preparation;
        setPreparation(data);
        setEdits(Object.fromEntries(data.items.map((item) => [item.id, item.content])));
        await refresh(data.id, version);
      })
      .catch((caught) => {
        if (active && version === requestVersion.current) setError(caught instanceof Error ? caught.message : "Preparation could not be loaded.");
      })
      .finally(() => { if (active && version === requestVersion.current) setLoading(false); });
    return () => { active = false; requestVersion.current += 1; };
  }, [refresh, vacancyId]);

  async function generate() {
    if (!preparation || preparation.stale) return;
    const version = requestVersion.current;
    setBusy("generate");
    setError("");
    try {
      const result = await api(`/api/v1/applications/preparations/${preparation.id}/generate`, { method: "POST" });
      if (version === requestVersion.current) {
        setPreparation(result.data as Preparation);
        setEdits(Object.fromEntries((result.data as Preparation).items.map((item) => [item.id, item.content])));
      }
    } catch (caught) {
      if (version === requestVersion.current) setError(caught instanceof Error ? caught.message : "Draft generation failed.");
    } finally { if (version === requestVersion.current) setBusy(""); }
  }

  async function act(item: DraftItem, action: "accept" | "edit" | "reject" | "approve") {
    if (!preparation || preparation.stale || busy) return;
    const version = requestVersion.current;
    setBusy(item.id);
    setError("");
    try {
      const result = action === "approve"
        ? await api(`/api/v1/applications/draft-items/${item.id}/approve`, { method: "POST" })
        : await api(`/api/v1/applications/draft-items/${item.id}`, {
            method: "PATCH",
            body: JSON.stringify({ action, ...(action === "edit" ? { content: edits[item.id] ?? item.content } : {}) }),
          });
      if (version === requestVersion.current) {
        setPreparation(result.data as Preparation);
        setEdits(Object.fromEntries((result.data as Preparation).items.map((draft) => [draft.id, draft.content])));
      }
    } catch (caught) {
      if (version === requestVersion.current) setError(caught instanceof Error ? caught.message : "Draft review action failed.");
      if (version === requestVersion.current) await refresh(preparation.id, version).catch(() => undefined);
    } finally { if (version === requestVersion.current) setBusy(""); }
  }

  return <section className="review-section application-draft" aria-labelledby="application-draft-title">
    <p className="eyebrow">Preview 0.1 · saved preparation</p>
    <h3 id="application-draft-title">Prepare for {vacancyTitle}</h3>
    <p className="muted">Recommendations and cover drafts stay private. Approval does not submit an application or contact an employer.</p>
    {loading && <p className="loading" role="status">Loading saved preparation…</p>}
    {error && <div className="alert error" role="alert"><span>{error}</span></div>}
    {preparation?.stale && <p className="stale-callout" role="status"><strong>Preparation is stale.</strong><span>Vacancy or confirmed Career evidence changed. Reanalyze and reopen it before continuing.</span></p>}
    {preparation && !loading && <>
      {preparation.items.length === 0 && <div className="panel"><p>No saved recommendations or cover drafts yet.</p><button type="button" disabled={Boolean(busy) || preparation.stale} onClick={() => void generate()}>{busy === "generate" ? "Generating drafts…" : "Generate recommendations and cover drafts"}</button></div>}
      {preparation.items.map((item) => <article className="application-draft-item" key={item.id}>
        <div className="section-title"><div><p className="eyebrow">{item.kind === "COVER_DRAFT" ? `${item.variant?.toLowerCase()} cover draft` : `Resume recommendation · ${item.section}`}</p><h4>{item.kind === "COVER_DRAFT" ? "Candidate-facing draft" : item.reason}</h4></div><span className={`badge ${item.validation_result.toLowerCase()}`}>{item.status} · Truth Guard {item.validation_result}</span></div>
        {item.before && <p><strong>Before</strong><br />{item.before}</p>}
        {item.kind === "RESUME_RECOMMENDATION" && <p><strong>Reason</strong><br />{item.reason}</p>}
        {item.kind === "RESUME_RECOMMENDATION" && <p><strong>Risk</strong><br />{item.risk}</p>}
        <label>{item.kind === "COVER_DRAFT" ? "Draft content" : "After · recommendation, not confirmed career truth"}<textarea value={edits[item.id] ?? item.content} maxLength={6000} rows={item.kind === "COVER_DRAFT" ? 8 : 4} disabled={preparation.stale || item.status === "REJECTED" || item.status === "APPROVED"} onChange={(event) => setEdits((current) => ({ ...current, [item.id]: event.target.value }))} /></label>
        {item.claim_usages.length > 0 && <details><summary>Claim and Career Fact provenance</summary><ul className="claim-list">{item.claim_usages.map((usage, index) => <li key={`${item.id}-${index}`}><span>Candidate assertion: {usage.assertion}</span>{usage.supporting_claims.map((claim) => <div key={claim.id}><strong>Claim · {claim.truth_status}</strong><p>{claim.statement}</p>{claim.career_facts.map((fact) => <div key={fact.id}><strong>Career Fact · {fact.status}</strong><p>{fact.statement}</p><small>{fact.provenance_type}: {fact.source_excerpt}</small></div>)}</div>)}</li>)}</ul></details>}
        {item.approvals.length > 0 && <details><summary>Approval history</summary><ul>{item.approvals.map((approval, index) => <li key={`${item.id}-approval-${index}`}>{approval.action} · {approval.validation_result} · {approval.content_hash.slice(0, 12)}</li>)}</ul></details>}
        <div className="actions">
          {item.status !== "REJECTED" && item.status !== "APPROVED" && <>
            <button type="button" className="secondary" disabled={Boolean(busy) || preparation.stale} onClick={() => void act(item, "edit")}>Save edit and revalidate</button>
            <button type="button" className="secondary" disabled={Boolean(busy) || preparation.stale} onClick={() => void act(item, "accept")}>{item.kind === "COVER_DRAFT" ? "Accept draft" : "Accept recommendation"}</button>
            <button type="button" className="danger" disabled={Boolean(busy) || preparation.stale} onClick={() => void act(item, "reject")}>Reject</button>
          </>}
          {item.status === "ACCEPTED" && <button type="button" disabled={Boolean(busy) || preparation.stale || item.validation_result !== "PASS"} onClick={() => void act(item, "approve")}>Explicitly approve content</button>}
          {item.status === "APPROVED" && <span className="confirmed-badge">Approved draft · not submitted</span>}
        </div>
      </article>)}
    </>}
  </section>;
}

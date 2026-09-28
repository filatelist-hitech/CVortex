"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { ApiError, api } from "./access-shell";

type Incident = { id: string; severity: string; status: string; error_code: string; message: string; service: string; component: string; environment: string; occurrence_count: number; first_seen_at: string; last_seen_at: string; exception_class: string | null; retryable: boolean; impact: string; recovery_action: string; latest_operation?: string | null; latest_provider?: string | null };
type Occurrence = { id: string; created_at: string; request_id: string | null; job_id: string | null; llm_run_id: string | null; application_id: string | null; user_id: string | null; route: string | null; operation: string | null; provider: string | null; queue: string | null; connection: string | null; attempt: number | null; safe_stack: string | null };
type Detail = { incident: Incident; occurrences: Occurrence[] };
type Filters = Record<string, string>;

const causes: Record<string, string> = {
  LLM_PROVIDER_UNAVAILABLE: "Provider temporarily unavailable", LLM_PROVIDER_RATE_LIMITED: "Provider rate limited",
  LLM_PROVIDER_CONFIGURATION: "Provider configuration required", LLM_OUTPUT_INVALID: "Generated output could not be validated",
  LLM_REQUEST_REFUSED: "Provider refused the request", LLM_RESPONSE_INCOMPLETE: "Provider returned an incomplete result",
  LLM_PROVIDER_FAILED: "Provider operation failed", RATE_LIMITED: "Request rate limited",
  QUEUE_JOB_FAILED: "Background job failed", FRONTEND_RUNTIME_ERROR: "Browser operation failed",
};
const operations: Record<string, string> = { vacancy_requirement_extraction: "Vacancy analysis", career_text_extraction: "Career extraction", application_draft_generation: "Application draft generation", application_truth_review: "Application truth review" };
const advancedFields = ["service", "environment", "error_code", "from", "to", "request_id", "job_id", "llm_run_id", "application_id"];
const exactFormat = new Intl.DateTimeFormat("en", { day: "numeric", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", second: "2-digit", timeZoneName: "short" });
const clockFormat = new Intl.DateTimeFormat("en", { timeStyle: "medium" });

function relativeTime(value: string): string {
  const elapsed = Date.now() - new Date(value).getTime();
  if (!Number.isFinite(elapsed)) return "Time unavailable";
  const minutes = Math.max(0, Math.floor(elapsed / 60000));
  if (minutes < 1) return "Just now";
  if (minutes < 60) return `${minutes} min ago`;
  const hours = Math.floor(minutes / 60);
  return hours < 24 ? `${hours} h ago` : `${Math.floor(hours / 24)} d ago`;
}
function Timestamp({ value, clock = false }: { value: string; clock?: boolean }) {
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return <span>Time unavailable</span>;
  const exact = exactFormat.format(date);
  return <time dateTime={value} title={exact} aria-label={exact}>{clock ? clockFormat.format(date) : relativeTime(value)}<span className="diagnostics-exact">{exact}</span></time>;
}
function operationName(value: string | null | undefined): string | null { return value ? operations[value] ?? value.replaceAll("_", " ") : null; }
function titleFor(item: Incident, occurrence?: Occurrence): string {
  const operation = operationName(occurrence?.operation ?? item.latest_operation);
  if (operation) return `${operation} failed`;
  if (item.error_code === "FRONTEND_RUNTIME_ERROR") return "Browser operation failed";
  if (item.error_code === "QUEUE_JOB_FAILED") return "Background job failed";
  return item.error_code.startsWith("LLM_") ? "Analysis failed" : "Operation failed";
}
function causeFor(item: Incident): string { return causes[item.error_code] ?? "Cause not classified; inspect safe technical context"; }
function Severity({ value }: { value: string }) { return <span className={`diagnostics-severity severity-${value.toLowerCase()}`}><span aria-hidden="true">{value === "CRITICAL" ? "◆" : value === "ERROR" ? "!" : "△"}</span> {value}</span>; }
function Correlation({ label, value, onCopy }: { label: string; value: string | null; onCopy: (label: string, value: string) => void }) {
  if (!value) return null;
  return <div className="diagnostics-correlation-row"><dt>{label}</dt><dd><span title={value}>{value.length > 18 ? `${value.slice(0, 10)}…${value.slice(-5)}` : value}</span><button type="button" className="secondary" aria-label={`Copy ${label.toLowerCase()} ID`} onClick={() => onCopy(label, value)}>Copy</button></dd></div>;
}
function Stack({ value }: { value: string | null }) {
  if (!value) return <p className="muted">No sanitized stack was recorded.</p>;
  const lines = value.split("\n").filter(Boolean);
  const app = lines.slice(1).filter((line) => line.startsWith("[app]"));
  const framework = lines.slice(1).filter((line) => !line.startsWith("[app]"));
  return <div className="diagnostics-stack"><h5>Throw site</h5><pre>{lines[0]}</pre>{app.length > 0 && <><h5>Application frames</h5><pre>{app.join("\n")}</pre></>}{framework.length > 0 && <details><summary>Show framework frames ({framework.length})</summary><pre>{framework.join("\n")}</pre></details>}</div>;
}

export default function Diagnostics() {
  const [filters, setFilters] = useState<Filters>({});
  const [applied, setApplied] = useState<Filters>({});
  const [items, setItems] = useState<Incident[]>([]);
  const [detail, setDetail] = useState<Detail | null>(null);
  const [error, setError] = useState("");
  const [retryableError, setRetryableError] = useState(false);
  const [busy, setBusy] = useState(true);
  const [detailBusy, setDetailBusy] = useState(false);
  const [filterOpen, setFilterOpen] = useState(false);
  const [expanded, setExpanded] = useState<string | null>(null);
  const [technicalOpen, setTechnicalOpen] = useState(false);
  const [copied, setCopied] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [refreshedAt, setRefreshedAt] = useState<Date | null>(null);

  const load = useCallback(async (current: Filters, nextPage = 1) => {
    setBusy(true);
    try {
      const params = new URLSearchParams(Object.entries(current).filter(([, value]) => value.trim()));
      params.set("page", String(nextPage));
      const result = await api(`/api/v1/diagnostics/incidents?${params}`);
      setItems(result.data.data); setApplied(current);
      setPage(result.data.current_page ?? nextPage); setLastPage(result.data.last_page ?? 1);
      setTotal(result.data.total ?? result.data.data.length); setRefreshedAt(new Date());
      setError(""); setRetryableError(false);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Diagnostics could not be loaded.");
      setRetryableError(caught instanceof ApiError && caught.retryable);
    } finally { setBusy(false); }
  }, []);
  useEffect(() => { const timer = window.setTimeout(() => void load({}), 0); return () => window.clearTimeout(timer); }, [load]);
  function apply(next: Filters) { setFilters(next); setDetail(null); void load(next); }
  function setField(field: string, value: string) { setFilters((current) => ({ ...current, [field]: value })); }
  async function open(id: string) {
    setDetailBusy(true); setExpanded(null); setTechnicalOpen(false);
    try { const result = await api(`/api/v1/diagnostics/incidents/${id}`); setDetail(result.data); setError(""); setRetryableError(false); }
    catch (caught) { setError(caught instanceof Error ? caught.message : "Incident could not be loaded."); setRetryableError(caught instanceof ApiError && caught.retryable); }
    finally { setDetailBusy(false); }
  }
  async function setStatus(status: string) {
    if (!detail) return;
    try {
      const result = await api(`/api/v1/diagnostics/incidents/${detail.incident.id}`, { method: "PATCH", body: JSON.stringify({ status }) });
      setDetail(result.data); await load(applied, page); setError(""); setRetryableError(false);
    } catch (caught) { setError(caught instanceof Error ? caught.message : "Status could not be updated."); setRetryableError(caught instanceof ApiError && caught.retryable); }
  }
  async function copy(label: string, value: string) {
    try { await navigator.clipboard.writeText(value); setCopied(`${label} copied`); }
    catch { setCopied(`Could not copy ${label.toLowerCase()}`); }
  }
  const active = Object.entries(applied).filter(([, value]) => value);
  const selected = detail?.incident;
  const latest = detail?.occurrences[0];
  const visibleOpen = items.filter((item) => item.status === "OPEN").length;
  const visibleCritical = items.filter((item) => item.severity === "CRITICAL").length;
  const visibleRecurring = items.filter((item) => item.occurrence_count > 1).length;

  return <section className="diagnostics" aria-labelledby="diagnostics-title">
    {selected && <h1 id="diagnostics-title" className="sr-only">Error Center incident detail</h1>}
    {!selected && <header className="diagnostics-header"><div><h1 id="diagnostics-title">Error Center</h1><p>Operational errors and incidents</p></div><div className="diagnostics-refresh"><button type="button" className="secondary" onClick={() => void load(applied, page)}>Reload</button>{refreshedAt && <small>Updated {refreshedAt.toLocaleTimeString()}</small>}</div></header>}
    {!selected && <>
      <form className="diagnostics-search" role="search" onSubmit={(event: FormEvent) => { event.preventDefault(); apply(filters); }}><label htmlFor="diagnostics-query">Search incidents</label><div><input id="diagnostics-query" type="search" maxLength={128} value={filters.search ?? ""} onChange={(event) => setField("search", event.target.value)} onPaste={(event) => { const value = event.clipboardData.getData("text").trim(); if (value) { event.preventDefault(); apply({ ...filters, search: value }); } }} placeholder="Search request, job, LLM run, application, error code…" /><button type="submit" disabled={busy}>Search</button>{filters.search && <button type="button" className="secondary" onClick={() => apply({ ...filters, search: "" })}>Clear search</button>}</div></form>
      <div className={`diagnostics-toolbar${filterOpen ? " is-open" : ""}`}><div className="diagnostics-quick"><label>Status<select value={filters.status ?? ""} onChange={(event) => setField("status", event.target.value)}><option value="">All statuses</option>{["OPEN", "RESOLVED", "IGNORED"].map((value) => <option key={value}>{value}</option>)}</select></label><label>Severity<select value={filters.severity ?? ""} onChange={(event) => setField("severity", event.target.value)}><option value="">All severities</option>{["CRITICAL", "ERROR", "WARNING"].map((value) => <option key={value}>{value}</option>)}</select></label><label>Component<input maxLength={96} value={filters.component ?? ""} onChange={(event) => setField("component", event.target.value)} placeholder="Any component" /></label><label>Time<select value={filters.hours ?? ""} onChange={(event) => setField("hours", event.target.value)}><option value="">Any time</option><option value="24">Last 24h</option><option value="168">Last 7d</option><option value="720">Last 30d</option></select></label></div><div className="actions"><button type="button" className="secondary" aria-expanded={filterOpen} aria-controls="diagnostics-advanced" onClick={() => setFilterOpen(!filterOpen)}>More filters</button><button type="button" onClick={() => apply(filters)} disabled={busy}>Apply filters</button></div></div>
      <div id="diagnostics-advanced" className="diagnostics-advanced" hidden={!filterOpen}>{advancedFields.map((field) => <label key={field}>{field.replaceAll("_", " ")}<input type={field === "from" || field === "to" ? "date" : "text"} maxLength={128} value={filters[field] ?? ""} onChange={(event) => setField(field, event.target.value)} /></label>)}</div>
      {active.length > 0 && <div className="diagnostics-chips" aria-label="Active filters">{active.map(([key, value]) => <button key={key} type="button" className="secondary" aria-label={`Remove ${key.replaceAll("_", " ")} filter`} onClick={() => { const next = { ...applied }; delete next[key]; apply(next); }}>{key === "hours" ? ({ "24": "Last 24h", "168": "Last 7d", "720": "Last 30d" }[value] ?? value) : `${key.replaceAll("_", " ")}: ${value}`} ×</button>)}<button type="button" className="diagnostics-link" onClick={() => apply({})}>Clear all</button></div>}
      <div className="diagnostics-metrics" aria-label="Current page summary"><div><strong>{visibleOpen}</strong><span>Open on this page</span></div><div><strong>{visibleCritical}</strong><span>Critical on this page</span></div><div><strong>{visibleRecurring}</strong><span>Recurring on this page</span></div><div><strong>{total}</strong><span>Matching incidents</span></div></div>
      <div className="diagnostics-list-heading"><h2>Incidents</h2><label>Sort<select value={filters.sort ?? "priority"} onChange={(event) => apply({ ...filters, sort: event.target.value })}><option value="priority">Priority</option><option value="last_seen">Last seen</option><option value="first_seen">First seen</option><option value="occurrences">Occurrence count</option><option value="severity">Severity</option></select></label></div>
    </>}
    {selected && <button type="button" className="diagnostics-link" onClick={() => setDetail(null)}>← All incidents</button>}
    {error && <div className="alert error diagnostics-error" role="alert"><div><strong>Diagnostics unavailable</strong><p>{error}</p><p>If diagnostics remain unavailable, use local container logs.</p></div>{retryableError && <button type="button" className="secondary" onClick={() => selected ? void open(selected.id) : void load(applied, page)}>Retry</button>}</div>}
    {copied && <p className="diagnostics-feedback" role="status" aria-live="polite">{copied}</p>}
    {(busy || detailBusy) && <div className="diagnostics-skeleton" role="status" aria-label={detailBusy ? "Loading incident details" : "Loading incidents"}><span /><span /><span /></div>}
    {!selected && !busy && !error && items.length === 0 && <div className="diagnostics-empty"><h2>{applied.search ? "No incident found for this reference" : active.length ? "No incidents match these filters" : "No incidents recorded"}</h2><p>{applied.search ? "The event may have expired, failed before persistence, or exist only in local logs." : active.length ? "Clear filters or change the time range." : "The system has not recorded operational errors yet."}</p>{active.length > 0 && <button type="button" className="secondary" onClick={() => apply({})}>Clear filters</button>}</div>}
    {!selected && !busy && !error && items.length > 0 && <ul className="diagnostics-list">{items.map((item) => <li key={item.id}><button type="button" onClick={() => void open(item.id)}><span className="diagnostics-item-top"><Severity value={item.severity} /><span className="diagnostics-status">{item.status}</span></span><strong>{titleFor(item)}</strong><span className="diagnostics-cause">{causeFor(item)}</span><span className="diagnostics-item-meta"><span>{operationName(item.latest_operation) ?? item.component}{item.latest_provider ? ` · ${item.latest_provider}` : ""}</span><span>{item.occurrence_count} {item.occurrence_count === 1 ? "occurrence" : "occurrences"}</span><span>Last seen <Timestamp value={item.last_seen_at} /></span></span></button></li>)}</ul>}
    {!selected && !busy && !error && total > 0 && <nav className="diagnostics-pages" aria-label="Incident pages"><button type="button" className="secondary" disabled={page <= 1} onClick={() => void load(applied, page - 1)}>Previous</button><span>Page {page} of {lastPage} · {total} incidents</span><button type="button" className="secondary" disabled={page >= lastPage} onClick={() => void load(applied, page + 1)}>Next</button></nav>}
    {selected && !detailBusy && <article className="diagnostics-detail" aria-labelledby="incident-title"><div className="diagnostics-detail-head"><div><div className="diagnostics-item-top"><Severity value={selected.severity} /><span className="diagnostics-status">Status: {selected.status} · {selected.occurrence_count} occurrences</span></div><h2 id="incident-title">{titleFor(selected, latest)}</h2><p className="diagnostics-code">{selected.error_code} <button type="button" className="diagnostics-link" onClick={() => void copy("Error code", selected.error_code)}>Copy code</button></p><p>{selected.message}</p></div><div className="actions">{["OPEN", "RESOLVED", "IGNORED"].filter((status) => status !== selected.status).map((status) => <button key={status} type="button" className="secondary" onClick={() => void setStatus(status)}>{status === "OPEN" ? "Reopen" : status === "RESOLVED" ? "Resolve" : "Ignore"}</button>)}</div></div>
      <section className="diagnostics-decision" aria-label="Diagnosis and next action"><div><h3>Cause</h3><p className="diagnostics-cause-head">{causeFor(selected)}</p></div><div><h3>Impact</h3><p>{selected.impact}</p><p className="muted">Diagnostics do not confirm whether data changed.</p></div><div><h3>Recommended action</h3><p>{selected.recovery_action}</p></div><div className="diagnostics-retry"><h3>Retryable</h3><p><strong>{selected.retryable ? "Yes" : "No"}</strong> — {selected.retryable ? "Retry after the stated condition clears." : "Address the cause before running the operation again."}</p></div></section>
      <dl className="diagnostics-timing"><div><dt>First seen</dt><dd><Timestamp value={selected.first_seen_at} /></dd></div><div><dt>Last seen</dt><dd><Timestamp value={selected.last_seen_at} /></dd></div><div><dt>Occurrences</dt><dd>{selected.occurrence_count}</dd></div></dl>
      {(latest?.operation || latest?.provider || latest?.attempt != null) && <section className="diagnostics-context" aria-labelledby="context-title"><h3 id="context-title">Operational context</h3><dl>{latest.operation && <div><dt>Affected operation</dt><dd>{operationName(latest.operation)}</dd></div>}{latest.provider && <div><dt>Provider</dt><dd>{latest.provider}</dd></div>}{latest.attempt != null && <div><dt>Attempt</dt><dd>{latest.attempt}</dd></div>}</dl></section>}
      <section className="diagnostics-context" aria-labelledby="correlation-title"><div className="diagnostics-section-title"><h3 id="correlation-title">Correlation</h3><button type="button" className="secondary" onClick={() => void copy("Diagnostic summary", [`Error: ${selected.error_code}`, `Incident: ${selected.id}`, latest?.request_id ? `Request: ${latest.request_id}` : null, latest?.operation ? `Operation: ${operationName(latest.operation)}` : null, `Last seen: ${selected.last_seen_at}`].filter(Boolean).join("\n"))}>Copy diagnostic summary</button></div><dl><Correlation label="Incident" value={selected.id} onCopy={copy} /><Correlation label="Request" value={latest?.request_id ?? null} onCopy={copy} /><Correlation label="Job" value={latest?.job_id ?? null} onCopy={copy} /><Correlation label="LLM run" value={latest?.llm_run_id ?? null} onCopy={copy} /><Correlation label="Application" value={latest?.application_id ?? null} onCopy={copy} /></dl></section>
      <section className="diagnostics-occurrences" aria-labelledby="occurrences-title"><h3 id="occurrences-title">Recent occurrences <span>{detail?.occurrences.length} of {selected.occurrence_count}</span></h3><ul>{detail?.occurrences.map((event) => <li key={event.id}><button type="button" className="diagnostics-occurrence-button" aria-expanded={expanded === event.id} onClick={() => setExpanded(expanded === event.id ? null : event.id)}><Timestamp value={event.created_at} clock /><span>{event.provider ?? operationName(event.operation) ?? "Operation"}</span>{event.attempt != null && <span>Attempt {event.attempt}</span>}{event.request_id && <span>Request …{event.request_id.slice(-5)}</span>}<span>{expanded === event.id ? "Hide" : "View"}</span></button>{expanded === event.id && <div className="diagnostics-occurrence-detail"><dl>{(["request_id", "job_id", "llm_run_id", "application_id", "user_id", "route", "operation", "provider", "queue", "connection", "attempt"] as const).map((field) => event[field] != null && <div key={field}><dt>{field.replaceAll("_", " ")}</dt><dd>{String(event[field])}</dd></div>)}</dl></div>}</li>)}</ul></section>
      <section className="diagnostics-technical"><button type="button" className="diagnostics-technical-toggle" aria-expanded={technicalOpen} onClick={() => setTechnicalOpen(!technicalOpen)}>Technical details <span>{technicalOpen ? "Hide" : "Expand"}</span></button>{technicalOpen && <div><dl><div><dt>Exception class</dt><dd>{selected.exception_class ?? "No exception class recorded"}</dd></div><div><dt>Component</dt><dd>{selected.component}</dd></div><div><dt>Environment</dt><dd>{selected.environment}</dd></div><div><dt>Service</dt><dd>{selected.service}</dd></div></dl><Stack value={latest?.safe_stack ?? null} /></div>}</section>
    </article>}
  </section>;
}

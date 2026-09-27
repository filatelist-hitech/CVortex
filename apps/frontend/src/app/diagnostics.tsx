"use client";

import { FormEvent, useCallback, useEffect, useState } from "react";
import { ApiError, api } from "./access-shell";

type Incident = {
  id: string; severity: string; status: string; error_code: string; message: string;
  service: string; component: string; environment: string; occurrence_count: number;
  first_seen_at: string; last_seen_at: string; exception_class: string | null;
};
type Occurrence = {
  id: string; created_at: string; request_id: string | null; job_id: string | null;
  llm_run_id: string | null; application_id: string | null; user_id: string | null;
  route: string | null; operation: string | null; provider: string | null;
  queue: string | null; connection: string | null;
  attempt: number | null; safe_stack: string | null;
};
type Detail = { incident: Incident; occurrences: Occurrence[] };

export default function Diagnostics() {
  const [filters, setFilters] = useState<Record<string, string>>({});
  const [items, setItems] = useState<Incident[]>([]);
  const [detail, setDetail] = useState<Detail | null>(null);
  const [error, setError] = useState("");
  const [retryableError, setRetryableError] = useState(false);
  const [busy, setBusy] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const load = useCallback(async (current: Record<string, string>, nextPage = 1) => {
    setBusy(true);
    try {
      const params = new URLSearchParams(Object.entries(current).filter(([, value]) => value.trim()));
      params.set("page", String(nextPage));
      const result = await api(`/api/v1/diagnostics/incidents?${params}`);
      setItems(result.data.data);
      setPage(result.data.current_page ?? nextPage);
      setLastPage(result.data.last_page ?? 1);
      setTotal(result.data.total ?? result.data.data.length);
      setError("");
      setRetryableError(false);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Diagnostics could not be loaded.");
      setRetryableError(caught instanceof ApiError && caught.retryable);
    } finally { setBusy(false); }
  }, []);

  useEffect(() => {
    const timer = window.setTimeout(() => void load({}), 0);
    return () => window.clearTimeout(timer);
  }, [load]);

  async function open(id: string) {
    try {
      const result = await api(`/api/v1/diagnostics/incidents/${id}`);
      setDetail(result.data);
      setError("");
      setRetryableError(false);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Incident could not be loaded.");
      setRetryableError(caught instanceof ApiError && caught.retryable);
    }
  }

  async function setStatus(status: string) {
    if (!detail) return;
    try {
      const result = await api(`/api/v1/diagnostics/incidents/${detail.incident.id}`, { method: "PATCH", body: JSON.stringify({ status }) });
      setDetail(result.data);
      await load(filters, page);
      setRetryableError(false);
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Status could not be updated.");
      setRetryableError(caught instanceof ApiError && caught.retryable);
    }
  }

  function showAllIncidents() {
    setFilters({});
    setDetail(null);
    void load({}, 1);
  }

  const fields = ["search", "severity", "status", "service", "component", "environment", "error_code", "from", "to", "request_id", "job_id", "llm_run_id", "application_id"];
  return <section className="review-section diagnostics" aria-labelledby="diagnostics-title">
    <div className="section-title"><div><p className="eyebrow">Admin only</p><h2 id="diagnostics-title">Error Center</h2></div><button type="button" className="secondary" onClick={() => void load(filters, page)}>Reload</button></div>
    <p>All incident severities and statuses are shown by default. Use filters only to narrow the list.</p>
    <form className="diagnostics-filters" onSubmit={(event: FormEvent) => { event.preventDefault(); setDetail(null); void load(filters); }}>
      {fields.map((field) => <label key={field}>{field.replaceAll("_", " ")}{field === "severity" || field === "status" ? <select value={filters[field] ?? ""} onChange={(event) => setFilters({ ...filters, [field]: event.target.value })}><option value="">All</option>{(field === "severity" ? ["WARNING", "ERROR", "CRITICAL"] : ["OPEN", "RESOLVED", "IGNORED"]).map((value) => <option key={value}>{value}</option>)}</select> : <input type={field === "from" || field === "to" ? "date" : "text"} maxLength={128} value={filters[field] ?? ""} onChange={(event) => setFilters({ ...filters, [field]: event.target.value })} placeholder={field === "search" ? "Request, job, LLM, application ID or code" : undefined} />}</label>)}
      <div className="actions"><button type="submit" disabled={busy}>Apply filters</button><button type="button" className="secondary" disabled={busy} onClick={showAllIncidents}>Show all incidents</button></div>
    </form>
    {error && <div className="alert error" role="alert">{error}{retryableError && <button type="button" className="secondary" onClick={() => void load(filters, page)}>Retry</button>}</div>}
    {busy && <p role="status">Loading incidents…</p>}
    {!busy && items.length === 0 && <p className="empty">{Object.values(filters).some(Boolean) ? "No incidents for the selected filters and time range." : "No incidents recorded."}</p>}
    <ul className="diagnostics-list">{items.map((item) => <li key={item.id}><button type="button" className="secondary" onClick={() => void open(item.id)}><span className={`badge ${item.severity === "ERROR" || item.severity === "CRITICAL" ? "blocked-badge" : "pending-badge"}`}>{item.severity}</span><strong>{item.error_code}</strong><span>{item.message}</span><small>{item.service}/{item.component} · {item.environment} · {item.status} · {item.occurrence_count} occurrences · {item.first_seen_at} → {item.last_seen_at}</small></button></li>)}</ul>
    {total > 0 && <nav className="actions" aria-label="Incident pages"><button type="button" className="secondary" disabled={busy || page <= 1} onClick={() => { setDetail(null); void load(filters, page - 1); }}>Previous</button><span>Page {page} of {lastPage} · {total} incidents</span><button type="button" className="secondary" disabled={busy || page >= lastPage} onClick={() => { setDetail(null); void load(filters, page + 1); }}>Next</button></nav>}
    {detail && <section className="panel diagnostics-detail" aria-labelledby="incident-title"><h3 id="incident-title">{detail.incident.error_code}</h3><p>{detail.incident.message}</p><p>{detail.incident.severity} · {detail.incident.status} · {detail.incident.component} · {detail.incident.exception_class ?? "Browser event"}</p><div className="actions">{["OPEN", "RESOLVED", "IGNORED"].map((status) => <button key={status} type="button" className="secondary" disabled={status === detail.incident.status} onClick={() => void setStatus(status)}>{status}</button>)}</div><h4>Recent occurrences</h4><ul>{detail.occurrences.map((event) => <li key={event.id}><time>{event.created_at}</time><p>Request {event.request_id ?? "—"} · Job {event.job_id ?? "—"} · LLM {event.llm_run_id ?? "—"} · Application {event.application_id ?? "—"}</p><p>User {event.user_id ?? "—"} · Route {event.route ?? "—"} · Operation {event.operation ?? "—"} · Provider {event.provider ?? "—"} · Attempt {event.attempt ?? "—"}</p><p>Queue {event.queue ?? "—"} · Connection {event.connection ?? "—"}</p>{event.safe_stack && <pre className="source-copy">{event.safe_stack}</pre>}</li>)}</ul></section>}
  </section>;
}

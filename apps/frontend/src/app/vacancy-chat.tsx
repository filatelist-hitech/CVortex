"use client";

import { useEffect, useRef, useState } from "react";
import { api } from "./access-shell";
import ChatGptConnectionPanel from "./chatgpt-connection";

type Message = { id: string; role: string; content: string; status: string; error_code: string | null };
type Thread = { id: string; status: string; model: string | null; connection_id: string | null; messages: Message[] };
type Requirement = { dimension: string; importance: string; label: string; source_excerpt: string };
type Match = { requirement_index: number; career_fact_ids: string[] };
type Analysis = { matches?: Match[]; proposed_matches?: Match[]; requirements: Requirement[]; gaps: string[]; risks: string[]; questions: string[]; recommendations: string[] };
type Draft = Analysis & { id: string; status: string; origin: string; source_channel: string };

function structured(text: string): Analysis | null {
  try {
    const value = JSON.parse(text);
    if (!value || typeof value !== "object" || !["requirements", "matches", "gaps", "risks", "questions", "recommendations"].every((key) => Array.isArray(value[key]))) return null;
    if (!value.requirements.every((item: Requirement) => item && typeof item.label === "string" && typeof item.source_excerpt === "string" && typeof item.importance === "string")) return null;
    if (!value.matches.every((item: Match) => item && Number.isInteger(item.requirement_index) && Array.isArray(item.career_fact_ids) && item.career_fact_ids.every((id) => typeof id === "string"))) return null;
    if (![...value.gaps, ...value.risks, ...value.questions, ...value.recommendations].every((item) => typeof item === "string")) return null;
    return value;
  } catch { return null; }
}

function AnalysisView({ analysis }: { analysis: Analysis }) {
  return <>
    <h4>Requirements</h4><ul>{analysis.requirements.map((item, index) => <li key={index}><strong>{item.importance} · {item.label}</strong><blockquote>{item.source_excerpt}</blockquote></li>)}</ul>
    <h4>Proposed confirmed-fact matches</h4><ul>{(analysis.matches ?? analysis.proposed_matches ?? []).map((match, index) => <li key={index}>{analysis.requirements[match.requirement_index]?.label} · {match.career_fact_ids.join(", ")}</li>)}</ul>
    {(["gaps", "risks", "questions", "recommendations"] as const).map((key) => <div key={key}><h4>{key[0].toUpperCase() + key.slice(1)}</h4><ul>{analysis[key].map((note, index) => <li key={index}>{note}</li>)}</ul></div>)}
  </>;
}

const errors: Record<string, string> = {
  subscription_sharing_usage_limit_exceeded: "Usage limit reached. Open Manage usage to review your app and plan limits.",
  USAGE_LIMIT_REACHED: "Usage limit reached. Open Manage usage.",
  REAUTHENTICATION_REQUIRED: "Sign in with ChatGPT again, then refresh the connection.",
  PLAN_PERMISSION_MISSING: "Enable ChatGPT plan usage during sign-in.",
  subscription_sharing_user_not_eligible: "This account or workspace cannot use ChatGPT plan usage.",
  subscription_sharing_usage_unavailable: "Plan usage could not be checked. Retry later.",
  subscription_sharing_unsupported_capability: "This request uses a capability unavailable on the plan route. Contact your operator.",
  model_not_found: "Model unavailable. Refresh connection and choose another model.",
  PROVIDER_UNAVAILABLE: "Provider unavailable. Retry later.",
  USER_CANCELLED: "Generation cancelled. The partial answer is saved as interrupted.",
  STREAM_INTERRUPTED: "Streaming connection lost. The saved partial answer remains interrupted; it cannot be saved as analysis.",
};

export default function VacancyChat({ vacancyId, onApproved }: { vacancyId: string; onApproved?: () => void }) {
  const [thread, setThread] = useState<Thread | null>(null);
  const [drafts, setDrafts] = useState<Draft[]>([]);
  const [configuration, setConfiguration] = useState({ id: "", model: "" });
  const [content, setContent] = useState("");
  const [live, setLive] = useState("");
  const [busy, setBusy] = useState(false);
  const [cancelRequestId, setCancelRequestId] = useState<string | null>(null);
  const [cancelling, setCancelling] = useState(false);
  const [error, setError] = useState("");
  const [saving, setSaving] = useState<string | null>(null);
  const abortController = useRef<AbortController | null>(null);
  const intentionalAbort = useRef(false);

  useEffect(() => {
    let active = true;
    Promise.all([api(`/api/v1/vacancies/${vacancyId}/chat`), api(`/api/v1/vacancies/${vacancyId}/analysis-drafts`)])
      .then(([chat, saved]) => { if (active) { setThread(chat.data); setDrafts(saved.data); } })
      .catch((cause) => { if (active) setError(cause instanceof Error ? cause.message : "Chat could not be loaded."); });
    return () => { active = false; };
  }, [vacancyId]);

  async function refresh() {
    const [chat, saved] = await Promise.all([api(`/api/v1/vacancies/${vacancyId}/chat`), api(`/api/v1/vacancies/${vacancyId}/analysis-drafts`)]);
    setThread(chat.data);
    setDrafts(saved.data);
  }

  async function send(analyze = false) {
    if (!configuration.id || !configuration.model) return;
    setBusy(true); setLive(""); setError("");
    setCancelRequestId(null); setCancelling(false);
    intentionalAbort.current = false;
    let completed = false;
    const clientRequestId = crypto.randomUUID();
    const controller = new AbortController();
    abortController.current = controller;
    try {
      const csrf = decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");
      const response = await fetch(`/api/v1/vacancies/${vacancyId}/chat/messages`, {
        method: "POST", credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "text/event-stream", "X-XSRF-TOKEN": csrf },
        body: JSON.stringify({ connection_id: configuration.id, model: configuration.model, client_request_id: clientRequestId, content, analyze }),
        signal: controller.signal,
      });
      if (!response.ok) {
        const body = await response.json().catch(() => null);
        throw new Error(errors[body?.error?.code] ?? body?.message ?? "Chat request failed. Refresh the chat before retrying.");
      }
      if (!response.body) throw new Error(errors.STREAM_INTERRUPTED);
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";
      try {
        while (true) {
          const { done, value } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          let position: number;
          while ((position = buffer.indexOf("\n\n")) >= 0) {
            const frame = buffer.slice(0, position); buffer = buffer.slice(position + 2);
            if (!frame.startsWith("data: ")) continue;
            const event = JSON.parse(frame.slice(6));
            if (event.type === "started") setCancelRequestId(clientRequestId);
            if (event.type === "delta") setLive((text) => text + event.text);
            if (event.type === "completed") completed = true;
            if (event.type === "error") throw new Error(errors[event.code] ?? `Chat failed (${event.code}). Refresh connection or contact your operator.`);
          }
        }
      } finally { await reader.cancel(); reader.releaseLock(); }
      if (!completed) throw new Error(errors.STREAM_INTERRUPTED);
      setContent("");
    } catch (cause) {
      if (!intentionalAbort.current && !(cause instanceof Error && cause.name === "AbortError")) {
        setError(cause instanceof Error ? cause.message : errors.STREAM_INTERRUPTED);
      }
    }
    finally {
      intentionalAbort.current = false;
      if (abortController.current === controller) abortController.current = null;
      setCancelRequestId(null); setCancelling(false);
      setBusy(false);
      try { await refresh(); setLive(""); } catch { setError("Chat status could not be refreshed. Reload to retrieve saved messages."); }
    }
  }

  async function cancel() {
    const requestId = cancelRequestId;
    const controller = abortController.current;
    if (!requestId || !controller || cancelling) return;
    setCancelling(true); setError("");
    try {
      const result = await api(`/api/v1/vacancies/${vacancyId}/chat/cancel`, {
        method: "POST", body: JSON.stringify({ client_request_id: requestId }),
      });
      intentionalAbort.current = true;
      controller.abort();
      setError(result?.cancelled ? "Generation cancelled. The partial answer is saved as interrupted." : "Generation finished before cancellation; saved messages were refreshed.");
    } catch (cause) {
      setError(cause instanceof Error ? `Cancellation failed: ${cause.message}` : "Cancellation failed. The stream is still active.");
      setCancelling(false);
    }
  }

  async function save(message: Message) {
    setSaving(message.id); setError("");
    try {
      await api(`/api/v1/vacancies/${vacancyId}/analysis-drafts`, { method: "POST", body: JSON.stringify({ message_id: message.id, client_request_id: `message_${message.id}` }) });
      await refresh();
    } catch (cause) { setError(cause instanceof Error ? `${cause.message} Draft requires literal supported requirement labels/excerpts and current confirmed facts. Ask ChatGPT to correct the structured result.` : "Analysis draft could not be saved."); }
    finally { setSaving(null); }
  }

  async function approve(draft: Draft) {
    setSaving(draft.id); setError("");
    try {
      await api(`/api/v1/vacancy-analysis-drafts/${draft.id}/approve`, { method: "POST" });
      await refresh(); onApproved?.();
    } catch (cause) { setError(cause instanceof Error ? cause.message : "Approval blocked. Check the current source and confirmed evidence."); }
    finally { setSaving(null); }
  }

  return <section className="review-section" aria-label="Vacancy Chat">
    <p className="eyebrow">Chat</p><h3>ChatGPT</h3>
    <ChatGptConnectionPanel preferredConnectionId={thread?.connection_id} preferredModel={thread?.model} onSelect={(id, model) => setConfiguration({ id, model })} />
    <p className="muted">Send shares this vacancy snapshot, up to 20 relevant CONFIRMED facts, available prior approved employer statements and bounded chat history with OpenAI. This chat belongs to CVortex. Career Facts stay unchanged.</p>
    {configuration.id && <p>Using ChatGPT plan · <a href="https://chatgpt.com/settings/usage" target="_blank" rel="noopener noreferrer">Manage usage</a></p>}
    <div aria-label="Conversation messages">{thread?.messages.map((message) => {
      const analysis = message.role === "assistant" ? structured(message.content) : null;
      return <article className="panel" key={message.id}><strong>{message.role === "user" ? "You" : "ChatGPT"} · {message.status}</strong>
        {analysis ? <AnalysisView analysis={analysis} /> : <pre className="source-copy">{message.content}</pre>}
        {message.error_code && <p className="error">{errors[message.error_code] ?? message.error_code}</p>}
        {analysis && message.status === "COMPLETED" && <button type="button" disabled={busy || saving !== null} onClick={() => void save(message)}>Save analysis</button>}
      </article>;
    })}</div>
    {busy && <div role="status"><strong>Streaming</strong><pre className="source-copy">{live}</pre>
      <button type="button" className="secondary" disabled={!cancelRequestId || cancelling} onClick={() => void cancel()}>{cancelling ? "Cancelling…" : "Cancel generation"}</button>
    </div>}
    <form onSubmit={(event) => { event.preventDefault(); void send(); }}><label>Message<textarea value={content} maxLength={4000} onChange={(event) => setContent(event.target.value)} /></label>
      <button disabled={busy || !configuration.id || !configuration.model || !content.trim()}>Send</button>
      <button type="button" disabled={busy || !configuration.id || !configuration.model} onClick={() => void send(true)}>Analyze vacancy</button>
    </form>
    <button type="button" className="secondary" disabled={busy} onClick={() => void refresh().catch(() => setError("Refresh failed."))}>Reload chat</button>
    {error && <p role="alert" className="error">{error}</p>}
    {drafts.length > 0 && <section aria-label="Saved analysis drafts"><h3>Analysis drafts</h3>{drafts.map((draft) => <article className="panel" key={draft.id}><strong>{draft.status} · {draft.origin} · {draft.source_channel}</strong><AnalysisView analysis={draft} />
      {draft.status === "DRAFT" && <><p className="muted">Review source excerpts. Approval recomputes matching from current confirmed facts and promotes the requirements to the vacancy workflow.</p><button type="button" disabled={saving !== null || busy} onClick={() => void approve(draft)}>Approve analysis</button></>}
    </article>)}</section>}
  </section>;
}

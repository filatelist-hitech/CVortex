"use client";

import { useEffect, useRef, useState } from "react";
import { api } from "./access-shell";

type Connection = { id: string; status: string; client_id: string; welcome_acknowledged_at?: string | null };
type Model = { slug: string; display_name: string };

const explanations: Record<string, string> = {
  PLAN_PERMISSION_MISSING: "Sign in again and allow ChatGPT plan usage.",
  OAUTH_DECLINED: "Permission was declined. You can start a new sign-in.",
  REAUTHENTICATION_REQUIRED: "Sign in again to restore access.",
  STREAM_INTERRUPTED: "The stream ended before completion. This check did not pass.",
  subscription_sharing_usage_limit_exceeded: "Usage limit reached. Review your app and plan limits in Manage usage.",
  USAGE_LIMIT_REACHED: "Usage limit reached. Open Manage usage.",
  subscription_sharing_user_not_eligible: "ChatGPT plan usage is unavailable for this account or workspace.",
  subscription_sharing_unsupported_capability: "The provider rejected this capability. Contact your operator.",
  subscription_sharing_usage_unavailable: "Plan usage could not be checked. Retry later.",
  PROVIDER_UNAVAILABLE: "The provider is unavailable. Retry later.",
  PLAN_UNAVAILABLE: "Plan access was denied. Check your account, permissions and serving region.",
  model_not_found: "This model is unavailable. Refresh the model catalog and select another model.",
};

export default function ChatGptConnectionPanel({ onSelect, preferredConnectionId, preferredModel }: { onSelect?: (id: string, model: string) => void; preferredConnectionId?: string | null; preferredModel?: string | null } = {}) {
  const welcomeDialog = useRef<HTMLDialogElement>(null);
  const [showWelcome, setShowWelcome] = useState(false);
  useEffect(() => { if (showWelcome) welcomeDialog.current?.showModal?.(); }, [showWelcome]);
  const [open, setOpen] = useState(false);
  const [enabled, setEnabled] = useState(false);
  const [connections, setConnections] = useState<Connection[]>([]);
  const [connectionId, setConnectionId] = useState("");
  const [models, setModels] = useState<Model[]>([]);
  const [model, setModel] = useState("");
  const [status, setStatus] = useState("Not connected");
  const [message, setMessage] = useState("");
  const [output, setOutput] = useState("");
  const [busy, setBusy] = useState(false);

  async function catalog(id: string) {
    setModels([]);
    setModel("");
    const result = await api(`/api/v1/chatgpt/connections/${id}/models`);
    setModels(result.data);
    const selected = result.data.some((item: Model) => item.slug === preferredModel) ? preferredModel! : result.data[0]?.slug ?? "";
    setModel(selected);
    onSelect?.(id, selected);
  }

  async function refresh() {
    setBusy(true);
    setMessage("");
    try {
      const result = await api("/api/v1/chatgpt/connections");
      setEnabled(result.enabled);
      setConnections(result.data);
      const connection: Connection | undefined = result.data.find((item: Connection) => item.id === (connectionId || preferredConnectionId)) ?? result.data[0];
      setConnectionId(connection?.id ?? "");
      setShowWelcome(connection?.status === "CONNECTED" && !connection.welcome_acknowledged_at);
      setStatus(connection?.status === "CONNECTED" ? "Connected" : connection?.status.replaceAll("_", " ") ?? "Not connected");
      if (connection?.status === "CONNECTED") await catalog(connection.id);
      else { setModels([]); setModel(""); onSelect?.("", ""); }
    } catch (error) {
      setMessage(error instanceof Error ? error.message : "Connection status could not be loaded.");
    } finally { setBusy(false); }
  }

  async function connect(newAccount = false) {
    const popup = window.open("about:blank", "cvortex-chatgpt-signin");
    setBusy(true);
    setStatus("Connecting");
    setMessage("");
    try {
      const connection = connections.find((item) => item.id === connectionId);
      const result = await api("/api/v1/chatgpt/connections", { method: "POST", body: JSON.stringify({
        connection_id: newAccount ? null : connectionId || null,
        consent: connection?.status === "PLAN_PERMISSION_MISSING",
      }) });
      if (popup) popup.location.href = result.authorization_url;
      else window.location.assign(result.authorization_url);
      setMessage("Complete sign-in in the browser, then select Refresh connection. No vacancy or Career Facts are sent by this connection check.");
    } catch (error) {
      popup?.close();
      setStatus("Not connected");
      setMessage(error instanceof Error ? error.message : "Sign-in could not start.");
    } finally { setBusy(false); }
  }

  async function proof() {
    setBusy(true);
    setOutput("");
    setStatus("Streaming");
    setMessage("");
    let completed = false;
    try {
      const csrf = decodeURIComponent(document.cookie.split("; ").find((item) => item.startsWith("XSRF-TOKEN="))?.split("=")[1] ?? "");
      const response = await fetch(`/api/v1/chatgpt/connections/${connectionId}/proof`, {
        method: "POST", credentials: "same-origin",
        headers: { "Content-Type": "application/json", Accept: "text/event-stream", "X-XSRF-TOKEN": csrf },
        body: JSON.stringify({ model }),
      });
      if (!response.ok) {
        const result = await response.json().catch(() => null);
        throw new Error(explanations[result?.error?.code] ?? "Connection check failed. Refresh the connection or sign in again.");
      }
      if (!response.body) throw new Error(explanations.STREAM_INTERRUPTED);
      const reader = response.body.getReader();
      const decoder = new TextDecoder();
      let buffer = "";
      try {
        while (true) {
          const { value, done } = await reader.read();
          if (done) break;
          buffer += decoder.decode(value, { stream: true });
          let boundary: number;
          while ((boundary = buffer.indexOf("\n\n")) >= 0) {
            const frame = buffer.slice(0, boundary);
            buffer = buffer.slice(boundary + 2);
            if (!frame.startsWith("data: ")) continue;
            const event = JSON.parse(frame.slice(6));
            if (event.type === "delta") setOutput((current) => current + event.text);
            if (event.type === "completed") completed = true;
            if (event.type === "error") throw new Error(explanations[event.code] ?? `Connection check failed (${event.code}). Refresh the connection or contact your operator.`);
          }
        }
      } finally { await reader.cancel(); reader.releaseLock(); }
      if (!completed) throw new Error(explanations.STREAM_INTERRUPTED);
      setStatus("Completed");
      setMessage("The ChatGPT plan connection completed a streamed text request. You can use this account for vacancy chat.");
    } catch (error) {
      setStatus("Interrupted");
      setMessage(error instanceof Error ? error.message : explanations.STREAM_INTERRUPTED);
    } finally { setBusy(false); }
  }

  async function disconnect() {
    setBusy(true);
    try {
      const result = await api(`/api/v1/chatgpt/connections/${connectionId}`, { method: "DELETE" });
      await refresh();
      if (!result.remote_revocation_confirmed) setMessage("Local credentials cleared. Remote revocation was not confirmed; disconnect the app in ChatGPT settings.");
    } catch (error) { setMessage(error instanceof Error ? error.message : "Disconnect failed."); }
    finally { setBusy(false); }
  }

  const connected = connections.find((item) => item.id === connectionId)?.status === "CONNECTED";
  return <section className="review-section" aria-label="ChatGPT plan connection">
    <button type="button" className="secondary" onClick={() => { setOpen(!open); if (!open) void refresh(); }} aria-expanded={open}>ChatGPT plan connection</button>
    {open && <>
      {showWelcome && <dialog ref={welcomeDialog} aria-label="ChatGPT plan enabled" onCancel={() => setShowWelcome(false)}>
        <h3>You’re using your ChatGPT plan</h3><p>Eligible AI requests in CVortex use your ChatGPT plan. Manage usage and access in ChatGPT settings.</p>
        <button type="button" onClick={() => { void api(`/api/v1/chatgpt/connections/${connectionId}/welcome`, { method: "PATCH" }).then(() => setShowWelcome(false)).catch(() => setMessage("The confirmation could not be saved. Refresh connection.")); }}>Got it</button>
      </dialog>}
      <h3>ChatGPT</h3><p role="status">{status}</p>
      <p className="muted">Connect your ChatGPT plan. The existing OpenAI API provider uses separate API billing.</p>
      {!enabled && <p>Local ChatGPT plan usage is disabled. Your operator must enable it before connecting.</p>}
      {connections.length > 0 && <label>Account registration<select value={connectionId} disabled={busy} onChange={(event) => { setConnectionId(event.target.value); onSelect?.("", ""); setModels([]); setModel(""); setStatus("Refresh connection to load this account"); }}>{connections.map((item) => <option key={item.id} value={item.id}>{item.client_id} · {item.status}</option>)}</select></label>}
      <button type="button" disabled={!enabled || busy} onClick={() => void connect()}>Continue with ChatGPT</button>
      <button type="button" className="secondary" disabled={busy} onClick={() => void refresh()}>Refresh connection</button>
      {connections.length > 0 && <button type="button" className="secondary" disabled={!enabled || busy} onClick={() => void connect(true)}>Add ChatGPT account</button>}
      {connected && <>
        <p>Using ChatGPT plan · <a href="https://chatgpt.com/settings/usage" target="_blank" rel="noopener noreferrer">Manage usage</a></p>
        <label>Model<select value={model} disabled={busy} onChange={(event) => { setModel(event.target.value); onSelect?.(connectionId, event.target.value); }}>{models.map((item) => <option key={item.slug} value={item.slug}>{item.display_name}</option>)}</select></label>
        <p className="muted">Verify connection sends a short greeting request using your plan. It sends no Career Facts or vacancy content.</p>
        <button type="button" disabled={busy || !model} onClick={() => void proof()}>Verify connection</button>
      </>}
      {connectionId && <button type="button" className="secondary" disabled={busy} onClick={() => void disconnect()}>Disconnect</button>}
      {output && <pre className="source-copy">{output}</pre>}
      {message && <p role="alert">{message}</p>}
    </>}
  </section>;
}

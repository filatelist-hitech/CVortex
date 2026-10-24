"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
import { api } from "./access-shell";
import CareerClaim, { type Claim, type Fact } from "./career-claim";
import VacancyWorkspace from "./vacancy-workspace";

type Source = { id: string; extraction_status: "PENDING" | "RUNNING" | "COMPLETED" | "FAILED"; error_code: string | null };
const extractionLabels = { PENDING: "В очереди", RUNNING: "Выполняется", COMPLETED: "Завершено", FAILED: "Ошибка" };
const provenanceLabels = { paste_extraction: "Из текста", user_manual: "Добавлено вручную" };

type Overview = { facts: Fact[]; claims: Claim[]; sources: Source[] };

export default function CareerWorkspace({ userId }: { userId?: string } = {}) {
  const [overview, setOverview] = useState<Overview | null>(null);
  const [error, setError] = useState("");
  const [busy, setBusy] = useState("");
  const [edits, setEdits] = useState<Record<string, string>>({});

  const isMountedRef = useRef(true);
  const activeUserIdRef = useRef(userId);
  const latestRequestIdRef = useRef(0);
  const latestAppliedRequestIdRef = useRef(0);
  const mutationEpochRef = useRef(0);
  const activeMutationsRef = useRef(0);
  const activeAbortControllerRef = useRef<AbortController | null>(null);
  const resolvedClaimsRef = useRef<Record<string, { truth_status: Claim["truth_status"]; epoch: number }>>({});

  const refresh = useCallback(async (options: { force?: boolean } = {}) => {
    if (activeMutationsRef.current > 0 && !options.force) {
      return;
    }

    if (activeAbortControllerRef.current) {
      activeAbortControllerRef.current.abort();
      activeAbortControllerRef.current = null;
    }

    const abortController = new AbortController();
    activeAbortControllerRef.current = abortController;

    const requestId = ++latestRequestIdRef.current;
    const requestEpoch = mutationEpochRef.current;
    const currentUserId = activeUserIdRef.current;

    try {
      const result = await api("/api/v1/career", { signal: abortController.signal });

      if (!isMountedRef.current) return;
      if (activeUserIdRef.current !== currentUserId) return;
      if (requestId < latestAppliedRequestIdRef.current || requestId < latestRequestIdRef.current) {
        return;
      }
      if (requestEpoch < mutationEpochRef.current) {
        return;
      }

      const serverOverview = result.data as Overview;
      const mergedClaims: Claim[] = serverOverview.claims.map((claim) => {
        const localResolution = resolvedClaimsRef.current[claim.id];
        if (localResolution) {
          if (localResolution.epoch >= requestEpoch) {
            return { ...claim, truth_status: localResolution.truth_status };
          }
          if (claim.truth_status === "USER_RESOLUTION_REQUIRED") {
            return { ...claim, truth_status: localResolution.truth_status };
          }
          if (localResolution.truth_status === "BLOCK" && claim.truth_status === "PASS") {
            return { ...claim, truth_status: "BLOCK" as const };
          }
        }
        return claim;
      });

      latestAppliedRequestIdRef.current = requestId;
      setOverview({ ...serverOverview, claims: mergedClaims });
      setError("");
    } catch (caught) {
      if (!isMountedRef.current) return;
      if (activeUserIdRef.current !== currentUserId) return;
      if (abortController.signal.aborted || requestId < latestRequestIdRef.current) {
        return;
      }
      if (caught instanceof DOMException && caught.name === "AbortError") {
        return;
      }
      if (caught && typeof caught === "object" && "name" in caught && (caught as { name: string }).name === "AbortError") {
        return;
      }
      setError(caught instanceof Error ? caught.message : "Не удалось загрузить данные карьеры.");
    } finally {
      if (activeAbortControllerRef.current === abortController) {
        activeAbortControllerRef.current = null;
      }
    }
  }, []);

  const handleClaimMutating = useCallback((isMutating: boolean) => {
    if (isMutating) {
      activeMutationsRef.current += 1;
      if (activeAbortControllerRef.current) {
        activeAbortControllerRef.current.abort();
        activeAbortControllerRef.current = null;
      }
    } else {
      activeMutationsRef.current = Math.max(0, activeMutationsRef.current - 1);
    }
  }, []);

  const handleClaimResolved = useCallback((claimId: string, resolved: Pick<Claim, "truth_status">, mutationUserId?: string) => {
    if (mutationUserId !== undefined && mutationUserId !== activeUserIdRef.current) {
      return;
    }
    mutationEpochRef.current += 1;
    if (activeAbortControllerRef.current) {
      activeAbortControllerRef.current.abort();
      activeAbortControllerRef.current = null;
    }
    resolvedClaimsRef.current[claimId] = {
      truth_status: resolved.truth_status,
      epoch: mutationEpochRef.current,
    };

    setOverview((current) => {
      if (!current || !current.claims.some((c) => c.id === claimId)) return current;
      return {
        ...current,
        claims: current.claims.map((item) =>
          item.id === claimId ? { ...item, truth_status: resolved.truth_status } : item
        ),
      };
    });
  }, []);

  useEffect(() => {
    isMountedRef.current = true;
    return () => {
      isMountedRef.current = false;
      if (activeAbortControllerRef.current) {
        activeAbortControllerRef.current.abort();
        activeAbortControllerRef.current = null;
      }
    };
  }, []);

  useEffect(() => {
    if (activeUserIdRef.current !== userId) {
      activeUserIdRef.current = userId;
      if (activeAbortControllerRef.current) {
        activeAbortControllerRef.current.abort();
        activeAbortControllerRef.current = null;
      }
      latestRequestIdRef.current += 1;
      latestAppliedRequestIdRef.current = latestRequestIdRef.current;
      mutationEpochRef.current += 1;
      resolvedClaimsRef.current = {};
      setOverview(null);
      setError("");
      setEdits({});
      void refresh({ force: true });
    }
  }, [userId, refresh]);

  useEffect(() => {
    // Initial owner-scoped API hydration is the external synchronization performed by this effect.
    void refresh({ force: true });
  }, [refresh]);

  useEffect(() => {
    if (!overview?.sources.some((source) => source.extraction_status === "PENDING" || source.extraction_status === "RUNNING")) return;
    const timer = window.setInterval(() => {
      if (activeMutationsRef.current === 0) {
        void refresh();
      }
    }, 1500);
    return () => window.clearInterval(timer);
  }, [overview?.sources, refresh]);

  async function submit(event: FormEvent<HTMLFormElement>, path: string, operation: string) {
    event.preventDefault();
    const form = event.currentTarget;
    setBusy(operation);
    setError("");

    activeMutationsRef.current += 1;
    if (activeAbortControllerRef.current) {
      activeAbortControllerRef.current.abort();
      activeAbortControllerRef.current = null;
    }

    try {
      await api(path, { method: "POST", body: JSON.stringify(Object.fromEntries(new FormData(form))) });
      if (!isMountedRef.current) return;
      form.reset();
      mutationEpochRef.current += 1;
      await refresh({ force: true });
    } catch (caught) {
      if (!isMountedRef.current) return;
      if (caught instanceof DOMException && caught.name === "AbortError") return;
      setError(caught instanceof Error ? caught.message : "Не удалось выполнить запрос.");
    } finally {
      activeMutationsRef.current = Math.max(0, activeMutationsRef.current - 1);
      if (isMountedRef.current) {
        setBusy("");
      }
    }
  }

  async function review(fact: Fact, action: "confirm" | "edit_confirm" | "reject") {
    setBusy(fact.id + action);
    setError("");

    activeMutationsRef.current += 1;
    if (activeAbortControllerRef.current) {
      activeAbortControllerRef.current.abort();
      activeAbortControllerRef.current = null;
    }

    try {
      await api(`/api/v1/career/facts/${fact.id}/review`, {
        method: "PATCH",
        body: JSON.stringify({ action, ...(action === "edit_confirm" ? { assertion: edits[fact.id] ?? fact.assertion_original } : {}) }),
      });
      if (!isMountedRef.current) return;
      mutationEpochRef.current += 1;
      await refresh({ force: true });
    } catch (caught) {
      if (!isMountedRef.current) return;
      if (caught instanceof DOMException && caught.name === "AbortError") return;
      setError(caught instanceof Error ? caught.message : "Не удалось проверить факт.");
    } finally {
      activeMutationsRef.current = Math.max(0, activeMutationsRef.current - 1);
      if (isMountedRef.current) {
        setBusy("");
      }
    }
  }

  const pending = overview?.facts.filter((fact) => fact.status === "PENDING") ?? [];
  const confirmed = overview?.facts.filter((fact) => fact.status === "CONFIRMED") ?? [];

  return <main id="workspace-content" tabIndex={-1} className="career-shell">
    <header className="career-header"><div><p className="eyebrow">Карьера</p><h1>Факты вашей карьеры</h1><p className="tagline">Сохраняйте опыт, проверяйте источники и подтверждайте факты.</p></div></header>
    {error && <div className="alert error" role="alert"><strong>Ошибка операции</strong><span>{error}</span><button type="button" className="secondary" onClick={() => void refresh({ force: true })}>Повторить</button></div>}
    <section className="career-grid" aria-busy={!overview}>
      <div className="panel"><h2>Извлечь факты из текста</h2><p className="muted">Факты, извлечённые из текста, требуют вашего подтверждения.</p><form onSubmit={(event) => void submit(event, "/api/v1/career/extractions", "extract")}><label>Текст об опыте или резюме<textarea name="source_text" required maxLength={30000} rows={9} /></label><button disabled={busy !== ""}>{busy === "extract" ? "Извлечение…" : "Извлечь факты для проверки"}</button></form>{overview?.sources[0] && <p className={`source-status ${overview.sources[0].extraction_status.toLowerCase()}`} role="status">Последнее извлечение: {extractionLabels[overview.sources[0].extraction_status]}{overview.sources[0].error_code ? ` — ${overview.sources[0].error_code}` : ""}</p>}</div>
      <div className="panel"><h2>Добавить факт вручную</h2><p className="muted">Добавляя факт вручную, вы подтверждаете его достоверность. ИИ не используется.</p><form onSubmit={(event) => void submit(event, "/api/v1/career/facts/manual", "manual")}><label>Тип факта<input name="fact_type" required pattern="[a-z][a-z0-9_]{1,63}" placeholder="skill" /></label><label>Подтверждённое утверждение<textarea name="assertion" required maxLength={1000} rows={4} /></label><button disabled={busy !== ""}>{busy === "manual" ? "Сохранение…" : "Добавить подтверждённый факт"}</button></form></div>
    </section>
    {!overview && <p className="loading" role="status">Загрузка данных карьеры…</p>}
    {overview && <>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Требуется ваша проверка</p><h2>Факты на проверке</h2></div><span className="count">{pending.length}</span></div>{pending.length === 0 ? <p className="empty">Нет фактов на проверке. Вставьте текст выше или добавьте факт вручную.</p> : <div className="fact-list">{pending.map((fact) => <article className="fact-card pending-card" key={fact.id}><div className="fact-meta"><span className="badge pending-badge">Ожидает подтверждения</span><span>{fact.fact_type}</span></div><p className="assertion">{fact.assertion_original}</p><details open><summary>Источник факта</summary><blockquote>{fact.source_excerpt}</blockquote><p className="muted">Происхождение: {provenanceLabels[fact.provenance_type]}</p></details><label>Изменить перед подтверждением<textarea rows={3} value={edits[fact.id] ?? fact.assertion_original} onChange={(event) => setEdits((current) => ({ ...current, [fact.id]: event.target.value }))} /></label><div className="actions"><button disabled={busy !== ""} onClick={() => void review(fact, "confirm")}>Подтвердить</button><button className="secondary" disabled={busy !== ""} onClick={() => void review(fact, "edit_confirm")}>Изменить и подтвердить</button><button className="danger" disabled={busy !== ""} onClick={() => void review(fact, "reject")}>Отклонить</button></div></article>)}</div>}</section>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Подтверждено вами</p><h2>Подтверждённые факты</h2></div><span className="count confirmed-count">{confirmed.length}</span></div>{confirmed.length === 0 ? <p className="empty">Подтверждённых фактов пока нет.</p> : <div className="fact-list">{confirmed.map((fact) => <article className="fact-card confirmed-card" key={fact.id}><div className="fact-meta"><span className="badge confirmed-badge">Подтверждён</span><span>{fact.fact_type}</span></div><p className="assertion">{fact.assertion_approved ?? fact.assertion_original}</p><details><summary>Источник и происхождение</summary><blockquote>{fact.source_excerpt}</blockquote><p className="muted">{provenanceLabels[fact.provenance_type]}</p></details></article>)}</div>}</section>
      <section className="review-section"><div className="section-title"><div><p className="eyebrow">Truth Guard</p><h2>Утверждения</h2></div></div>{overview.claims.length === 0 ? <p className="empty">Утверждения появляются только после вашего подтверждения.</p> : <ul className="claim-list">{overview.claims.map((claim) => <CareerClaim key={claim.id} claim={claim} facts={overview.facts} onResolved={(resolved) => handleClaimResolved(claim.id, resolved, userId)} onRefresh={() => refresh({ force: true })} onMutating={handleClaimMutating} />)}</ul>}</section>
      <VacancyWorkspace />
    </>}
  </main>;
}

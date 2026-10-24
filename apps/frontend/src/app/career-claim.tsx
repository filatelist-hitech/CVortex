"use client";

import { type FormEvent, useEffect, useMemo, useRef, useState } from "react";
import { api, ApiError } from "./access-shell";

export type Fact = {
  id: string;
  fact_type: string;
  assertion_original: string;
  assertion_approved: string | null;
  source_excerpt: string;
  provenance_type: "paste_extraction" | "user_manual";
  status: "PENDING" | "CONFIRMED" | "REJECTED" | "DEPRECATED";
  supersedes_fact_id?: string | null;
};
export type Claim = {
  id: string;
  statement: string;
  truth_status: "PASS" | "BLOCK" | "USER_RESOLUTION_REQUIRED";
  fact_ids: string[];
};

const truthLabels = { PASS: "Подтверждено фактами", BLOCK: "Недостаточно подтверждения", USER_RESOLUTION_REQUIRED: "Нужен выбор источника" };
const factLabels: Record<string, string> = {
  skill: "Навык", experience: "Опыт", achievement: "Достижение", education: "Образование",
  language: "Язык", certification: "Сертификат", employment_period: "Период работы",
  seniority: "Уровень", leadership: "Руководство", responsibility: "Обязанность", technology_depth: "Глубина владения технологией",
};

function resolutionError(caught: unknown): string {
  if (caught instanceof ApiError) {
    if (caught.code === "AUTH_REQUIRED") return "Сессия завершена. Войдите снова и повторите выбор источника.";
    if (caught.code === "VALIDATION_FAILED") return "Сервер отклонил выбор. Состояние утверждения или факта могло измениться. Обновите варианты и проверьте выбранный факт.";
    if (caught.code === "PERMISSION_DENIED" || caught.code === "NOT_FOUND" || caught.code === "RESOURCE_NOT_FOUND") return "Утверждение или факт больше недоступны. Обновите варианты перед повтором.";
    if (caught.code === "NETWORK_ERROR") return "Не удалось подключиться. Выбор сохранён. Проверьте сеть и повторите подтверждение.";
  }
  return "Не удалось подтвердить источник. Выбор сохранён. Обновите варианты или повторите подтверждение позже.";
}

export default function CareerClaim({ claim, facts, onResolved, onRefresh, onMutating }: {
  claim: Claim;
  facts: Fact[];
  onResolved: (resolved: Pick<Claim, "truth_status">) => void;
  onRefresh: () => Promise<void>;
  onMutating?: (busy: boolean) => void;
}) {
  const [selected, setSelected] = useState("");
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState(false);
  const inFlight = useRef(false);
  const isMounted = useRef(true);
  const announcement = useRef<HTMLParagraphElement>(null);

  useEffect(() => {
    isMounted.current = true;
    return () => {
      isMounted.current = false;
    };
  }, []);

  const eligible = useMemo(() => facts.filter((fact) => claim.fact_ids?.includes(fact.id)
    && fact.status === "CONFIRMED"
    && (fact.assertion_approved ?? fact.assertion_original) === claim.statement
    && fact.source_excerpt.trim() !== ""
    && !facts.some((replacement) => replacement.status === "CONFIRMED" && replacement.supersedes_fact_id === fact.id)), [facts, claim]);
  const selectionAvailable = eligible.some((fact) => fact.id === selected);

  async function resolve(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (inFlight.current || !selectionAvailable || claim.truth_status !== "USER_RESOLUTION_REQUIRED") return;
    const initialClaimId = claim.id;
    inFlight.current = true;
    setBusy(true);
    onMutating?.(true);
    setError("");
    setSuccess(false);
    try {
      const result = await api(`/api/v1/career/claims/${claim.id}/resolve`, {
        method: "PATCH", body: JSON.stringify({ career_fact_id: selected }),
      });
      const status = result?.data?.truth_status;
      if (result?.data?.id !== claim.id || !["PASS", "BLOCK", "USER_RESOLUTION_REQUIRED"].includes(status)) {
        throw new Error("Invalid resolution response");
      }
      if (!isMounted.current || claim.id !== initialClaimId) return;
      onResolved({ truth_status: status });
      if (status === "PASS") setSuccess(true);
      else setError("Источник выбран, но проверка утверждения не пройдена. Обновите варианты и проверьте факты.");
    } catch (caught) {
      if (!isMounted.current || claim.id !== initialClaimId) return;
      setError(resolutionError(caught));
    } finally {
      inFlight.current = false;
      onMutating?.(false);
      if (isMounted.current) {
        setBusy(false);
        // The form can disappear after a successful mutation; retain focus on its result.
        requestAnimationFrame(() => announcement.current?.focus());
      }
    }
  }

  return <li className="career-claim">
    <div className="fact-meta"><span className={`badge ${claim.truth_status === "PASS" ? "confirmed-badge" : "blocked-badge"}`}>{truthLabels[claim.truth_status]}</span></div>
    <p className="assertion">{claim.statement}</p>
    <p ref={announcement} tabIndex={-1} role={error ? "alert" : "status"} className={error ? "error" : "muted"}>
      {error || (success && claim.truth_status === "PASS" ? "Источник утверждения подтверждён. Для зависимых материалов повторите анализ вакансии и проверку текста. Одобрение текста остаётся отдельным действием." : "")}
    </p>
    {claim.truth_status === "USER_RESOLUTION_REQUIRED" && <details>
      <summary>Выбрать источник утверждения</summary>
      <p className="muted" id={`resolution-help-${claim.id}`}>Утверждение связано с подтверждёнными фактами разных типов. Выберите факт, на который хотите опираться. Подтверждение выбора не создаёт новых фактов.</p>
      <form onSubmit={(event) => void resolve(event)} aria-busy={busy} aria-describedby={`resolution-help-${claim.id}`}>
        <fieldset disabled={busy} className="claim-evidence">
          <legend>Подтверждённые источники</legend>
          {eligible.map((fact) => <label className="claim-evidence-choice" key={fact.id}>
            <input type="radio" name={`evidence-${claim.id}`} value={fact.id} checked={selected === fact.id} onChange={() => setSelected(fact.id)} />
            <span><strong>{fact.assertion_approved ?? fact.assertion_original}</strong>
              <span className="muted">Тип: {factLabels[fact.fact_type] ?? fact.fact_type}</span>
              <span className="muted">Источник: {fact.provenance_type === "user_manual" ? "Добавлено вручную" : "Из текста"}</span>
              <span className="claim-source">{fact.source_excerpt}</span>
            </span>
          </label>)}
        </fieldset>
        {eligible.length === 0 && <p className="muted">Нет доступных подтверждённых источников. Обновите варианты и проверьте факты карьеры.</p>}
        {selected && !selectionAvailable && <p className="error" role="alert">Выбранный факт больше недоступен. Выберите другой подтверждённый источник.</p>}
        <div className="actions">
          <button disabled={busy || !selectionAvailable}>{busy ? "Подтверждение источника…" : "Подтвердить источник утверждения"}</button>
          <button type="button" className="secondary" disabled={busy} onClick={() => void onRefresh()}>Обновить варианты</button>
        </div>
      </form>
    </details>}
  </li>;
}

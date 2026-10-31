"use client";

import { useId } from "react";

export interface MarginaliaFact {
  id: string;
  statement: string;
  status: "CONFIRMED" | "PENDING" | "REJECTED" | string;
  factType?: string;
  sourceExcerpt?: string;
  company?: string;
  year?: string | number;
  linkedReqId?: string;
  provenanceType?: string;
}

export function MarginaliaCard({
  fact,
  isHighlighted = false,
  onHover,
  onClick,
}: {
  fact: MarginaliaFact;
  isHighlighted?: boolean;
  onHover?: (active: boolean) => void;
  onClick?: () => void;
}) {
  const cardId = useId();
  const isConfirmed = fact.status === "CONFIRMED";
  const isPending = fact.status === "PENDING";

  return (
    <aside
      id={cardId}
      className={`marginalia-card ${isHighlighted ? "is-highlighted" : ""} ${isConfirmed ? "confirmed" : isPending ? "pending" : ""}`}
      data-fact-id={fact.id}
      data-linked-req={fact.linkedReqId}
      onMouseEnter={() => onHover?.(true)}
      onMouseLeave={() => onHover?.(false)}
      onClick={onClick}
      tabIndex={0}
      role="note"
      aria-label={`Маргиналия: факт ${fact.id}`}
    >
      <div className="marginalia-connector" aria-hidden="true">
        <span className="marginalia-rule" />
        <span className="marginalia-arrow">→</span>
      </div>
      <div className="marginalia-body">
        <div className="marginalia-meta">
          <span className="marginalia-tag font-mono">
            [Факт #{fact.id.replace(/^cf-|^fact-/, "")}]
          </span>
          <span
            className={`marginalia-status font-mono ${
              isConfirmed ? "status-truth" : isPending ? "status-pending" : "status-neutral"
            }`}
          >
            {isConfirmed ? "[Подтверждено]" : isPending ? "[На проверке]" : `[${fact.status}]`}
          </span>
        </div>
        {fact.company && (
          <div className="marginalia-context font-mono">
            {fact.company} {fact.year ? `(${fact.year})` : ""}
          </div>
        )}
        <p className="marginalia-statement">«{fact.statement}»</p>
        {fact.sourceExcerpt && (
          <blockquote className="marginalia-quote">
            {fact.sourceExcerpt}
          </blockquote>
        )}
      </div>
    </aside>
  );
}

export function MarginaliaColumn({
  facts,
  activeFactId,
  activeReqId,
  onHoverFact,
  onSelectFact,
}: {
  facts: MarginaliaFact[];
  activeFactId?: string | null;
  activeReqId?: string | null;
  onHoverFact?: (id: string | null) => void;
  onSelectFact?: (id: string) => void;
}) {
  if (!facts.length) {
    return (
      <div className="marginalia-column empty">
        <span className="marginalia-empty-note font-mono">
          Маргиналии фактов карьеры появятся здесь после подтверждения данных
        </span>
      </div>
    );
  }

  return (
    <div className="marginalia-column" aria-label="Маргиналии подтверждённых фактов">
      <div className="marginalia-column-header">
        <span className="font-mono text-micro">МАРГИНАЛИИ ПРАВДЫ</span>
        <span className="marginalia-count font-mono">{facts.length}</span>
      </div>
      <div className="marginalia-cards-list">
        {facts.map((fact) => {
          const isHighlighted =
            activeFactId === fact.id || (Boolean(activeReqId) && fact.linkedReqId === activeReqId);
          return (
            <MarginaliaCard
              key={fact.id}
              fact={fact}
              isHighlighted={isHighlighted}
              onHover={(active) => onHoverFact?.(active ? fact.id : null)}
              onClick={() => onSelectFact?.(fact.id)}
            />
          );
        })}
      </div>
    </div>
  );
}

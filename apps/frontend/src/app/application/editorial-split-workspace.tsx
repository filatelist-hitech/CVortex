"use client";

import { useId, useState, useCallback, useMemo, useRef, useEffect } from "react";
import "./editorial-split.css";
import { useWorkspace } from "./use-workspace";
import { useProvenanceLinker } from "./use-provenance-linker";
import { MarginaliaColumn, type MarginaliaFact } from "./marginalia";
import { VortexSwissLogo } from "../vortex-logo";
import { RecommendationCard } from "./recommendation";
import { ApplicationDraftPanel } from "../vacancy-workspace";

export function EditorialSplitWorkspace() {
  const w = useWorkspace();
  const [splitRatio, setSplitRatio] = useState(45); // 45% / 55%
  const [isResizing, setIsResizing] = useState(false);
  const separatorId = useId();
  const dragRef = useRef<{ startX: number; startRatio: number; containerWidth: number } | null>(null);

  // Compute match score percentage
  const matchPercentage = useMemo<number | null>(() => {
    if (!w.detail?.analysis?.dimensions?.length) return null;
    const matches = w.detail.analysis.dimensions.filter(
      (d) => d.result === "MATCH"
    ).length;
    const adjacents = w.detail.analysis.dimensions.filter(
      (d) => d.result === "ADJACENT"
    ).length;
    const total = w.detail.analysis.dimensions.length;
    return Math.round(((matches + adjacents * 0.5) / total) * 100);
  }, [w.detail]);

  // Format salary in mono
  const salaryDisplay = useMemo(() => {
    if (w.item?.salary) {
      return `${w.item.salary.min / 1000}k–${w.item.salary.max / 1000}k ₽`;
    }
    return "Зарплата не указана";
  }, [w.item]);

  // Derive marginalia facts from detail or demo
  const marginaliaFacts: MarginaliaFact[] = useMemo(() => {
    if (w.demo) {
      return [
        {
          id: "fact-42",
          statement: "Снизил latency транзакций на 40% за счёт шардирования PostgreSQL",
          status: "CONFIRMED",
          company: "FinTech Corp",
          year: 2023,
          linkedReqId: "req-1",
          sourceExcerpt: "Проектирование высоконагруженных API и оптимизация кэширования",
        },
        {
          id: "fact-18",
          statement: "Разработал отказоустойчивое API на Python и pytest",
          status: "CONFIRMED",
          company: "CloudLab",
          year: 2024,
          linkedReqId: "req-1",
          sourceExcerpt: "Регрессионные тесты API и транзакции баз данных",
        },
        {
          id: "fact-31",
          statement: "Оркестрация и развёртывание сервисов в Kubernetes",
          status: "PENDING",
          company: "DataScale",
          year: 2025,
          linkedReqId: "req-2",
          sourceExcerpt: "Опыт работы с Helm-чартами в staging",
        },
      ];
    }

    if (!w.detail?.analysis?.dimensions) return [];

    const facts: MarginaliaFact[] = [];
    const seen = new Set<string>();
    w.detail.analysis.dimensions.forEach((dim) => {
      dim.candidate_evidence?.forEach((ev) => {
        const reqId = dim.vacancy_requirement_ids?.[0];
        const key = `${ev.id}-${reqId ?? ""}`;
        if (!seen.has(key)) {
          seen.add(key);
          facts.push({
            id: ev.id,
            statement: ev.statement,
            status: ev.status,
            linkedReqId: reqId,
            provenanceType: ev.provenance_type,
            company: (ev as { company?: string }).company ?? undefined,
          });
        }
      });
    });
    return facts;
  }, [w.demo, w.detail]);

  const linker = useProvenanceLinker(w.detail, marginaliaFacts);

  // Column resizer with lifecycle cleanup
  const handleMouseDown = useCallback((e: React.MouseEvent) => {
    e.preventDefault();
    const container = e.currentTarget.parentElement;
    const containerWidth = container ? container.clientWidth : window.innerWidth;
    dragRef.current = {
      startX: e.clientX,
      startRatio: splitRatio,
      containerWidth: containerWidth || window.innerWidth,
    };
    setIsResizing(true);
  }, [splitRatio]);

  useEffect(() => {
    if (!isResizing) return;

    function handleMouseMove(moveEvent: MouseEvent) {
      if (!dragRef.current) return;
      const deltaX = moveEvent.clientX - dragRef.current.startX;
      const deltaRatio = (deltaX / dragRef.current.containerWidth) * 100;
      const nextRatio = Math.min(75, Math.max(25, dragRef.current.startRatio + deltaRatio));
      setSplitRatio(Math.round(nextRatio));
    }

    function handleMouseUp() {
      setIsResizing(false);
      dragRef.current = null;
    }

    window.addEventListener("mousemove", handleMouseMove);
    window.addEventListener("mouseup", handleMouseUp);
    return () => {
      window.removeEventListener("mousemove", handleMouseMove);
      window.removeEventListener("mouseup", handleMouseUp);
    };
  }, [isResizing]);

  const roleTitle = w.item?.role || w.detail?.title || (w.demo ? "Senior Staff Product Engineer" : "Вакансия не выбрана");
  const companyTitle = w.item?.company || w.detail?.company || (w.demo ? "Stripe" : "");

  const handleCtaClick = useCallback(() => {
    if (w.demo) {
      w.approve();
    } else {
      const draftPanel = document.querySelector(".application-draft-panel, [data-testid='application-draft-panel']");
      if (draftPanel) {
        draftPanel.scrollIntoView({ behavior: "smooth", block: "start" });
      }
    }
  }, [w]);

  const isCtaDisabled = w.demo
    ? (!w.safe || !w.accepted || w.approved || w.rejected)
    : (!w.detail || w.loading);

  return (
    <div
      className={`editorial-split-workspace ${isResizing ? "resizing" : ""}`}
      data-testid="editorial-split-workspace"
    >
      {/* Floating Header */}
      <header className="editorial-floating-header">
        <div className="header-brand-group">
          <div className="header-logo">
            <VortexSwissLogo />
          </div>
          <div className="header-title-block">
            <h1 className="header-role-title font-display">
              {roleTitle}
            </h1>
            {companyTitle && (
              <span className="header-company font-mono">
                · {companyTitle}
              </span>
            )}
          </div>
        </div>

        <div className="header-telemetry-group">
          <span
            className="telemetry-pill match-pill font-mono"
            aria-label={matchPercentage !== null ? `Матчинг вакансии ${matchPercentage}%` : "Матчинг не рассчитан"}
          >
            [ MATCH {matchPercentage !== null ? `${matchPercentage}%` : "—"} ]
          </span>
          <span className="telemetry-pill salary-pill font-mono">
            [ {salaryDisplay} ]
          </span>
          <button
            type="button"
            className="btn-coral-punch font-mono"
            disabled={isCtaDisabled}
            onClick={handleCtaClick}
            aria-label="Одобрить отклик"
          >
            {w.approved ? "✓ Одобрено" : "( Одобрить отклик )"}
          </button>
        </div>
      </header>

      {/* Two Column Split Container */}
      <div className="editorial-split-columns" style={{ "--split-left": `${splitRatio}%` } as React.CSSProperties}>
        {/* Left Column: Requirements Anatomy */}
        <section
          className="split-column column-requirements"
          aria-label="Анатомия требований вакансии"
        >
          <div className="column-pane-header">
            <span className="eyebrow font-mono">ВАКАНСИЯ: АНАТОМИЯ ТРЕБОВАНИЙ</span>
            <span className="badge-count font-mono">
              {w.detail?.requirements?.length ?? (w.demo ? 2 : 0)} ТРЕБ.
            </span>
          </div>

          <div className="requirements-editorial-stream">
            {w.demo ? (
              <>
                <article
                  className={`editorial-req-card ${
                    linker.isRequirementHighlighted("req-1") ? "is-linked-highlight" : ""
                  }`}
                  data-req-id="req-1"
                  onMouseEnter={() => linker.hoverRequirement("req-1")}
                  onMouseLeave={() => linker.hoverRequirement(null)}
                  tabIndex={0}
                >
                  <div className="req-card-top">
                    <span className="req-marker-icon font-mono">[ ! ]</span>
                    <h3 className="req-title font-display">
                      Архитектура распределённых систем
                    </h3>
                    <span className="req-status-badge badge-match font-mono">
                      [MANDATORY · MATCH]
                    </span>
                  </div>
                  <blockquote className="req-source-quote font-body">
                    «Проектирование высоконагруженных API и кэширования транзакций...»
                  </blockquote>
                  <div className="req-footer-meta">
                    <span className="meta-evidence-count font-mono status-truth">
                      ● Подтверждено 2 фактами
                    </span>
                  </div>
                </article>

                <article
                  className={`editorial-req-card ${
                    linker.isRequirementHighlighted("req-2") ? "is-linked-highlight" : ""
                  }`}
                  data-req-id="req-2"
                  onMouseEnter={() => linker.hoverRequirement("req-2")}
                  onMouseLeave={() => linker.hoverRequirement(null)}
                  tabIndex={0}
                >
                  <div className="req-card-top">
                    <span className="req-marker-icon font-mono">[ ? ]</span>
                    <h3 className="req-title font-display">
                      Опыт с Kubernetes в проде
                    </h3>
                    <span className="req-status-badge badge-gap font-mono">
                      [PREFERRED · GAP]
                    </span>
                  </div>
                  <blockquote className="req-source-quote font-body">
                    «Опыт оркестрации и написания helm-чартов для микросервисов...»
                  </blockquote>
                  <div className="req-footer-meta">
                    <span className="meta-evidence-count font-mono status-warning">
                      ○ В базе подтверждений нет прямых фактов
                    </span>
                  </div>
                </article>
              </>
            ) : w.detail?.requirements?.length ? (
              w.detail.requirements.map((req) => {
                const linkedFacts = linker.getLinkedFactIds(req.id);
                const hasEvidence = linkedFacts.length > 0;
                const isHighlighted = linker.isRequirementHighlighted(req.id);

                return (
                  <article
                    key={req.id}
                    className={`editorial-req-card ${isHighlighted ? "is-linked-highlight" : ""}`}
                    data-req-id={req.id}
                    onMouseEnter={() => linker.hoverRequirement(req.id)}
                    onMouseLeave={() => linker.hoverRequirement(null)}
                    tabIndex={0}
                  >
                    <div className="req-card-top">
                      <span className="req-marker-icon font-mono">
                        {req.importance === "MANDATORY" ? "[ ! ]" : "[ · ]"}
                      </span>
                      <h3 className="req-title font-display">{req.label}</h3>
                      <span
                        className={`req-status-badge font-mono ${
                          hasEvidence ? "badge-match" : "badge-gap"
                        }`}
                      >
                        [{req.importance} · {hasEvidence ? "MATCH" : "GAP"}]
                      </span>
                    </div>
                    {req.source_excerpt && (
                      <blockquote className="req-source-quote font-body">
                        «{req.source_excerpt}»
                      </blockquote>
                    )}
                    <div className="req-footer-meta">
                      <span
                        className={`meta-evidence-count font-mono ${
                          hasEvidence ? "status-truth" : "status-warning"
                        }`}
                      >
                        {hasEvidence
                          ? `● Подтверждено ${linkedFacts.length} фактами`
                          : "○ В базе подтверждений нет прямых фактов"}
                      </span>
                    </div>
                  </article>
                );
              })
            ) : (
              <p className="empty-editorial font-mono">
                Требования вакансии извлекаются…
              </p>
            )}
          </div>
        </section>

        {/* Resizable Divider */}
        <div
          id={separatorId}
          role="separator"
          aria-valuenow={splitRatio}
          aria-valuemin={25}
          aria-valuemax={75}
          aria-label="Изменить ширину колонок редактора"
          tabIndex={0}
          className="editorial-split-divider"
          onMouseDown={handleMouseDown}
          onKeyDown={(e) => {
            if (e.key === "ArrowLeft") {
              setSplitRatio((r) => Math.max(25, r - 5));
            } else if (e.key === "ArrowRight") {
              setSplitRatio((r) => Math.min(75, r + 5));
            }
          }}
        >
          <span className="divider-grip" aria-hidden="true" />
        </div>

        {/* Right Column: Materials & Marginalia */}
        <section
          className="split-column column-materials-marginalia"
          aria-label="Материалы отклика и маргиналии"
        >
          <div className="column-pane-header">
            <span className="eyebrow font-mono">МАТЕРИАЛЫ ОТКЛИКА & МАРГИНАЛИИ</span>
            <span className="badge-count font-mono">РЕДАКТОР</span>
          </div>

          <div className="materials-editorial-container">
            {/* Main Document Body */}
            <div className="document-sheet">
              {w.demo ? (
                <>
                  <div className="document-header">
                    <h2 className="doc-section-title font-display">
                      ### Опыт работы: Senior Platform Architect
                    </h2>
                    <span className="doc-meta font-mono">Версия черновика R-07</span>
                  </div>

                  <div
                    className={`document-paragraph ${
                      linker.isProvenanceHighlighted("req-1") ? "provenance-active-highlight" : ""
                    }`}
                    data-provenance="req-1"
                    onMouseEnter={() => linker.hoverProvenance("req-1")}
                    onMouseLeave={() => linker.hoverProvenance(null)}
                  >
                    <p className="font-body">
                      Руководил переходом на событийно-ориентированную архитектуру
                      микросервисов. Спроектировал распределённое отказоустойчивое API
                      на Go и Python, автоматизировал тестирование интеграционных сценариев
                      с базами данных PostgreSQL.
                    </p>
                  </div>

                  <div
                    className={`document-paragraph ${
                      linker.isProvenanceHighlighted("req-2") ? "provenance-active-highlight" : ""
                    }`}
                    data-provenance="req-2"
                    onMouseEnter={() => linker.hoverProvenance("req-2")}
                    onMouseLeave={() => linker.hoverProvenance(null)}
                  >
                    <p className="font-body">
                      Разработал инфраструктурные скрипты и конфигурации для развёртывания
                      контейнеризированных сервисов в тестовых средах.
                    </p>
                  </div>

                  <RecommendationCard />
                </>
              ) : w.detail ? (
                <ApplicationDraftPanel
                  canonical
                  key={w.detail.id}
                  vacancyId={w.detail.id}
                  vacancyTitle={w.detail.title ?? "Вакансия"}
                />
              ) : (
                <div className="empty-editorial font-mono">
                  Выберите вакансию для редактирования материалов отклика
                </div>
              )}
            </div>

            {/* Marginalia Sidebar */}
            <MarginaliaColumn
              facts={marginaliaFacts}
              activeFactId={linker.activeLink.factId}
              activeReqId={linker.activeLink.requirementId}
              onHoverFact={linker.hoverFact}
              onSelectFact={(factId) => linker.scrollToLinked(factId, "fact")}
            />
          </div>
        </section>
      </div>
    </div>
  );
}

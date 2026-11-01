"use client";

import { useEffect } from "react";
import { WorkspaceProvider, useWorkspace } from "./use-workspace";
import { IconSprite } from "./icons";
import { scenarioLabels, scenarios, type Scenario } from "./model";
import { VacancyList } from "./vacancy-list";
import { VacancyHeader } from "./vacancy-header";
import { WorkspaceTabs, OverviewPanel, RequirementsPanel, MaterialsPanel, EmployerPanel, HistoryPanel } from "./panels";
import { EditorialSplitWorkspace } from "./editorial-split-workspace";
import { EvidencePanel } from "./evidence-panel";
import { NextAction } from "./recommendation";
import { useWorkspaceMotion } from "./motion";
import { GlobalHeader } from "../components/navigation/global-header";
import { GlobalSidebar as CanonicalSidebar } from "../components/navigation/global-sidebar";
import { UserMenu } from "../components/navigation/user-menu";
import "./tokens.css";
import "./application.css";

type Identity = { email: string; role: string; onSignOut: () => Promise<void> };

export function AccountMenu({ email, role, onSignOut }: Identity) {
  const w = useWorkspace();
  return (
    <UserMenu
      email={email}
      role={role}
      onSignOut={onSignOut}
      currentDestination="vacancies"
      allowNavigation={w.allowNavigation}
    />
  );
}

export function WorkspaceHeader(props: Identity) {
  const w = useWorkspace();

  const scenarioSelector = w.demo ? (
    <label className="scenario-picker type-caption">
      Состояние макета{" "}
      <select
        id="scenario-select"
        aria-label="Состояние макета"
        value={w.location.scenario}
        onChange={(event) => w.setScenario(event.target.value as Scenario)}
      >
        {scenarios.map((scenario, i) => (
          <option key={scenario} value={scenario}>
            {scenarioLabels[i]}
          </option>
        ))}
      </select>
    </label>
  ) : (
    <span className="type-caption header-badge-caption">Ваши вакансии и материалы</span>
  );

  return (
    <GlobalHeader
      destination="vacancies"
      email={props.email}
      role={props.role}
      onSignOut={props.onSignOut}
      allowNavigation={w.allowNavigation}
      demo={Boolean(w.demo)}
      scenarioSelector={scenarioSelector}
    />
  );
}

export function GlobalSidebar() {
  const w = useWorkspace();

  return (
    <div className="compact-navigation">
      <CanonicalSidebar
        destination="vacancies"
        currentLabel="Вакансии"
        allowNavigation={w.allowNavigation}
      />
      <div className="tablet-bar">
        <button
          id="open-list"
          type="button"
          aria-controls="opportunity-list"
          aria-expanded={w.listOpen}
          onClick={w.openList}
        >
          Выбрать вакансию
        </button>
      </div>
      <div className="mobile-bar" aria-label="Раздел макета">
        <button
          id="back-level"
          type="button"
          hidden={w.location.level === "list"}
          onClick={() => (w.location.level === "context" ? w.closeEvidence() : w.setLevel("list"))}
        >
          ← Назад
        </button>
        <strong className="type-nav" id="level-title">
          {w.location.level === "list"
            ? "Вакансии"
            : w.location.level === "work"
            ? w.item?.company
            : "Подтверждения"}
        </strong>
        <span>{w.demo ? "Локальный макет" : "Рабочее пространство"}</span>
      </div>
    </div>
  );
}

export function VacancyWorkspace() {
  const w = useWorkspace();
  return (
    <main className="workspace" id="workspace">
      <VacancyHeader />
      <NextAction />
      {!w.demo && w.status && <p className="action-status type-secondary" role="alert">{w.status}</p>}
      <WorkspaceTabs />
      {w.location.section === "split" ? (
        <EditorialSplitWorkspace />
      ) : (
        <>
          <OverviewPanel />
          <RequirementsPanel />
          <MaterialsPanel />
          <EmployerPanel />
          <HistoryPanel />
        </>
      )}
    </main>
  );
}

function AppShell(props: Identity) {
  const { root: rootRef, ...w } = useWorkspace();
  const motion = useWorkspaceMotion();

  useEffect(() => {
    const panel = rootRef.current?.querySelector<HTMLElement>("#inspector");
    if (!panel) return;
    if (w.inspector) motion.to(panel, 1, { fresh: true, x: w.size === "mobile" ? 0 : 12, y: 0 });
    else motion.stop(panel);
  }, [motion, rootRef, w.inspector, w.size]);

  useEffect(() => {
    const panel = rootRef.current?.querySelector<HTMLElement>("#opportunity-list");
    if (!panel) return;
    if (w.listOpen) motion.to(panel, 1, { fresh: true, x: -12, y: 0 });
    else motion.stop(panel);
  }, [motion, rootRef, w.listOpen]);

  const modalId =
    w.listOpen && w.size === "tablet"
      ? "opportunity-list"
      : w.inspector && ["tablet", "narrow"].includes(w.size)
      ? "inspector"
      : null;

  useEffect(() => {
    const element = rootRef.current;
    if (!element) return;
    const modal = modalId ? element.querySelector<HTMLElement>(`#${modalId}`) : null;
    for (const item of element.querySelectorAll<HTMLElement>(
      ".site-header, .global-menu, .tablet-bar, .mobile-bar, .reference-footer, .workspace, .opportunity-list, .inspector"
    )) {
      item.inert = Boolean(modal && item !== modal);
    }
    for (const panel of element.querySelectorAll<HTMLElement>(".opportunity-list, .inspector")) {
      if (panel === modal) {
        panel.setAttribute("role", "dialog");
        panel.setAttribute("aria-modal", "true");
      } else {
        panel.removeAttribute("role");
        panel.removeAttribute("aria-modal");
      }
    }
    function keydown(event: KeyboardEvent) {
      if (element?.querySelector("dialog[open]")) return;
      if (event.key === "Escape") {
        if (w.listOpen) {
          event.preventDefault();
          w.closeList();
        } else if (w.inspector) {
          event.preventDefault();
          w.closeEvidence();
        }
      }
      if (event.key === "Tab" && modal) {
        const items = [
          ...modal.querySelectorAll<HTMLElement>(
            'button:not(:disabled), input, select, a[href], [tabindex="0"]'
          ),
        ].filter((item) => item.getClientRects().length);
        const first = items[0],
          last = items.at(-1);
        if (
          event.shiftKey &&
          (document.activeElement === first || !items.includes(document.activeElement as HTMLElement))
        ) {
          event.preventDefault();
          last?.focus();
        } else if (
          !event.shiftKey &&
          (document.activeElement === last || !items.includes(document.activeElement as HTMLElement))
        ) {
          event.preventDefault();
          first?.focus();
        }
      }
    }
    document.addEventListener("keydown", keydown);
    const previous = document.body.style.overflow;
    if (modal) document.body.style.overflow = "hidden";
    return () => {
      document.removeEventListener("keydown", keydown);
      document.body.style.overflow = previous;
    };
  }, [modalId, rootRef, w]);

  return (
    <div
      ref={rootRef}
      className="application-shell"
      data-level={w.location.level}
      data-list-open={w.listOpen}
      data-demo={Boolean(w.demo)}
    >
      <IconSprite />
      <a
        className="skip-link"
        href="#workspace-title"
        onClick={(event) => {
          event.preventDefault();
          w.setLevel("work");
        }}
      >
        Перейти к подготовке отклика
      </a>
      <WorkspaceHeader {...props} />
      <GlobalSidebar />
      <div className="layout">
        <VacancyList />
        <VacancyWorkspace />
        <EvidencePanel />
      </div>
      <footer className="reference-footer type-caption">
        <span id="live-status" role="status" aria-live="polite">
          {w.announcement ||
            (w.demo
              ? "Макет B01 на вымышленных данных. Запросы к API и ИИ не отправляются; действий с работодателем нет."
              : "Отклики не отправляются автоматически. Одобрение текста — отдельное действие.")}
        </span>
        <span>
          Токены SHA-256:{" "}
          <code>
            {w.demo ? "72565fc8a72458e0f5c28563ab5b4656a5cdbaf1bfa58e8c6c353b87402358e7" : "канонические токены CVortex"}
          </code>
        </span>
        <span>
          {w.demo && (
            <>
              Исходные файлы и статус проверки: <a href="http://127.0.0.1:8768/manifest.json">manifest.json</a>
            </>
          )}
        </span>
      </footer>
    </div>
  );
}

export default function ApplicationWorkspace(props: Identity & { initialVacancyId?: string }) {
  return (
    <WorkspaceProvider initialVacancyId={props.initialVacancyId}>
      <AppShell {...props} />
    </WorkspaceProvider>
  );
}

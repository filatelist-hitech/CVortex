"use client";

import { useEffect, useRef, useState } from "react";
import { WorkspaceProvider, useWorkspace } from "./use-workspace";
import { Icon, IconSprite } from "./icons";
import { scenarioLabels, scenarios, type Scenario } from "./model";
import { VortexSwissLogo } from "../vortex-logo";
import { VacancyList } from "./vacancy-list";
import { VacancyHeader } from "./vacancy-header";
import { WorkspaceTabs, OverviewPanel, RequirementsPanel, MaterialsPanel, EmployerPanel, HistoryPanel } from "./panels";
import { EvidencePanel } from "./evidence-panel";
import { NextAction } from "./recommendation";
import { useWorkspaceMotion } from "./motion";
import "./tokens.css";
import "./application.css";

type Identity = { email: string; role: string; onSignOut: () => Promise<void> };

export function AccountMenu({ email, role, onSignOut }: Identity) {
  const w = useWorkspace();
  const motion = useWorkspaceMotion();
  const closing = useRef(false), backdropPress = useRef(false);
  const dialog = useRef<HTMLDialogElement>(null);
  const trigger = useRef<HTMLButtonElement>(null);
  const [open, setOpen] = useState(false), [busy, setBusy] = useState(false), [feedback, setFeedback] = useState("");
  async function signOut() {
    if (!w.allowNavigation()) { setFeedback("Сохраните или отмените несохранённую правку перед выходом."); return; }
    setBusy(true); setFeedback("");
    try { await onSignOut(); } catch { setFeedback("Не удалось выйти. Попробуйте ещё раз."); } finally { setBusy(false); }
  }
  function close() {
    const element = dialog.current; if (!element) return;
    if (closing.current) { motion.stop(element); element.close(); return; }
    closing.current = true;
    motion.to(element, 0, { y: w.size === "mobile" ? 16 : -8, scale: .01, complete: () => element.close() });
  }
  function redirect(target: EventTarget, key?: string) {
    const element = dialog.current;
    if (!element || !closing.current || (target as Element).closest("#close-account") || key === "Escape") return;
    closing.current = false;
    motion.to(element, 1, { y: w.size === "mobile" ? 16 : -8, scale: .01 });
  }
  return <>
    <button ref={trigger} id="account-trigger" className="account-trigger" type="button" aria-haspopup="dialog" aria-controls="account-dialog" aria-expanded={open} onClick={() => {
      w.closeList(false); if (w.inspector) w.closeEvidence(false);
      dialog.current?.style.setProperty("--account-top", `${Math.round((trigger.current?.getBoundingClientRect().bottom ?? 72) + 8)}px`);
      const element = dialog.current;
      if (element) {
        const wasOpen = element.open; closing.current = false;
        if (!wasOpen) element.showModal();
        motion.to(element, 1, { fresh: !wasOpen, y: w.size === "mobile" ? 16 : -8, scale: .01 });
        element.scrollTop = 0;
      }
      setOpen(true); setFeedback("");
      requestAnimationFrame(() => dialog.current?.querySelector<HTMLButtonElement>("#close-account")?.focus());
    }}><span className="account-avatar" aria-hidden="true">{w.demo ? "Д" : email.charAt(0).toUpperCase()}</span><span><strong className="type-label">Профиль</strong><small id="account-role-label">{w.demo ? "Демо · " : ""}{role === "admin" ? "администратор" : "пользователь"}</small></span><Icon name="chevron-down" /></button>
    <dialog ref={dialog} id="account-dialog" className="account-dialog" aria-labelledby="account-title" aria-describedby="account-description" onCancel={event => { event.preventDefault(); close(); }} onClose={() => { if (dialog.current?.open) return; if (dialog.current) motion.stop(dialog.current); closing.current = false; setOpen(false); trigger.current?.focus(); }} onKeyDownCapture={event => redirect(event.target, event.key)} onPointerDownCapture={event => redirect(event.target)} onPointerDown={event => {
      const r = event.currentTarget.getBoundingClientRect();
      backdropPress.current = event.target === event.currentTarget && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom);
    }} onClick={event => {
      if (event.target === event.currentTarget) { const r = event.currentTarget.getBoundingClientRect(); if (backdropPress.current && (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom)) close(); }
      backdropPress.current = false;
    }}>
      <div className="account-heading"><div><p className="type-caption">{w.demo ? "Демо-данные · реальная сессия" : "Аккаунт CVortex"}</p><h2 className="type-section" id="account-title">Профиль и доступ</h2></div><button id="close-account" type="button" onClick={close}>Закрыть</button></div>
      <div className="demo-profile"><span className="account-avatar" aria-hidden="true">{email.charAt(0).toUpperCase()}</span><div><strong className="type-label">{email}</strong><p className="type-support">{role === "admin" ? "Администратор" : "Пользователь"}</p></div></div>
      <p id="account-description" className="subtle">{w.demo ? "Вакансии вымышлены. Вход, выход и права принадлежат текущему аккаунту; демонстрационного переключения прав здесь нет." : "Вход, выход и права принадлежат текущему аккаунту."}</p>
      <div className="account-actions"><button type="button" onClick={() => setFeedback("Редактирование профиля пока недоступно. Карьерные факты не меняются.")}><Icon name="user" />Мой профиль<span className="type-caption">Пока недоступен</span></button><a href="/settings" onClick={event => { if (!w.allowNavigation()) event.preventDefault(); }}><Icon name="settings" />Настройки<span className="type-caption">AI-провайдеры</span></a></div>
      {role === "admin" && <div className="admin-utilities"><h3 className="type-subsection"><Icon name="shield-check" />Для администратора</h3><a href="/diagnostics" onClick={event => { if (!w.allowNavigation()) event.preventDefault(); }}><Icon name="activity" />Диагностика</a><a href="/admin/settings" onClick={event => { if (!w.allowNavigation()) event.preventDefault(); }}><Icon name="settings" />Настройки системы</a></div>}
      {feedback && <p className="account-feedback type-secondary" role="status" aria-live="polite">{feedback}</p>}
      <div className="session-actions"><button type="button" disabled={busy} onClick={() => void signOut()}><Icon name="log-out" />{busy ? "Выход…" : "Выйти"}</button></div>
    </dialog>
  </>;
}

export function WorkspaceHeader(props: Identity) {
  const w = useWorkspace();
  return <header className="site-header">
    <div className="brand">
      <VortexSwissLogo />
      <div className="brand-copy">
        <strong>CVortex<span className="brand-dot">.</span></strong>
        <span className="type-caption">Your career, in context.</span>
      </div>
    </div>
    <div className="reference-meta">
      <strong className="type-secondary">{w.demo ? "Орбита решения" : "Вакансии"}</strong>
      <span className="type-caption">{w.demo ? <>Canonical B01, <b>демо-данные. Направление утверждено; проверка состояний ожидается.</b></> : "Подготовка отклика на основе подтверждённых фактов"}</span>
    </div>
    {w.demo ? <label className="scenario-picker type-caption">Состояние макета <select id="scenario-select" aria-label="Состояние макета" value={w.location.scenario} onChange={event => w.setScenario(event.target.value as Scenario)}>{scenarios.map((scenario, i) => <option key={scenario} value={scenario}>{scenarioLabels[i]}</option>)}</select></label> : <span className="type-caption">Ваши вакансии и материалы</span>}
    <AccountMenu {...props} />
  </header>;
}

export function GlobalSidebar() {
  const w = useWorkspace(), [menuLocation, setMenuLocation] = useState<string | null>(null);
  const locationKey = `${w.size}/${w.location.selected}/${w.location.section}/${w.location.scenario}/${w.location.level}`;
  const menuOpen = menuLocation === locationKey;
  const summary = useRef<HTMLElement>(null);
  return <div className="compact-navigation"><details className="global-menu" open={w.size !== "mobile" || menuOpen} onKeyDown={event => { if (event.key === "Escape") { setMenuLocation(null); summary.current?.focus(); } }}><summary ref={summary} className="type-nav" onClick={event => { event.preventDefault(); setMenuLocation(menuOpen ? null : locationKey); }}>Меню: вакансии</summary><nav className="global-nav" aria-label="Основное меню">
    <span className="type-nav" title="Этот раздел пока недоступен"><Icon name="sun" />Сегодня <small>Недоступно</small></span>
    <strong className="type-nav" aria-current="page"><Icon name="briefcase" />Вакансии</strong>
    <a className="type-nav" href="/career" onClick={event => { if (!w.allowNavigation()) event.preventDefault(); }}><Icon name="user" />Карьера <small>Факты</small></a>
    <span className="type-nav" title="В приложении ещё нет подтверждённой связи с работодателем"><Icon name="building" />Работодатели <small>Недоступно</small></span>
    <a className="type-nav" href="/settings" onClick={event => { if (!w.allowNavigation()) event.preventDefault(); }}><Icon name="settings" />Настройки <small>AI</small></a>
  </nav></details><div className="tablet-bar"><button id="open-list" type="button" aria-controls="opportunity-list" aria-expanded={w.listOpen} onClick={w.openList}>Выбрать вакансию</button></div><div className="mobile-bar" aria-label="Раздел макета"><button id="back-level" type="button" hidden={w.location.level === "list"} onClick={() => w.location.level === "context" ? w.closeEvidence() : w.setLevel("list")}>← Назад</button><strong className="type-nav" id="level-title">{w.location.level === "list" ? "Вакансии" : w.location.level === "work" ? w.item?.company : "Подтверждения"}</strong><span>{w.demo ? "Локальный макет" : "Рабочее пространство"}</span></div></div>;
}

export function VacancyWorkspace() {
  const w = useWorkspace();
  return <main className="workspace" id="workspace"><VacancyHeader /><NextAction />{!w.demo && w.status && <p className="action-status type-secondary" role="alert">{w.status}</p>}<WorkspaceTabs /><OverviewPanel /><RequirementsPanel /><MaterialsPanel /><EmployerPanel /><HistoryPanel /></main>;
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
  const modalId = w.listOpen && w.size === "tablet" ? "opportunity-list" : w.inspector && ["tablet", "narrow"].includes(w.size) ? "inspector" : null;
  useEffect(() => {
    const element = rootRef.current;
    if (!element) return;
    const modal = modalId ? element.querySelector<HTMLElement>(`#${modalId}`) : null;
    for (const item of element.querySelectorAll<HTMLElement>(".site-header, .global-menu, .tablet-bar, .mobile-bar, .reference-footer, .workspace, .opportunity-list, .inspector")) item.inert = Boolean(modal && item !== modal);
    for (const panel of element.querySelectorAll<HTMLElement>(".opportunity-list, .inspector")) {
      if (panel === modal) { panel.setAttribute("role", "dialog"); panel.setAttribute("aria-modal", "true"); }
      else { panel.removeAttribute("role"); panel.removeAttribute("aria-modal"); }
    }
    function keydown(event: KeyboardEvent) {
      if (element?.querySelector("dialog[open]")) return;
      if (event.key === "Escape") {
        if (w.listOpen) { event.preventDefault(); w.closeList(); }
        else if (w.inspector) { event.preventDefault(); w.closeEvidence(); }
      }
      if (event.key === "Tab" && modal) {
        const items = [...modal.querySelectorAll<HTMLElement>('button:not(:disabled), input, select, a[href], [tabindex="0"]')].filter(item => item.getClientRects().length);
        const first = items[0], last = items.at(-1);
        if (event.shiftKey && (document.activeElement === first || !items.includes(document.activeElement as HTMLElement))) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && (document.activeElement === last || !items.includes(document.activeElement as HTMLElement))) { event.preventDefault(); first?.focus(); }
      }
    }
    document.addEventListener("keydown", keydown);
    const previous = document.body.style.overflow;
    if (modal) document.body.style.overflow = "hidden";
    return () => { document.removeEventListener("keydown", keydown); document.body.style.overflow = previous; };
  }, [modalId, rootRef, w]);
  return <div ref={rootRef} className="application-shell" data-level={w.location.level} data-list-open={w.listOpen} data-demo={Boolean(w.demo)}>
    <IconSprite /><a className="skip-link" href="#workspace-title" onClick={event => { event.preventDefault(); w.setLevel("work"); }}>Перейти к подготовке отклика</a>
    <WorkspaceHeader {...props} /><GlobalSidebar /><div className="layout"><VacancyList /><VacancyWorkspace /><EvidencePanel /></div>
    <footer className="reference-footer type-caption"><span id="live-status" role="status" aria-live="polite">{w.announcement || (w.demo ? "Макет B01 на вымышленных данных. Запросы к API и ИИ не отправляются; действий с работодателем нет." : "Отклики не отправляются автоматически. Одобрение текста — отдельное действие.")}</span><span>Токены SHA-256: <code>{w.demo ? "72565fc8a72458e0f5c28563ab5b4656a5cdbaf1bfa58e8c6c353b87402358e7" : "канонические токены CVortex"}</code></span><span>{w.demo && <>Исходные файлы и статус проверки: <a href="http://127.0.0.1:8768/manifest.json">manifest.json</a></>}</span></footer>
  </div>;
}

export default function ApplicationWorkspace(props: Identity) { return <WorkspaceProvider><AppShell {...props} /></WorkspaceProvider>; }

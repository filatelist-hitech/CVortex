"use client";

import { useRef, useState, useSyncExternalStore, type ReactNode } from "react";
import Link from "next/link";
import ApplicationWorkspace from "./application/app-shell";
import { VortexSwissLogo } from "./vortex-logo";

function subscribeLocation(onChange: () => void) {
  window.addEventListener("hashchange", onChange);
  window.addEventListener("popstate", onChange);
  return () => {
    window.removeEventListener("hashchange", onChange);
    window.removeEventListener("popstate", onChange);
  };
}
const currentHash = () => window.location.hash;
const serverHash = () => "";
function subscribeCompact(onChange: () => void) {
  const media = window.matchMedia("(max-width: 767px)");
  media.addEventListener("change", onChange);
  return () => media.removeEventListener("change", onChange);
}
const compactSnapshot = () => window.matchMedia("(max-width: 767px)").matches;
const serverCompact = () => false;

const directions = [
  { id: "today", label: "Сегодня", icon: "sun", href: "/" },
  { id: "opportunities", label: "Вакансии", icon: "briefcase", href: "/vacancies" },
  { id: "import", label: "Импорт вакансии", icon: "plus", href: "/vacancies/import" },
  { id: "career", label: "Карьера", icon: "user", href: "/career" },
  { id: "employers", label: "Работодатели", icon: "building" },
  { id: "settings", label: "Настройки", icon: "settings", href: "/settings" },
] as const;

function Icon({ name }: { name: string }) {
  const paths: Record<string, ReactNode> = {
    sun: <><circle cx="12" cy="12" r="4" /><path d="M12 2v2m0 16v2M2 12h2m16 0h2M5 5l1.5 1.5m11 11L19 19M5 19l1.5-1.5m11-11L19 5" /></>,
    briefcase: <><rect x="3" y="7" width="18" height="14" rx="2" /><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M8 7v14m8-14v14" /></>,
    plus: <path d="M12 5v14m-7-7h14" />,
    user: <><circle cx="12" cy="7" r="4" /><path d="M5 21v-3a4 4 0 0 1 4-4h6a4 4 0 0 1 4 4v3" /></>,
    building: <><rect x="4" y="2" width="16" height="20" rx="1" /><path d="M9 22v-5h6v5M8 6h.01M12 6h.01M16 6h.01M8 10h.01M12 10h.01M16 10h.01M8 14h.01M16 14h.01" /></>,
    settings: <><path d="m9 3 1-1h4l1 3 3 1 3-1 2 4-2 2v3l2 2-2 4-3-1-3 1-1 3h-4l-1-3-3-1-3 1-2-4 2-2v-3l-2-2 2-4 3 1 3-1Z" /><circle cx="12" cy="12" r="3" /></>,
    chevron: <path d="m6 9 6 6 6-6" />,
  };
  return <svg className="workspace-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true" focusable="false">{paths[name]}</svg>;
}

export default function WorkspaceShell({ email, role, destination, onSignOut, children }: {
  email: string;
  role: string;
  destination: "today" | "career" | "diagnostics" | "vacancies" | "vacancy-import" | "admin-settings" | "settings";
  onSignOut: () => Promise<void>;
  children: ReactNode;
}) {
  const hash = useSyncExternalStore(subscribeLocation, currentHash, serverHash);
  const compact = useSyncExternalStore(subscribeCompact, compactSnapshot, serverCompact);
  const active =
    destination === "today"
      ? "today"
      : destination === "vacancies"
      ? "opportunities"
      : destination === "vacancy-import"
      ? "import"
      : destination === "career"
      ? hash === "#vacancy-title"
        ? "opportunities"
        : "career"
      : destination === "settings"
      ? "settings"
      : null;
  const currentLabel =
    destination === "today"
      ? "Сегодня"
      : destination === "vacancies"
      ? "Вакансии"
      : destination === "vacancy-import"
      ? "Импорт вакансии"
      : destination === "diagnostics"
      ? "Диагностика"
      : destination === "admin-settings"
      ? "Настройки системы"
      : destination === "settings"
      ? "Настройки"
      : "Карьера";
  const contextSubtitle =
    destination === "today"
      ? "Командный центр поиска работы и подготовки откликов"
      : destination === "vacancy-import"
      ? "Вставка текста и автоматический разбор требований"
      : destination === "vacancies"
      ? "Подготовка отклика на основе подтверждённых фактов"
      : destination === "diagnostics"
      ? "Ошибки и состояние операций"
      : destination === "settings"
      ? "Конфигурация AI-провайдеров и ключей"
      : destination === "admin-settings"
      ? "Конфигурация системы и интеграций"
      : "Факты, источники и подтверждения";
  const [menuOpen, setMenuOpen] = useState(false);
  const [accountOpen, setAccountOpen] = useState(false);
  const menuTrigger = useRef<HTMLElement>(null);
  const accountTrigger = useRef<HTMLButtonElement>(null);
  const accountDialog = useRef<HTMLDialogElement>(null);
  const [signingOut, setSigningOut] = useState(false);
  const [error, setError] = useState("");

  function closeMenu() {
    setMenuOpen(false);
    menuTrigger.current?.focus();
  }
  function closeAccount() { accountDialog.current?.close(); }
  async function signOut() {
    setSigningOut(true);
    setError("");
    try { await onSignOut(); }
    catch { setError("Не удалось выйти. Попробуйте ещё раз."); }
    finally { setSigningOut(false); }
  }

  if (destination === "vacancies") return <ApplicationWorkspace email={email} role={role} onSignOut={onSignOut} />;

  return <div className="workspace-shell">
    <a className="workspace-skip" href="#workspace-content">Перейти к содержимому</a>
    <header className="workspace-global-header">
      <div className="workspace-brand">
        <VortexSwissLogo />
        <div className="brand-copy">
          <strong>CVortex<span className="brand-dot">.</span></strong>
          <span>Your career, in context.</span>
        </div>
      </div>
      <div className="workspace-context"><strong>{currentLabel}</strong><span>{contextSubtitle}</span></div>
      <button ref={accountTrigger} type="button" className="workspace-account-trigger secondary" aria-haspopup="dialog" aria-controls="workspace-account-dialog" aria-expanded={accountOpen} onClick={() => { accountDialog.current?.showModal(); setAccountOpen(true); }}>
        <span className="workspace-avatar" aria-hidden="true">{email.charAt(0).toUpperCase()}</span><span><strong>Профиль</strong><small>{role === "admin" ? "Администратор" : "Пользователь"}</small></span><Icon name="chevron" />
      </button>
    </header>
    <details className="workspace-menu" open={!compact || menuOpen} onKeyDown={(event) => { if (compact && event.key === "Escape") { event.preventDefault(); closeMenu(); } }}>
      <summary ref={menuTrigger} onClick={(event) => { event.preventDefault(); setMenuOpen(!menuOpen); }}>Меню: {currentLabel.toLowerCase()}</summary>
      <nav aria-label="Основное меню">
        <ul className="workspace-destinations">
          {directions.map((item) => <li key={item.id}>{"href" in item
            // Native document/fragment navigation preserves existing focus, history and API hydration.
            ? <a href={item.href} aria-current={active === item.id ? item.id === "career" ? "page" : "location" : undefined} onClick={() => setMenuOpen(false)}><Icon name={item.icon} /><span>{item.label}</span></a>
            : <button type="button" disabled aria-label={item.label} aria-describedby={`${item.id}-unavailable`}><Icon name={item.icon} /><span>{item.label}</span><small id={`${item.id}-unavailable`}>Недоступно</small></button>}</li>)}
        </ul>
        {compact && <button className="workspace-menu-close secondary" type="button" onClick={closeMenu}>Закрыть меню</button>}
      </nav>
    </details>
    {children}
    <dialog ref={accountDialog} id="workspace-account-dialog" className="workspace-account-dialog" aria-labelledby="workspace-account-title" onClose={() => { setAccountOpen(false); accountTrigger.current?.focus(); }} onClick={(event) => { if (event.target === event.currentTarget) { const r=event.currentTarget.getBoundingClientRect(); if(event.clientX<r.left || event.clientX>r.right || event.clientY<r.top || event.clientY>r.bottom) closeAccount(); } }}>
      <div className="workspace-account-heading"><h2 id="workspace-account-title">Профиль</h2><button type="button" className="secondary" onClick={closeAccount}>Закрыть</button></div>
      <div className="workspace-account" role="group" aria-label="Управление аккаунтом">
        <span className="workspace-identity">{email}</span>
        <Link className="workspace-link" href="/settings" aria-current={destination === "settings" ? "page" : undefined} onClick={closeAccount}>Настройки AI</Link>
        {role === "admin" && <Link className="workspace-link" href="/diagnostics" aria-current={destination === "diagnostics" ? "page" : undefined} onClick={closeAccount}>Диагностика</Link>}
        {role === "admin" && <Link className="workspace-link" href="/admin/settings" aria-current={destination === "admin-settings" ? "page" : undefined} onClick={closeAccount}>Настройки системы</Link>}
        <button type="button" className="secondary" disabled={signingOut} onClick={() => void signOut()}>{signingOut ? "Выход…" : "Выйти"}</button>
        {error && <p className="error" role="alert">{error}</p>}
      </div>
    </dialog>
  </div>;
}

"use client";

import { useRef, useState, useSyncExternalStore, type MouseEvent } from "react";
import { GLOBAL_NAV_ITEMS, resolveActiveNavId, type Destination } from "../../navigation-config";
import { NavIcon } from "./nav-icons";

function subscribeLocation(onChange: () => void) {
  window.addEventListener("hashchange", onChange);
  window.addEventListener("popstate", onChange);
  return () => {
    window.removeEventListener("hashchange", onChange);
    window.removeEventListener("popstate", onChange);
  };
}
const currentHash = () => (typeof window !== "undefined" ? window.location.hash : "");
const serverHash = () => "";

function subscribeCompact(onChange: () => void) {
  if (typeof window === "undefined") return () => {};
  const media = window.matchMedia("(max-width: 767px)");
  media.addEventListener("change", onChange);
  return () => media.removeEventListener("change", onChange);
}
const compactSnapshot = () =>
  typeof window !== "undefined" ? window.matchMedia("(max-width: 767px)").matches : false;
const serverCompact = () => false;

export type GlobalSidebarProps = {
  destination: Destination;
  currentLabel?: string;
  allowNavigation?: () => boolean;
};

export function GlobalSidebar({
  destination,
  currentLabel = "раздел",
  allowNavigation = () => true,
}: GlobalSidebarProps) {
  const hash = useSyncExternalStore(subscribeLocation, currentHash, serverHash);
  const compact = useSyncExternalStore(subscribeCompact, compactSnapshot, serverCompact);
  const [menuOpen, setMenuOpen] = useState(false);
  const menuTrigger = useRef<HTMLElement>(null);

  const activeId = resolveActiveNavId(destination, hash);

  function closeMenu() {
    setMenuOpen(false);
    menuTrigger.current?.focus();
  }

  function handleLinkClick(event: MouseEvent<HTMLAnchorElement>) {
    if (!allowNavigation()) {
      event.preventDefault();
      return;
    }
    setMenuOpen(false);
  }

  return (
    <div className="compact-navigation global-sidebar-wrapper">
      <details
        className="workspace-menu global-menu"
        open={!compact || menuOpen}
        onKeyDown={(event) => {
          if (compact && event.key === "Escape") {
            event.preventDefault();
            closeMenu();
          }
        }}
      >
        <summary
          ref={menuTrigger}
          className="type-nav"
          onClick={(event) => {
            event.preventDefault();
            setMenuOpen(!menuOpen);
          }}
        >
          Меню: {currentLabel.toLowerCase()}
        </summary>

        <nav className="workspace-destinations global-nav" aria-label="Основное меню">
          <ul className="workspace-destinations-list">
            {GLOBAL_NAV_ITEMS.map((item) => {
              const isActive = activeId === item.id;
              const ariaCurrentValue = isActive
                ? item.id === "vacancies" && hash === "#vacancy-title"
                  ? "location"
                  : "page"
                : undefined;

              if (item.disabled || !item.href) {
                return (
                  <li key={item.id} className="nav-item-wrapper">
                    <button
                      type="button"
                      disabled
                      className="nav-item-button disabled"
                      aria-label={item.label}
                      aria-describedby={`${item.id}-unavailable`}
                      title={item.description}
                    >
                      <NavIcon name={item.icon} className="workspace-icon icon" />
                      <span className="nav-item-label">{item.label}</span>
                      <small id={`${item.id}-unavailable`} className="nav-item-badge">
                        {item.disabledReason ?? "Недоступно"}
                      </small>
                    </button>
                  </li>
                );
              }

              return (
                <li key={item.id} className="nav-item-wrapper">
                  <a
                    href={item.href}
                    className={`nav-item-link ${isActive ? "active" : ""}`}
                    aria-current={ariaCurrentValue}
                    onClick={handleLinkClick}
                  >
                    <NavIcon name={item.icon} className="workspace-icon icon" />
                    <span className="nav-item-label">{item.label}</span>
                    {item.badge && <small className="nav-item-badge">{item.badge}</small>}
                  </a>
                </li>
              );
            })}
          </ul>

          {compact && (
            <button
              className="workspace-menu-close secondary"
              type="button"
              onClick={closeMenu}
            >
              Закрыть меню
            </button>
          )}
        </nav>
      </details>
    </div>
  );
}

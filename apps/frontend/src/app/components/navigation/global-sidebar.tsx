"use client";

import { useRef, useState, useEffect, useSyncExternalStore, type MouseEvent } from "react";
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

function subscribeStorage(onChange: () => void) {
  window.addEventListener("storage", onChange);
  return () => window.removeEventListener("storage", onChange);
}
const getCollapsedSnapshot = () => {
  try {
    return localStorage.getItem("cvortex_sidebar_collapsed") === "true";
  } catch {
    return false;
  }
};
const getCollapsedServerSnapshot = () => false;

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
  const isStorageCollapsed = useSyncExternalStore(
    subscribeStorage,
    getCollapsedSnapshot,
    getCollapsedServerSnapshot
  );
  const [collapsedOverride, setCollapsedOverride] = useState<boolean | null>(null);
  const collapsed = collapsedOverride ?? isStorageCollapsed;

  const [menuOpen, setMenuOpen] = useState(false);
  const [isResizing, setIsResizing] = useState(false);
  const menuTrigger = useRef<HTMLElement>(null);

  const activeId = resolveActiveNavId(destination, hash);

  useEffect(() => {
    try {
      if (collapsed) {
        document.documentElement.style.setProperty("--sidebar-width", "68px");
      } else {
        const savedWidth = localStorage.getItem("cvortex_sidebar_width") || "260";
        document.documentElement.style.setProperty("--sidebar-width", `${savedWidth}px`);
      }
    } catch {
      // ignore
    }
  }, [collapsed]);

  function toggleCollapse() {
    const next = !collapsed;
    setCollapsedOverride(next);
    try {
      localStorage.setItem("cvortex_sidebar_collapsed", String(next));
    } catch {
      // ignore
    }
  }

  function handleResizerMouseDown(event: React.MouseEvent) {
    if (compact) return;
    event.preventDefault();
    setIsResizing(true);
    const startX = event.clientX;
    const computedWidth = parseInt(
      getComputedStyle(document.documentElement).getPropertyValue("--sidebar-width") || "260",
      10
    );

    function onMouseMove(moveEvent: globalThis.MouseEvent) {
      const delta = moveEvent.clientX - startX;
      let newWidth = computedWidth + delta;
      if (newWidth < 120) {
        setCollapsedOverride(true);
        document.documentElement.style.setProperty("--sidebar-width", "68px");
      } else {
        if (newWidth < 200) newWidth = 200;
        if (newWidth > 420) newWidth = 420;
        setCollapsedOverride(false);
        document.documentElement.style.setProperty("--sidebar-width", `${newWidth}px`);
      }
    }

    function onMouseUp() {
      setIsResizing(false);
      window.removeEventListener("mousemove", onMouseMove);
      window.removeEventListener("mouseup", onMouseUp);
      try {
        const currentWidth = parseInt(
          getComputedStyle(document.documentElement).getPropertyValue("--sidebar-width") || "260",
          10
        );
        if (currentWidth <= 68) {
          localStorage.setItem("cvortex_sidebar_collapsed", "true");
        } else {
          localStorage.setItem("cvortex_sidebar_collapsed", "false");
          localStorage.setItem("cvortex_sidebar_width", String(currentWidth));
        }
      } catch {
        // ignore
      }
    }

    window.addEventListener("mousemove", onMouseMove);
    window.addEventListener("mouseup", onMouseUp);
  }

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
    <div className={`compact-navigation global-sidebar-wrapper ${collapsed ? "sidebar-collapsed-mode" : ""}`}>
      <details
        className={`workspace-menu global-menu ${collapsed ? "collapsed" : ""}`}
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
                      title={collapsed ? `${item.label} (${item.disabledReason ?? "Недоступно"})` : item.description}
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
                    title={collapsed ? item.label : undefined}
                  >
                    <NavIcon name={item.icon} className="workspace-icon icon" />
                    <span className="nav-item-label">{item.label}</span>
                    {item.badge && <small className="nav-item-badge">{item.badge}</small>}
                  </a>
                </li>
              );
            })}
          </ul>

          {!compact && (
            <div className="sidebar-footer">
              <button
                type="button"
                className="sidebar-toggle-btn"
                onClick={toggleCollapse}
                aria-label={collapsed ? "Развернуть боковое меню" : "Свернуть боковое меню"}
                title={collapsed ? "Развернуть боковое меню" : "Свернуть боковое меню"}
              >
                <NavIcon
                  name={collapsed ? "chevron-right" : "panel-left"}
                  className="workspace-icon icon"
                />
                {!collapsed && <span className="nav-item-label">Свернуть меню</span>}
              </button>
            </div>
          )}

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

        {!compact && (
          <div
            className={`sidebar-resizer ${isResizing ? "resizing" : ""}`}
            onMouseDown={handleResizerMouseDown}
            onDoubleClick={toggleCollapse}
            role="separator"
            aria-orientation="vertical"
            aria-label="Настроить ширину бокового меню"
            title="Перетащите для настройки ширины, двойной клик — свернуть/развернуть"
          />
        )}
      </details>
    </div>
  );
}

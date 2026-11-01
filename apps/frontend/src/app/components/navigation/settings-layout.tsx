"use client";

import type { ReactNode } from "react";
import Link from "next/link";
import { SETTINGS_TABS } from "../../navigation-config";
import { NavIcon } from "./nav-icons";

export type SettingsLayoutProps = {
  currentTab: "ai" | "system" | "diagnostics";
  role?: string;
  title: string;
  description: string;
  action?: ReactNode;
  children: ReactNode;
};

export function SettingsLayout({
  currentTab,
  role = "user",
  title,
  description,
  action,
  children,
}: SettingsLayoutProps) {
  const isAdmin = role === "admin";
  const visibleTabs = SETTINGS_TABS.filter((tab) => !tab.adminOnly || isAdmin);

  return (
    <div className="settings-shell-container">
      <header className="settings-unified-header">
        <div className="settings-unified-title-area">
          <div className="settings-unified-badge type-caption">Настройки CVortex</div>
          <h1 className="settings-unified-heading">{title}</h1>
          <p className="settings-unified-desc subtle">{description}</p>
        </div>
        {action && <div className="settings-unified-action">{action}</div>}
      </header>

      {/* Settings Navigation Tabs */}
      <nav className="settings-nav-tabs" aria-label="Разделы настроек">
        <ul className="settings-tabs-list">
          {visibleTabs.map((tab) => {
            const isActive = tab.id === currentTab;
            return (
              <li key={tab.id} className="settings-tab-item">
                <Link
                  href={tab.href}
                  className={`settings-tab-link ${isActive ? "active" : ""}`}
                  aria-current={isActive ? "page" : undefined}
                >
                  <NavIcon name={tab.icon} className="settings-tab-icon" />
                  <span>{tab.label}</span>
                </Link>
              </li>
            );
          })}
        </ul>
      </nav>

      {/* Main Settings Content */}
      <div className="settings-unified-content">{children}</div>
    </div>
  );
}

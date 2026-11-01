"use client";

import type { ReactNode } from "react";
import { VortexSwissLogo } from "../../vortex-logo";
import { UserMenu, type UserMenuProps } from "./user-menu";
import { DESTINATION_META, type Destination } from "../../navigation-config";

export type GlobalHeaderProps = UserMenuProps & {
  destination: Destination;
  demo?: boolean;
  scenarioSelector?: ReactNode;
};

export function GlobalHeader({
  destination,
  email,
  role,
  onSignOut,
  allowNavigation,
  demo = false,
  scenarioSelector,
}: GlobalHeaderProps) {
  const meta = DESTINATION_META[destination] ?? {
    label: "CVortex",
    subtitle: "Your career, in context.",
  };

  return (
    <header className="workspace-global-header site-header">
      <div className="header-left-group">
        <div className="workspace-brand brand">
          <VortexSwissLogo />
          <div className="brand-copy">
            <strong>
              CVortex<span className="brand-dot">.</span>
            </strong>
            <span className="type-caption">Your career, in context.</span>
          </div>
        </div>

        <div className="header-divider" aria-hidden="true" />

        <div className="workspace-context reference-meta">
          <strong className="type-secondary">{meta.label}</strong>
          <span className="type-caption">{meta.subtitle}</span>
        </div>
      </div>

      <div className="header-right-group">
        {demo && scenarioSelector && (
          <div className="header-scenario-container">{scenarioSelector}</div>
        )}

        <UserMenu
          email={email}
          role={role}
          onSignOut={onSignOut}
          currentDestination={destination}
          allowNavigation={allowNavigation}
        />
      </div>
    </header>
  );
}

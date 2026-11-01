"use client";

import type { ReactNode } from "react";
import ApplicationWorkspace from "./application/app-shell";
import { GlobalHeader } from "./components/navigation/global-header";
import { GlobalSidebar } from "./components/navigation/global-sidebar";
import { DESTINATION_META, type Destination } from "./navigation-config";

export default function WorkspaceShell({
  email,
  role,
  destination,
  onSignOut,
  children,
  initialVacancyId,
}: {
  email: string;
  role: string;
  destination: Destination;
  onSignOut: () => Promise<void>;
  children: ReactNode;
  initialVacancyId?: string;
}) {
  if (destination === "vacancies") {
    return (
      <ApplicationWorkspace
        email={email}
        role={role}
        onSignOut={onSignOut}
        initialVacancyId={initialVacancyId}
      />
    );
  }

  const meta = DESTINATION_META[destination];

  return (
    <div className="workspace-shell">
      <a className="workspace-skip skip-link" href="#workspace-content">
        Перейти к содержимому
      </a>
      <GlobalHeader
        destination={destination}
        email={email}
        role={role}
        onSignOut={onSignOut}
      />
      <GlobalSidebar
        destination={destination}
        currentLabel={meta?.label}
      />
      {children}
    </div>
  );
}

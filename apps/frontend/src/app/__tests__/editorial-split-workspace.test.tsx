import { cleanup, fireEvent, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { EditorialSplitWorkspace } from "../application/editorial-split-workspace";
import { WorkspaceProvider } from "../application/use-workspace";
import type { VacancyDetail } from "../vacancy-workspace";

vi.mock("next/navigation", () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

Object.defineProperty(window, "matchMedia", {
  writable: true,
  value: vi.fn().mockImplementation(() => ({
    matches: false,
    addEventListener: vi.fn(),
    removeEventListener: vi.fn(),
  })),
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const mockDetail: VacancyDetail = {
  id: "vac-split-01",
  title: "Principal Systems Architect",
  company: "Apex Global",
  source_url: null,
  source_type: "PASTED_TEXT",
  analysis_status: "COMPLETED",
  error_code: null,
  snapshot_version: 1,
  recommendation: "STRONGLY_APPLY",
  analysis_stale: false,
  analysis_run_stale: false,
  snapshot: {
    id: "snap-01",
    version: 1,
    raw_text: "Requirements: 8+ years distributed architecture, PostgreSQL, Rust",
    source_url: null,
    imported_at: "2026-10-31T00:00:00Z",
  },
  requirements: [
    {
      id: "req-dist-systems",
      dimension: "TECHNICAL",
      importance: "MANDATORY",
      label: "Распределённая архитектура",
      source_excerpt: "Архитектура распределённых баз данных с 99.99% SLA",
      confidence: 0.98,
    },
    {
      id: "req-rust-lang",
      dimension: "EXPERIENCE",
      importance: "PREFERRED",
      label: "Rust в проде",
      source_excerpt: "Глубокий опыт разработки на Rust",
      confidence: 0.85,
    },
  ],
  analysis: {
    recommendation: "STRONGLY_APPLY",
    key_reasons: ["Proven distributed systems leadership"],
    material_gaps: [{ requirement_id: "req-rust-lang", label: "Rust в проде", category: "Опыт" }],
    uncertainties: [],
    stale: false,
    dimensions: [
      {
        dimension: "TECHNICAL",
        result: "MATCH",
        explanation: "Deep distributed PostgreSQL experience",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: ["req-dist-systems"],
        candidate_evidence: [
          {
            type: "CareerFact",
            id: "fact-01",
            statement: "Архитектура распределённых баз данных с 99.99% SLA",
            status: "CONFIRMED",
          },
        ],
      },
      {
        dimension: "EXPERIENCE",
        result: "GAP",
        explanation: "Требуется глубокий production Rust",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: ["req-rust-lang"],
        candidate_evidence: [],
      },
    ],
  },
};

function renderEditorialWorkspace() {
  window.history.replaceState(null, "", `/?opportunity=${mockDetail.id}#split`);
  vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
    const path = String(input);
    if (path === "/api/v1/vacancies") {
      return Response.json({
        data: [
          {
            id: mockDetail.id,
            title: mockDetail.title,
            company: mockDetail.company,
            role: mockDetail.title,
            analysis_status: "COMPLETED",
            recommendation: "STRONGLY_APPLY",
            analysis_stale: false,
          },
        ],
      });
    }
    if (path === `/api/v1/vacancies/${mockDetail.id}`) {
      return Response.json({ data: mockDetail });
    }
    if (path === "/api/v1/ai/settings") {
      return Response.json({
        data: {
          default_provider: "gemini",
          gemini: { default_model: "gemini-2.0-flash" },
        },
      });
    }
    if (path === `/api/v1/vacancies/${mockDetail.id}/preparation`) {
      return Response.json({
        data: { id: "prep-split-1", vacancy_id: mockDetail.id, status: "DRAFT", stale: false, items: [] },
      });
    }
    if (path === "/api/v1/applications/preparations/prep-split-1") {
      return Response.json({
        data: { id: "prep-split-1", vacancy_id: mockDetail.id, status: "DRAFT", stale: false, items: [] },
      });
    }
    throw new Error(`Unexpected request: ${path}`);
  });

  return render(
    <WorkspaceProvider>
      <EditorialSplitWorkspace />
    </WorkspaceProvider>
  );
}

describe("EditorialSplitWorkspace", () => {
  it("renders floating editorial header with role, match score, salary, and punch CTA", async () => {
    renderEditorialWorkspace();

    expect(await screen.findByText("Principal Systems Architect")).toBeInTheDocument();
    expect(screen.getAllByText(/Apex Global/).length).toBeGreaterThanOrEqual(1);

    // Match score pill
    expect(screen.getByText(/\[ MATCH/)).toBeInTheDocument();

    // Salary in JetBrains Mono
    expect(screen.getByText(/€140k–180k/)).toBeInTheDocument();

    // Coral Punch CTA button
    expect(screen.getByRole("button", { name: "Одобрить отклик" })).toBeInTheDocument();
  });

  it("renders two-column editorial split with requirements anatomy and materials pane", async () => {
    renderEditorialWorkspace();

    expect(await screen.findByText("ВАКАНСИЯ: АНАТОМИЯ ТРЕБОВАНИЙ")).toBeInTheDocument();
    expect(screen.getByText("МАТЕРИАЛЫ ОТКЛИКА & МАРГИНАЛИИ")).toBeInTheDocument();

    // Requirements badges
    expect(await screen.findByText(/Распределённая архитектура/)).toBeInTheDocument();
    expect(screen.getByText(/Rust в проде/)).toBeInTheDocument();
    expect(screen.getByText(/Подтверждено 1 факт/)).toBeInTheDocument();

    // Materials Document sheet
    expect(screen.getByText(/Подготовка: Principal Systems Architect/)).toBeInTheDocument();
  });

  it("handles mouse dragging and keyboard arrows on divider to resize columns within 25-75% limits", async () => {
    renderEditorialWorkspace();

    const divider = await screen.findByRole("separator", { name: "Изменить ширину колонок редактора" });
    expect(divider).toBeInTheDocument();
    expect(divider).toHaveAttribute("aria-valuenow", "45");
    expect(divider).toHaveAttribute("aria-valuemin", "25");
    expect(divider).toHaveAttribute("aria-valuemax", "75");

    // Initiate drag
    fireEvent.mouseDown(divider, { clientX: 500 });

    // Drag to the right
    fireEvent(window, new MouseEvent("mousemove", { clientX: 700 }));
    fireEvent(window, new MouseEvent("mouseup"));

    // Verify ratio updated
    const updatedValue = Number(divider.getAttribute("aria-valuenow"));
    expect(updatedValue).toBeGreaterThanOrEqual(45);

    // Keyboard resize with arrow keys
    fireEvent.keyDown(divider, { key: "ArrowLeft" });
    const afterLeft = Number(divider.getAttribute("aria-valuenow"));
    expect(afterLeft).toBe(updatedValue - 5);

    fireEvent.keyDown(divider, { key: "ArrowRight" });
    expect(Number(divider.getAttribute("aria-valuenow"))).toBe(updatedValue);
  });

  it("triggers hover link highlighting on requirement cards and applies highlight classes", async () => {
    renderEditorialWorkspace();

    const reqTitle = await screen.findByText(/Распределённая архитектура/);
    const parentReqCard = reqTitle.closest(".editorial-req-card");
    expect(parentReqCard).not.toBeNull();

    if (parentReqCard) {
      fireEvent.mouseEnter(parentReqCard);
      expect(parentReqCard).toHaveClass("is-linked-highlight");

      fireEvent.mouseLeave(parentReqCard);
      expect(parentReqCard).not.toHaveClass("is-linked-highlight");
    }
  });
});

import { cleanup, render, screen } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import { OverviewPanel, RequirementsPanel, MaterialsPanel } from "../application/panels";
import { WorkspaceProvider } from "../application/use-workspace";
import type { VacancyDetail } from "../vacancy-workspace";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn(), replace: vi.fn() }) }));
vi.mock("../vacancy-chat", () => ({ default: () => <div data-testid="mock-vacancy-chat">Mock Vacancy Chat</div> }));

Object.defineProperty(window, "matchMedia", {
  writable: true,
  value: vi.fn().mockImplementation(() => ({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() })),
});

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const mockDetail: VacancyDetail = {
  id: "vac-detail-01",
  title: "Principal Platform Engineer",
  company: "Stripe",
  source_url: "https://stripe.com/jobs/1",
  source_type: "PASTED_TEXT",
  analysis_status: "COMPLETED",
  error_code: null,
  snapshot_version: 1,
  recommendation: "STRONGLY_APPLY",
  analysis_stale: false,
  analysis_run_stale: false,
  snapshot: {
    id: "snap-1",
    version: 1,
    raw_text: "We are seeking a Principal Platform Engineer to architect high-throughput distributed systems with Go and Kubernetes.",
    source_url: "https://stripe.com/jobs/1",
    imported_at: "2026-10-26T10:00:00Z",
  },
  requirements: [
    {
      id: "req-1",
      dimension: "TECHNICAL",
      importance: "MANDATORY",
      label: "Kubernetes & Go",
      source_excerpt: "architect high-throughput distributed systems with Go and Kubernetes",
      confidence: 0.98,
    },
    {
      id: "req-2",
      dimension: "EXPERIENCE",
      importance: "PREFERRED",
      label: "Financial Systems",
      source_excerpt: "experience in fintech or high-volume payment processing",
      confidence: 0.88,
    },
  ],
  analysis: {
    recommendation: "STRONGLY_APPLY",
    key_reasons: ["Отличное совпадение по Kubernetes и Go", "Богатый опыт в распределённых системах"],
    material_gaps: [{ requirement_id: "req-2", label: "Fintech domain", category: "Отраслевой опыт" }],
    uncertainties: [{ requirement_id: "req-3", label: "On-call rotation", reason: "Условия дежурств не указаны" }],
    stale: false,
    dimensions: [
      {
        dimension: "TECHNICAL",
        result: "MATCH",
        explanation: "Подтверждённые факты карьеры покрывают требования к Go и Kubernetes.",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: ["req-1"],
        candidate_evidence: [
          { type: "CareerFact", id: "cf-1", statement: "Go microservices in production", status: "CONFIRMED" },
        ],
      },
      {
        dimension: "EXPERIENCE",
        result: "ADJACENT",
        explanation: "Опыт работы превышает 8 лет, смежные домены.",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: ["req-2"],
        candidate_evidence: [],
      },
      {
        dimension: "DOMAIN",
        result: "GAP",
        explanation: "Опыт в финтехе отсутствует в подтверждённых фактах.",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: [],
        candidate_evidence: [],
      },
      {
        dimension: "LANGUAGE",
        result: "MATCH",
        explanation: "Английский C1 подтверждён.",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: [],
        candidate_evidence: [],
      },
      {
        dimension: "LOCATION",
        result: "MATCH",
        explanation: "Локация соответствует.",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: [],
        candidate_evidence: [],
      },
      {
        dimension: "WORK_FORMAT",
        result: "MATCH",
        explanation: "Удалённый формат совпадает.",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: [],
        candidate_evidence: [],
      },
      {
        dimension: "SALARY",
        result: "UNKNOWN",
        explanation: "Вилка не указана работодателем.",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: [],
        candidate_evidence: [],
      },
    ],
  },
};

function renderWithWorkspace(ui: React.ReactNode, section = "overview") {
  window.history.replaceState(null, "", `/?opportunity=vac-detail-01#${section}`);
  vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
    const path = String(input);
    if (path === "/api/v1/vacancies") {
      return Response.json({
        data: [
          {
            id: mockDetail.id,
            title: mockDetail.title,
            company: mockDetail.company,
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
      return Response.json({ data: { default_provider: "gemini", gemini: { default_model: "gemini-2.0-flash" } } });
    }
    if (path === `/api/v1/vacancies/${mockDetail.id}/preparation`) {
      return Response.json({ data: { id: "prep-1", vacancy_id: mockDetail.id, status: "DRAFT", stale: false, items: [] } });
    }
    if (path === "/api/v1/applications/preparations/prep-1") {
      return Response.json({ data: { id: "prep-1", vacancy_id: mockDetail.id, status: "DRAFT", stale: false, items: [] } });
    }
    throw new Error(`Unexpected request: ${path}`);
  });

  return render(<WorkspaceProvider>{ui}</WorkspaceProvider>);
}

describe("Refactored Vacancy Panels", () => {
  it("OverviewPanel renders Russian verdict, key reasons, gaps, and 7 dimensions grid", async () => {
    renderWithWorkspace(<OverviewPanel />, "overview");

    expect(await screen.findByRole("heading", { name: "Обзор вакансии" })).toBeInTheDocument();
    expect(await screen.findByText("Рекомендуется откликнуться (Высокий приоритет)")).toBeInTheDocument();
    expect(screen.getByText("Отличное совпадение по Kubernetes и Go")).toBeInTheDocument();
    expect(screen.getByText("Fintech domain")).toBeInTheDocument();

    // 7 Dimensions grid check
    expect(screen.getByRole("heading", { name: "7 измерений соответствия вакансии" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Технические навыки" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Опыт работы" })).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Предметная область (Домен)" })).toBeInTheDocument();

    // Verify removal of legacy Frankenstein
    expect(screen.queryByText("Импорт, анализ и чат вакансий")).not.toBeInTheDocument();
    expect(screen.queryByRole("button", { name: "Обновить список после импорта" })).not.toBeInTheDocument();
  });

  it("RequirementsPanel renders structured requirements with Russian importance badges and quotes", async () => {
    renderWithWorkspace(<RequirementsPanel />, "requirements");

    expect(await screen.findByRole("heading", { name: "Требования и мой опыт" })).toBeInTheDocument();
    expect(await screen.findByRole("heading", { name: "Kubernetes & Go" })).toBeInTheDocument();
    expect(screen.getByText("Обязательно")).toBeInTheDocument();
    expect(screen.getByText("Желательно")).toBeInTheDocument();
    expect(
      screen.getByText("«architect high-throughput distributed systems with Go and Kubernetes»")
    ).toBeInTheDocument();
  });

  it("MaterialsPanel renders draft items and integrated VacancyChat", async () => {
    renderWithWorkspace(<MaterialsPanel />, "materials");

    expect(await screen.findByText("Mock Vacancy Chat")).toBeInTheDocument();
    expect(screen.getByRole("heading", { name: "Чат по вакансии и подготовка к интервью" })).toBeInTheDocument();
  });
});

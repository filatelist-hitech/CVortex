import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import TodayWorkspace from "../today-workspace";

vi.mock("next/navigation", () => ({ useRouter: () => ({ push: vi.fn(), replace: vi.fn() }) }));

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const mockAiSettings = {
  default_provider: "gemini",
  gemini: {
    has_key: true,
    masked_key: "AIza••••••••1234",
    default_model: "gemini-2.0-flash",
    available_models: ["gemini-2.0-flash", "gemini-1.5-pro"],
    env_key_configured: false,
  },
  openai: {
    has_key: false,
    masked_key: null,
    default_model: "gpt-4o",
    available_models: ["gpt-4o", "gpt-4o-mini"],
    env_key_configured: false,
  },
};

const mockCareerEmpty = {
  facts: [],
  claims: [],
  sources: [],
};

const mockCareerWithFacts = {
  facts: [
    { id: "fact-1", statement: "PHP & Laravel", status: "CONFIRMED", provenance_type: "user_manual" },
    { id: "fact-2", statement: "PostgreSQL & Redis", status: "CONFIRMED", provenance_type: "user_manual" },
    { id: "fact-3", statement: "Unverified fact", status: "PENDING", provenance_type: "paste_extraction" },
  ],
  claims: [],
  sources: [],
};

const mockVacancies = [
  {
    id: "vac-1",
    title: "Senior Fullstack Engineer",
    company: "Acme Corp",
    analysis_status: "COMPLETED",
    recommendation: "STRONGLY_APPLY",
    snapshot_version: 1,
    analysis_stale: false,
    analysis_run_stale: false,
  },
  {
    id: "vac-2",
    title: "Backend Lead",
    company: "Beta Systems",
    analysis_status: "RUNNING",
    recommendation: null,
    snapshot_version: 1,
    analysis_stale: false,
    analysis_run_stale: false,
  },
];

describe("TodayWorkspace", () => {
  it("renders today command center with header, role, and primary CTAs", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: mockAiSettings });
      if (path === "/api/v1/career") return Response.json({ data: mockCareerWithFacts });
      if (path === "/api/v1/vacancies") return Response.json({ data: mockVacancies });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<TodayWorkspace email="user@cvortex.test" role="user" />);

    expect(await screen.findByRole("heading", { name: "Сегодня" })).toBeInTheDocument();
    expect(screen.getByText(/user@cvortex\.test · Кандидат/)).toBeInTheDocument();

    const importLink = screen.getByRole("link", { name: /\+ Импортировать вакансию/ });
    expect(importLink).toHaveAttribute("href", "/vacancies/import");

    const careerLink = screen.getByRole("link", { name: /Загрузить резюме в Карьеру/ });
    expect(careerLink).toHaveAttribute("href", "/career");
  });

  it("displays smart warning when confirmed career facts count is 0", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: mockAiSettings });
      if (path === "/api/v1/career") return Response.json({ data: mockCareerEmpty });
      if (path === "/api/v1/vacancies") return Response.json({ data: [] });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<TodayWorkspace email="fresh@cvortex.test" role="user" />);

    expect(await screen.findByText("База карьерного опыта пуста")).toBeInTheDocument();
    expect(screen.getByText("0 подтверждённых фактов карьеры")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Добавить опыт и подтвердить факты →/ })).toHaveAttribute("href", "/career");
  });

  it("switches AI provider in 1 click and updates model select", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings" && (!options || !options.method || options.method === "GET")) {
        return Response.json({ data: mockAiSettings });
      }
      if (path === "/api/v1/ai/settings" && options?.method === "PATCH") {
        return Response.json({ data: { ...mockAiSettings, default_provider: "openai" } });
      }
      if (path === "/api/v1/career") return Response.json({ data: mockCareerWithFacts });
      if (path === "/api/v1/vacancies") return Response.json({ data: mockVacancies });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<TodayWorkspace email="ai-user@cvortex.test" role="user" />);

    expect(await screen.findByRole("tab", { name: /Google Gemini/ })).toHaveAttribute("aria-selected", "true");

    // Click OpenAI tab
    const openaiTab = screen.getByRole("tab", { name: /OpenAI/ });
    fireEvent.click(openaiTab);

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledWith(
        "/api/v1/ai/settings",
        expect.objectContaining({
          method: "PATCH",
          body: JSON.stringify({ default_provider: "openai" }),
        })
      );
    });

    expect(openaiTab).toHaveAttribute("aria-selected", "true");
  });

  it("executes connection test and reports latency in ms", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: mockAiSettings });
      if (path === "/api/v1/career") return Response.json({ data: mockCareerWithFacts });
      if (path === "/api/v1/vacancies") return Response.json({ data: mockVacancies });
      if (path === "/api/v1/ai/test-connection" && options?.method === "POST") {
        return Response.json({
          data: {
            status: "healthy",
            provider: "gemini",
            model: "gemini-2.0-flash",
            latency_ms: 195,
            message: "Connection healthy",
          },
        });
      }
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<TodayWorkspace email="tester@cvortex.test" role="user" />);

    const testBtn = await screen.findByRole("button", { name: /Проверить подключение/ });
    fireEvent.click(testBtn);

    expect(await screen.findByText("195 мс")).toBeInTheDocument();
    expect(screen.getByText("· Подключение активно")).toBeInTheDocument();
  });

  it("renders recent vacancies with status and direct links", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: mockAiSettings });
      if (path === "/api/v1/career") return Response.json({ data: mockCareerWithFacts });
      if (path === "/api/v1/vacancies") return Response.json({ data: mockVacancies });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<TodayWorkspace email="user@cvortex.test" role="user" />);

    expect(await screen.findByText("Senior Fullstack Engineer")).toBeInTheDocument();
    expect(screen.getByText("Acme Corp")).toBeInTheDocument();
    expect(screen.getByText("Рекомендуется (Высокий приоритет)")).toBeInTheDocument();
    expect(screen.getByRole("link", { name: /Senior Fullstack Engineer.*Acme Corp/ })).toHaveAttribute(
      "href",
      "/vacancies?opportunity=vac-1&level=work#overview"
    );
  });
});

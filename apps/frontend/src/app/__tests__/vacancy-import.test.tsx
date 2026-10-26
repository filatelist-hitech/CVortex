import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import VacancyImportWorkspace from "../vacancy-import";

const pushMock = vi.fn();
vi.mock("next/navigation", () => ({ useRouter: () => ({ push: pushMock, replace: vi.fn() }) }));

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
  pushMock.mockClear();
});

const mockAiSettings = {
  default_provider: "gemini",
  gemini: {
    has_key: true,
    masked_key: "AIza••••••••",
    default_model: "gemini-2.0-flash",
    available_models: ["gemini-2.0-flash"],
    env_key_configured: false,
  },
};

const mockCareerWithFacts = {
  facts: [{ id: "f-1", statement: "Fullstack experience", status: "CONFIRMED", provenance_type: "user_manual" }],
  claims: [],
  sources: [],
};

describe("VacancyImportWorkspace", () => {
  it("renders form, character counter, active AI badge and disables submit on empty input", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: mockAiSettings });
      if (path === "/api/v1/career") return Response.json({ data: mockCareerWithFacts });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<VacancyImportWorkspace />);

    expect(await screen.findByRole("heading", { name: "Новая вакансия" })).toBeInTheDocument();
    expect(screen.getByText("Google Gemini")).toBeInTheDocument();
    expect(screen.getByText(/0 \/ .* символов/)).toBeInTheDocument();

    const submitBtn = screen.getByRole("button", { name: "Сохранить и проанализировать" });
    expect(submitBtn).toBeDisabled();
  });

  it("updates character counter as user types", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: mockAiSettings });
      if (path === "/api/v1/career") return Response.json({ data: mockCareerWithFacts });
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<VacancyImportWorkspace />);

    const textarea = await screen.findByLabelText(/Текст вакансии/);
    fireEvent.change(textarea, { target: { value: "We are hiring a Senior Software Engineer for backend development." } });

    expect(screen.getByText(/65 \/ .* символов/)).toBeInTheDocument();
    expect(screen.getByRole("button", { name: "Сохранить и проанализировать" })).toBeEnabled();
  });

  it("submits vacancy, displays interactive progress tracker, and redirects on completion", async () => {
    let pollCount = 0;
    const vacancyId = "vac-created-123";

    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const path = String(input);
      if (path === "/api/v1/ai/settings") return Response.json({ data: mockAiSettings });
      if (path === "/api/v1/career") return Response.json({ data: mockCareerWithFacts });
      if (path === "/api/v1/vacancies" && options?.method === "POST") {
        return Response.json({ data: { id: vacancyId } }, { status: 201 });
      }
      if (path === `/api/v1/vacancies/${vacancyId}`) {
        pollCount++;
        return Response.json({
          data: {
            id: vacancyId,
            title: "Senior Backend Developer",
            analysis_status: pollCount >= 2 ? "COMPLETED" : "RUNNING",
            requirements: [
              { id: "req-1", dimension: "TECHNICAL", importance: "MANDATORY", label: "PHP & Laravel", source_excerpt: "PHP required", confidence: 0.95 },
            ],
            analysis: pollCount >= 2 ? { recommendation: "APPLY", key_reasons: [], material_gaps: [], uncertainties: [], dimensions: [] } : null,
          },
        });
      }
      throw new Error(`Unexpected request: ${path}`);
    });

    render(<VacancyImportWorkspace />);

    const textarea = await screen.findByLabelText(/Текст вакансии/);
    fireEvent.change(textarea, { target: { value: "Full requirements for the new role." } });

    const submitBtn = screen.getByRole("button", { name: "Сохранить и проанализировать" });
    fireEvent.click(submitBtn);

    // Progress card appears
    expect(await screen.findByRole("region", { name: "Прогресс обработки вакансии" })).toBeInTheDocument();
    expect(screen.getByText("Шаг 1: Сохранение исходного снимка")).toBeInTheDocument();
    expect(screen.getByText("Шаг 2: Извлечение требований AI-моделью")).toBeInTheDocument();

    // Polls until completed and triggers redirect
    await waitFor(
      () => {
        expect(pushMock).toHaveBeenCalledWith(`/vacancies?opportunity=${vacancyId}&level=work#overview`);
      },
      { timeout: 4000 }
    );
  });
});

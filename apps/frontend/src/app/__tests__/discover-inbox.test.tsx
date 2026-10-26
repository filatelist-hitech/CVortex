import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";
import DiscoverInbox from "../discover-inbox";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const fixtureJobs = [
  {
    id: "disc-101",
    portal: "habr",
    external_id: "10001",
    title: "Senior Backend Developer (PHP/Laravel)",
    company: "Fintech Lab",
    location: "Remote",
    source_url: "https://career.habr.com/vacancies/10001",
    salary_text: "3500 - 4500 $",
    snippet: "We develop high-load fintech core with Laravel and PostgreSQL.",
    description: "Full description of fintech backend role.",
    fit_score: 88,
    fit_level: "HIGH",
    fit_reasons: {
      matched_skills: ["Laravel", "PostgreSQL", "Docker"],
      missing_skills: ["Kafka"],
      title_match: true,
      summary: "Совпадение по профилю роли | Стек: Laravel, PostgreSQL, Docker",
    },
    status: "INBOX",
    security_threat_level: "CLEAN",
    published_at: "2026-10-26T10:00:00Z",
    created_at: "2026-10-26T10:05:00Z",
  },
  {
    id: "disc-102",
    portal: "headhunter",
    external_id: "20002",
    title: "Junior Manual QA",
    company: "Retail Corp",
    location: "Москва",
    source_url: "https://hh.ru/vacancy/20002",
    salary_text: "80000 RUB",
    snippet: "Ручное тестирование веб-интерфейсов.",
    description: "Тестирование по готовым тест-кейсам.",
    fit_score: 20,
    fit_level: "LOW",
    fit_reasons: {
      matched_skills: [],
      missing_skills: ["PostgreSQL"],
      title_match: false,
      summary: "Частичное совпадение с общим описанием",
    },
    status: "INBOX",
    security_threat_level: "CLEAN",
    published_at: "2026-10-26T09:00:00Z",
    created_at: "2026-10-26T09:05:00Z",
  },
];

describe("Discover Inbox UI", () => {
  it("renders search controls and loads inbox items", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?")) {
        return Response.json({ items: fixtureJobs, meta: { total: 2 } });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    expect(screen.getByRole("heading", { name: /Входящие вакансии/i })).toBeInTheDocument();
    expect(screen.getByRole("button", { name: /Найти вакансии/i })).toBeInTheDocument();

    await waitFor(() => {
      expect(screen.getByText("Senior Backend Developer (PHP/Laravel) ↗")).toBeInTheDocument();
    });

    expect(screen.getByText("Fintech Lab")).toBeInTheDocument();
    expect(screen.getByText("★ Match 88%")).toBeInTheDocument();
    expect(screen.getAllByText("🛡️ Clean").length).toBe(2);
    expect(screen.getByText("💰 3500 - 4500 $")).toBeInTheDocument();
    expect(screen.getByText("Laravel")).toBeInTheDocument();
  });

  it("handles search execution and updates list", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery/search") && options?.method === "POST") {
        return Response.json({ success: true, count: 2, items: fixtureJobs });
      }
      if (url.includes("/api/v1/discovery?")) {
        return Response.json({ items: fixtureJobs, meta: { total: 2 } });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    const searchInput = screen.getByRole("textbox", { name: /Ключевые слова/i });
    fireEvent.change(searchInput, { target: { value: "Fullstack Go" } });

    const searchButton = screen.getByRole("button", { name: /Найти вакансии/i });
    fireEvent.click(searchButton);

    await waitFor(() => {
      expect(screen.getByText(/обнаружено 2 подходящих вакансий/i)).toBeInTheDocument();
    });

    expect(fetchMock).toHaveBeenCalledWith(
      "/api/v1/discovery/search",
      expect.objectContaining({
        method: "POST",
      })
    );
  });

  it("handles triage accept into core pipeline", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?") && !options?.method) {
        return Response.json({ items: fixtureJobs, meta: { total: 2 } });
      }
      if (url === "/api/v1/discovery/disc-101/accept" && options?.method === "POST") {
        return Response.json({
          success: true,
          discovered: { ...fixtureJobs[0], status: "ACCEPTED" },
          vacancy: { id: "vac-core-999" },
        });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    await waitFor(() => {
      expect(screen.getByText("Senior Backend Developer (PHP/Laravel) ↗")).toBeInTheDocument();
    });

    const acceptButtons = screen.getAllByRole("button", { name: /✓ В работу/i });
    fireEvent.click(acceptButtons[0]);

    await waitFor(() => {
      expect(screen.getByText(/добавлена в пайплайн/i)).toBeInTheDocument();
      const pipelineLink = screen.getByRole("link", { name: /Перейти к анализу вакансии в пайплайне CVortex →/i });
      expect(pipelineLink).toBeInTheDocument();
      expect(pipelineLink).toHaveAttribute("href", "/?selected=vac-core-999");
    });
  });

  it("handles triage dismiss to remove vacancy from inbox", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?") && !options?.method) {
        return Response.json({ items: fixtureJobs, meta: { total: 2 } });
      }
      if (url === "/api/v1/discovery/disc-102/dismiss" && options?.method === "POST") {
        return Response.json({
          success: true,
          discovered: { ...fixtureJobs[1], status: "DISMISSED" },
        });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    await waitFor(() => {
      expect(screen.getByText("Junior Manual QA ↗")).toBeInTheDocument();
    });

    const dismissButtons = screen.getAllByRole("button", { name: /✕ Пропустить/i });
    fireEvent.click(dismissButtons[1]); // dismiss second job

    await waitFor(() => {
      expect(screen.queryByText("Junior Manual QA ↗")).not.toBeInTheDocument();
    });
  });

  it("aborts previous pending list request when switching tabs", async () => {
    const signals: AbortSignal[] = [];
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?")) {
        if (options?.signal) {
          signals.push(options.signal);
        }
        return Response.json({ items: [], meta: { total: 0 } });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    const acceptedTab = screen.getByRole("button", { name: /В работе/i });
    fireEvent.click(acceptedTab);

    await waitFor(() => {
      expect(signals.length).toBeGreaterThanOrEqual(2);
    });

    expect(signals[0].aborted).toBe(true);
  });
});

import { cleanup, fireEvent, render, screen, waitFor } from "@testing-library/react";
import { afterEach, describe, expect, it, vi } from "vitest";

const pushMock = vi.fn();
vi.mock("next/navigation", () => ({
  useRouter: () => ({
    push: pushMock,
    replace: vi.fn(),
  }),
}));

import DiscoverInbox from "../discover-inbox";

afterEach(() => {
  cleanup();
  vi.restoreAllMocks();
});

const mockJobs = [
  {
    id: "job-01",
    portal: "habr",
    external_id: "101",
    title: "Senior Platform Architect",
    company: "Fintech Cloud",
    location: "Remote",
    source_url: "https://career.habr.com/vacancies/101",
    salary_text: "$4500",
    snippet: "Highload core architecture with Go and Kubernetes.",
    description: "Full description of fintech platform role.",
    fit_score: 92,
    fit_level: "HIGH",
    fit_reasons: {
      matched_skills: ["Go", "Kubernetes", "PostgreSQL"],
      missing_skills: [],
      title_match: true,
      summary: "Идеальное соответствие стека",
    },
    status: "INBOX",
    security_threat_level: "CLEAN",
    published_at: "2026-10-31T10:00:00Z",
    created_at: "2026-10-31T10:05:00Z",
  },
  {
    id: "job-02",
    portal: "headhunter",
    external_id: "202",
    title: "Backend Specialist",
    company: "ScamCorp",
    location: "Москва",
    source_url: "https://hh.ru/vacancy/202",
    salary_text: "150000 RUB",
    snippet: "Suspicious job posting with obfuscated script tags.",
    description: "Suspicious description with hidden zero-width spaces.",
    fit_score: 35,
    fit_level: "LOW",
    fit_reasons: {
      matched_skills: ["PHP"],
      missing_skills: ["Go"],
      title_match: false,
      summary: "Частичное совпадение",
    },
    status: "INBOX",
    security_threat_level: "SUSPICIOUS",
    published_at: "2026-10-31T09:00:00Z",
    created_at: "2026-10-31T09:05:00Z",
  },
];

describe("Discover Inbox Keyboard Navigation & PostingGuard Telemetry", () => {
  it("renders PostingGuard telemetry pill with clean status and threat warning", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?")) {
        return Response.json({ items: mockJobs, meta: { total: 2 } });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    // PostingGuard Telemetry Pill: CLEAN
    expect(await screen.findByText(/\[ CLEAN: 0 threats · SSRF & Injection safe \]/)).toBeInTheDocument();
    expect(screen.getByText("🛡️ Clean")).toBeInTheDocument();

    // PostingGuard Telemetry Pill: SUSPICIOUS
    expect(screen.getByText(/\[ SUSPICIOUS: Flagged by PostingGuard \]/)).toBeInTheDocument();
    expect(screen.getByText("⚠️ SUSPICIOUS")).toBeInTheDocument();
  });

  it("renders keyboard shortcuts hint bar and button badges", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?")) {
        return Response.json({ items: mockJobs, meta: { total: 2 } });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    expect(await screen.findByRole("region", { name: "Горячие клавиши ленты" })).toBeInTheDocument();
    expect(screen.getAllByText(/В работу/).length).toBeGreaterThanOrEqual(1);
    expect(screen.getAllByText(/Пропустить/).length).toBeGreaterThanOrEqual(1);
    expect(screen.getByText(/Открыть сплит/)).toBeInTheDocument();

    // 1-Click Split jump link
    const splitLinks = screen.getAllByRole("link", { name: /↔ Сплит/ });
    expect(splitLinks.length).toBe(2);
    expect(splitLinks[0]).toHaveAttribute("href", "/vacancies/job-01#split");
  });

  it("navigates cards with arrow keys and accepts card with 'A' shortcut", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input, options) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?")) {
        return Response.json({ items: mockJobs, meta: { total: 2 } });
      }
      if (url === "/api/v1/discovery/job-01/accept" && options?.method === "POST") {
        return Response.json({
          success: true,
          discovered: { ...mockJobs[0], status: "ACCEPTED" },
          vacancy: { id: "vac-core-42" },
        });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    await screen.findByText("Senior Platform Architect ↗");

    // First card should be active by default
    const activeIndicators = screen.getAllByText("[ Активная ]");
    expect(activeIndicators.length).toBe(1);

    // Press ArrowDown to navigate to second card
    fireEvent.keyDown(window, { key: "ArrowDown" });

    // Press ArrowUp to navigate back to first card
    fireEvent.keyDown(window, { key: "ArrowUp" });

    // Press 'a' key to accept first card
    fireEvent.keyDown(window, { key: "a" });

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledWith(
        "/api/v1/discovery/job-01/accept",
        expect.objectContaining({ method: "POST" })
      );
    });
  });

  it("does not trigger keyboard shortcuts when typing in search input", async () => {
    const fetchMock = vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?")) {
        return Response.json({ items: mockJobs, meta: { total: 2 } });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    await screen.findByText("Senior Platform Architect ↗");

    const searchInput = screen.getByRole("textbox", { name: /Ключевые слова/i });
    searchInput.focus();

    // Type 'a' inside input
    fireEvent.keyDown(searchInput, { key: "a" });

    // Accept should NOT have been called
    expect(fetchMock).not.toHaveBeenCalledWith(
      expect.stringContaining("/accept"),
      expect.anything()
    );
  });

  it("navigates to split workspace on Enter key", async () => {
    vi.spyOn(globalThis, "fetch").mockImplementation(async (input) => {
      const url = String(input);
      if (url.includes("/api/v1/discovery?")) {
        return Response.json({ items: mockJobs, meta: { total: 2 } });
      }
      throw new Error(`Unexpected url: ${url}`);
    });

    render(<DiscoverInbox />);

    await screen.findByText("Senior Platform Architect ↗");

    // Press Enter on focused item (job-01)
    fireEvent.keyDown(window, { key: "Enter" });

    expect(pushMock).toHaveBeenCalledWith("/vacancies/job-01#split");
  });
});

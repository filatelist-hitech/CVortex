import { renderHook, act } from "@testing-library/react";
import { describe, expect, it, vi, beforeEach, afterEach } from "vitest";
import { useProvenanceLinker } from "../application/use-provenance-linker";
import type { VacancyDetail } from "../vacancy-workspace";

const mockDetail: VacancyDetail = {
  id: "vac-1",
  title: "Staff Backend Engineer",
  company: "Apex Tech",
  source_url: null,
  source_type: "PASTED_TEXT",
  analysis_status: "COMPLETED",
  error_code: null,
  snapshot_version: 1,
  recommendation: "APPLY",
  analysis_stale: false,
  analysis_run_stale: false,
  snapshot: {
    id: "snap-1",
    version: 1,
    raw_text: "Raw vacancy text",
    source_url: null,
    imported_at: "2026-10-31T00:00:00Z",
  },
  requirements: [
    {
      id: "req-arch",
      dimension: "EXPERIENCE",
      importance: "MANDATORY",
      label: "Distributed Architecture",
      source_excerpt: "Handled 50k QPS",
      confidence: 0.95,
    },
    {
      id: "req-python",
      dimension: "TECHNICAL",
      importance: "MANDATORY",
      label: "Python Core",
      source_excerpt: "FastAPI and asyncio",
      confidence: 0.9,
    },
  ],
  analysis: {
    recommendation: "APPLY",
    key_reasons: ["Strong architecture experience"],
    material_gaps: [],
    uncertainties: [],
    stale: false,
    dimensions: [
      {
        dimension: "EXPERIENCE",
        result: "MATCH",
        explanation: "5+ years backend",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: ["req-arch", "req-distributed"],
        candidate_evidence: [
          {
            type: "CareerFact",
            id: "fact-101",
            statement: "Designed multi-region Postgres",
            status: "CONFIRMED",
          },
        ],
      },
      {
        dimension: "TECHNICAL",
        result: "MATCH",
        explanation: "Python & Docker",
        origin: "DETERMINISTIC",
        vacancy_requirement_ids: ["req-python"],
        candidate_evidence: [
          {
            type: "CareerFact",
            id: "fact-102",
            statement: "Built async services",
            status: "CONFIRMED",
          },
        ],
      },
    ],
  },
};

describe("useProvenanceLinker", () => {
  beforeEach(() => {
    document.body.innerHTML = "";
  });

  afterEach(() => {
    document.body.innerHTML = "";
  });

  it("initializes with empty activeLink and inactive highlight queries", () => {
    const { result } = renderHook(() => useProvenanceLinker(mockDetail));

    expect(result.current.activeLink.requirementId).toBeNull();
    expect(result.current.activeLink.provenanceId).toBeNull();
    expect(result.current.activeLink.factId).toBeNull();
    expect(result.current.isRequirementHighlighted("req-arch")).toBe(false);
    expect(result.current.isProvenanceHighlighted("prov-1")).toBe(false);
    expect(result.current.isFactHighlighted("fact-101")).toBe(false);
  });

  it("resolves linked facts and requirements correctly from vacancy dimensions", () => {
    const { result } = renderHook(() => useProvenanceLinker(mockDetail));

    expect(result.current.getLinkedFactIds("req-arch")).toEqual(["fact-101"]);
    expect(result.current.getLinkedFactIds("req-python")).toEqual(["fact-102"]);
    expect(result.current.getLinkedFactIds("non-existent")).toEqual([]);

    expect(result.current.getLinkedReqIdsForFact("fact-101")).toEqual(["req-arch", "req-distributed"]);
    expect(result.current.getLinkedReqIdsForFact("fact-102")).toEqual(["req-python"]);
  });

  it("handles hoverRequirement and activates bi-directional fact highlighting", () => {
    const { result } = renderHook(() => useProvenanceLinker(mockDetail));

    act(() => {
      result.current.hoverRequirement("req-arch");
    });

    expect(result.current.activeLink.requirementId).toBe("req-arch");
    expect(result.current.activeLink.factId).toBe("fact-101");
    expect(result.current.activeLink.sourceType).toBe("requirement");
    expect(result.current.isRequirementHighlighted("req-arch")).toBe(true);
    expect(result.current.isFactHighlighted("fact-101")).toBe(true);
    expect(result.current.isFactHighlighted("fact-102")).toBe(false);

    // Unhover
    act(() => {
      result.current.hoverRequirement(null);
    });

    expect(result.current.activeLink.requirementId).toBeNull();
    expect(result.current.isRequirementHighlighted("req-arch")).toBe(false);
    expect(result.current.isFactHighlighted("fact-101")).toBe(false);
  });

  it("handles hoverFact and links back to parent requirements", () => {
    const { result } = renderHook(() => useProvenanceLinker(mockDetail));

    act(() => {
      result.current.hoverFact("fact-102");
    });

    expect(result.current.activeLink.factId).toBe("fact-102");
    expect(result.current.activeLink.requirementId).toBe("req-python");
    expect(result.current.activeLink.sourceType).toBe("fact");
    expect(result.current.isRequirementHighlighted("req-python")).toBe(true);
    expect(result.current.isFactHighlighted("fact-102")).toBe(true);
  });

  it("synchronizes DOM classes with provenance-active-highlight", () => {
    const reqEl = document.createElement("div");
    reqEl.setAttribute("data-req-id", "req-arch");
    document.body.appendChild(reqEl);

    const factEl = document.createElement("aside");
    factEl.setAttribute("data-fact-id", "fact-101");
    document.body.appendChild(factEl);

    const otherEl = document.createElement("div");
    otherEl.setAttribute("data-req-id", "req-python");
    document.body.appendChild(otherEl);

    const { result } = renderHook(() => useProvenanceLinker(mockDetail));

    act(() => {
      result.current.hoverRequirement("req-arch");
    });

    expect(reqEl.classList.contains("provenance-active-highlight")).toBe(true);
    expect(factEl.classList.contains("provenance-active-highlight")).toBe(true);
    expect(otherEl.classList.contains("provenance-active-highlight")).toBe(false);

    act(() => {
      result.current.hoverRequirement(null);
    });

    expect(reqEl.classList.contains("provenance-active-highlight")).toBe(false);
    expect(factEl.classList.contains("provenance-active-highlight")).toBe(false);
  });

  it("scrollToLinked finds element and triggers smooth scroll and focus", () => {
    const target = document.createElement("div");
    target.setAttribute("data-req-id", "target-req");
    target.scrollIntoView = vi.fn();
    target.focus = vi.fn();
    document.body.appendChild(target);

    const { result } = renderHook(() => useProvenanceLinker(mockDetail));

    act(() => {
      result.current.scrollToLinked("target-req", "requirement");
    });

    expect(target.scrollIntoView).toHaveBeenCalledWith({ behavior: "smooth", block: "nearest" });
    expect(target.focus).toHaveBeenCalledWith({ preventScroll: true });
  });
});

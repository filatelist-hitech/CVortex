"use client";

import { useCallback, useEffect, useState } from "react";
import type { VacancyDetail } from "../vacancy-workspace";

export type ProvenanceLinkType = "requirement" | "provenance" | "fact";

export interface ActiveProvenanceLink {
  requirementId: string | null;
  provenanceId: string | null;
  factId: string | null;
  sourceType: ProvenanceLinkType | null;
}

export function useProvenanceLinker(detail?: VacancyDetail | null) {
  const [activeLink, setActiveLink] = useState<ActiveProvenanceLink>({
    requirementId: null,
    provenanceId: null,
    factId: null,
    sourceType: null,
  });

  // Resolve which requirement matches which fact from detail dimensions
  const getLinkedFactIds = useCallback((reqId: string): string[] => {
    if (!detail?.analysis?.dimensions) return [];
    const matchedDim = detail.analysis.dimensions.find(d =>
      d.vacancy_requirement_ids?.includes(reqId)
    );
    if (!matchedDim?.candidate_evidence) return [];
    return matchedDim.candidate_evidence.map(ev => ev.id);
  }, [detail]);

  const getLinkedReqIdsForFact = useCallback((factId: string): string[] => {
    if (!detail?.analysis?.dimensions) return [];
    const matchedDim = detail.analysis.dimensions.find(d =>
      d.candidate_evidence?.some(ev => ev.id === factId)
    );
    return matchedDim?.vacancy_requirement_ids ?? [];
  }, [detail]);

  const hoverRequirement = useCallback((reqId: string | null) => {
    if (!reqId) {
      setActiveLink({ requirementId: null, provenanceId: null, factId: null, sourceType: null });
      return;
    }
    const linkedFacts = getLinkedFactIds(reqId);
    setActiveLink({
      requirementId: reqId,
      provenanceId: reqId,
      factId: linkedFacts[0] ?? null,
      sourceType: "requirement",
    });
  }, [getLinkedFactIds]);

  const hoverProvenance = useCallback((provenanceId: string | null) => {
    if (!provenanceId) {
      setActiveLink({ requirementId: null, provenanceId: null, factId: null, sourceType: null });
      return;
    }
    setActiveLink({
      requirementId: provenanceId,
      provenanceId,
      factId: getLinkedFactIds(provenanceId)[0] ?? null,
      sourceType: "provenance",
    });
  }, [getLinkedFactIds]);

  const hoverFact = useCallback((factId: string | null) => {
    if (!factId) {
      setActiveLink({ requirementId: null, provenanceId: null, factId: null, sourceType: null });
      return;
    }
    const linkedReqs = getLinkedReqIdsForFact(factId);
    setActiveLink({
      requirementId: linkedReqs[0] ?? null,
      provenanceId: linkedReqs[0] ?? null,
      factId,
      sourceType: "fact",
    });
  }, [getLinkedReqIdsForFact]);

  const isRequirementHighlighted = useCallback((reqId: string): boolean => {
    if (!activeLink.requirementId) return false;
    return activeLink.requirementId === reqId;
  }, [activeLink.requirementId]);

  const isProvenanceHighlighted = useCallback((provId: string): boolean => {
    if (!activeLink.provenanceId) return false;
    return activeLink.provenanceId === provId;
  }, [activeLink.provenanceId]);

  const isFactHighlighted = useCallback((factId: string): boolean => {
    if (!activeLink.factId && !activeLink.requirementId) return false;
    if (activeLink.factId === factId) return true;
    if (activeLink.requirementId) {
      const linked = getLinkedFactIds(activeLink.requirementId);
      return linked.includes(factId);
    }
    return false;
  }, [activeLink.factId, activeLink.requirementId, getLinkedFactIds]);

  const scrollToLinked = useCallback((id: string, type: ProvenanceLinkType) => {
    if (typeof document === "undefined") return;
    const selector =
      type === "requirement"
        ? `[data-req-id="${id}"]`
        : type === "provenance"
        ? `[data-provenance="${id}"]`
        : `[data-fact-id="${id}"]`;
    const target = document.querySelector<HTMLElement>(selector);
    if (target) {
      target.scrollIntoView({ behavior: "smooth", block: "nearest" });
      target.focus({ preventScroll: true });
    }
  }, []);

  // Update DOM classes for active highlighting with CSS transition without rerendering everything
  useEffect(() => {
    if (typeof document === "undefined") return;
    const allLinked = document.querySelectorAll<HTMLElement>("[data-req-id], [data-provenance], [data-fact-id]");
    allLinked.forEach(el => {
      const reqId = el.getAttribute("data-req-id");
      const provId = el.getAttribute("data-provenance");
      const factId = el.getAttribute("data-fact-id");

      let highlighted = false;
      if (reqId && isRequirementHighlighted(reqId)) highlighted = true;
      if (provId && isProvenanceHighlighted(provId)) highlighted = true;
      if (factId && isFactHighlighted(factId)) highlighted = true;

      if (highlighted) {
        el.classList.add("provenance-active-highlight");
      } else {
        el.classList.remove("provenance-active-highlight");
      }
    });
  }, [activeLink, isRequirementHighlighted, isProvenanceHighlighted, isFactHighlighted]);

  return {
    activeLink,
    hoverRequirement,
    hoverProvenance,
    hoverFact,
    isRequirementHighlighted,
    isProvenanceHighlighted,
    isFactHighlighted,
    scrollToLinked,
    getLinkedFactIds,
    getLinkedReqIdsForFact,
  };
}

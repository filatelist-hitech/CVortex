"use client";

import { useCallback, useEffect, useState } from "react";
import type { VacancyDetail } from "../vacancy-workspace";
import type { MarginaliaFact } from "./marginalia";

export type ProvenanceLinkType = "requirement" | "provenance" | "fact";

export interface ActiveProvenanceLink {
  requirementId: string | null;
  provenanceId: string | null;
  factId: string | null;
  sourceType: ProvenanceLinkType | null;
}

export function useProvenanceLinker(
  detail?: VacancyDetail | null,
  fallbackFacts?: MarginaliaFact[]
) {
  const [activeLink, setActiveLink] = useState<ActiveProvenanceLink>({
    requirementId: null,
    provenanceId: null,
    factId: null,
    sourceType: null,
  });

  // Resolve which requirement matches which fact from detail dimensions and fallback/demo facts
  const getLinkedFactIds = useCallback(
    (reqId: string): string[] => {
      const factIds = new Set<string>();

      if (detail?.analysis?.dimensions) {
        detail.analysis.dimensions.forEach((dim) => {
          if (dim.vacancy_requirement_ids?.includes(reqId)) {
            dim.candidate_evidence?.forEach((ev) => factIds.add(ev.id));
          }
        });
      }

      if (fallbackFacts?.length) {
        fallbackFacts.forEach((f) => {
          if (f.linkedReqId === reqId) {
            factIds.add(f.id);
          }
        });
      }

      return Array.from(factIds);
    },
    [detail, fallbackFacts]
  );

  const getLinkedReqIdsForFact = useCallback(
    (factId: string): string[] => {
      const reqIds = new Set<string>();

      if (detail?.analysis?.dimensions) {
        detail.analysis.dimensions.forEach((dim) => {
          if (dim.candidate_evidence?.some((ev) => ev.id === factId)) {
            dim.vacancy_requirement_ids?.forEach((rid) => reqIds.add(rid));
          }
        });
      }

      if (fallbackFacts?.length) {
        fallbackFacts.forEach((f) => {
          if (f.id === factId && f.linkedReqId) {
            reqIds.add(f.linkedReqId);
          }
        });
      }

      return Array.from(reqIds);
    },
    [detail, fallbackFacts]
  );

  const hoverRequirement = useCallback(
    (reqId: string | null) => {
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
    },
    [getLinkedFactIds]
  );

  const hoverProvenance = useCallback(
    (provenanceId: string | null) => {
      if (!provenanceId) {
        setActiveLink({ requirementId: null, provenanceId: null, factId: null, sourceType: null });
        return;
      }
      const linkedFacts = getLinkedFactIds(provenanceId);
      setActiveLink({
        requirementId: provenanceId,
        provenanceId,
        factId: linkedFacts[0] ?? null,
        sourceType: "provenance",
      });
    },
    [getLinkedFactIds]
  );

  const hoverFact = useCallback(
    (factId: string | null) => {
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
    },
    [getLinkedReqIdsForFact]
  );

  const isRequirementHighlighted = useCallback(
    (reqId: string): boolean => {
      if (!activeLink.requirementId && !activeLink.factId) return false;
      if (activeLink.requirementId === reqId) return true;
      if (activeLink.factId) {
        const linkedReqs = getLinkedReqIdsForFact(activeLink.factId);
        return linkedReqs.includes(reqId);
      }
      return false;
    },
    [activeLink.requirementId, activeLink.factId, getLinkedReqIdsForFact]
  );

  const isProvenanceHighlighted = useCallback(
    (provId: string): boolean => {
      if (!activeLink.provenanceId && !activeLink.requirementId && !activeLink.factId) return false;
      if (activeLink.provenanceId === provId || activeLink.requirementId === provId) return true;
      if (activeLink.factId) {
        const linkedReqs = getLinkedReqIdsForFact(activeLink.factId);
        return linkedReqs.includes(provId);
      }
      return false;
    },
    [activeLink.provenanceId, activeLink.requirementId, activeLink.factId, getLinkedReqIdsForFact]
  );

  const isFactHighlighted = useCallback(
    (factId: string): boolean => {
      if (!activeLink.factId && !activeLink.requirementId && !activeLink.provenanceId) return false;
      if (activeLink.factId === factId) return true;
      const targetReq = activeLink.requirementId || activeLink.provenanceId;
      if (targetReq) {
        const linked = getLinkedFactIds(targetReq);
        return linked.includes(factId);
      }
      return false;
    },
    [activeLink.factId, activeLink.requirementId, activeLink.provenanceId, getLinkedFactIds]
  );

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
    const allLinked = document.querySelectorAll<HTMLElement>(
      "[data-req-id], [data-provenance], [data-fact-id]"
    );
    allLinked.forEach((el) => {
      const reqId = el.getAttribute("data-req-id");
      const provId = el.getAttribute("data-provenance");
      const factId = el.getAttribute("data-fact-id");

      let highlighted = false;
      if (reqId && isRequirementHighlighted(reqId)) highlighted = true;
      if (provId && isProvenanceHighlighted(provId)) highlighted = true;
      if (factId && isFactHighlighted(factId)) highlighted = true;

      if (highlighted) {
        el.classList.add("provenance-active-highlight");
        if (reqId) el.classList.add("is-linked-highlight");
        if (factId) el.classList.add("is-highlighted");
      } else {
        el.classList.remove("provenance-active-highlight");
        if (reqId) el.classList.remove("is-linked-highlight");
        if (factId) el.classList.remove("is-highlighted");
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

"use client";

import { createContext, useCallback, useContext, useEffect, useRef, useState, useSyncExternalStore, type ReactNode } from "react";
import { api } from "../access-shell";
import type { VacancyDetail, VacancySummary } from "../vacancy-workspace";
import { readLocation, vacancyView, sections, sectionLabels, scenarios, scenarioLabels, type Level, type Scenario, type Section, type WorkspaceLocation, type WorkspaceVacancy } from "./model";

type DemoModule = typeof import("./demo");
type Context = ReturnType<typeof useController>;
const WorkspaceContext = createContext<Context | null>(null);
const subscribeMedia = (callback: () => void) => {
  const queries = [window.matchMedia("(max-width: 767px)"), window.matchMedia("(max-width: 1023px)"), window.matchMedia("(max-width: 1439px)")];
  queries.forEach(query => query.addEventListener("change", callback));
  return () => queries.forEach(query => query.removeEventListener("change", callback));
};
const viewport = () => window.innerWidth < 768 ? "mobile" : window.innerWidth < 1024 ? "tablet" : window.innerWidth < 1440 ? "narrow" : "desktop";

export function demoAllowed() {
  return process.env.NEXT_PUBLIC_CVORTEX_WORKSPACE_DEMO === "1" &&
    ["127.0.0.1", "localhost", "[::1]"].includes(window.location.hostname) &&
    new URLSearchParams(window.location.search).get("demo") === "b01";
}

function useController(initialVacancyId?: string) {
  const size = useSyncExternalStore(subscribeMedia, viewport, () => "desktop");
  const [demo, setDemo] = useState<DemoModule | null>(null);
  const [initialized, setInitialized] = useState(false);
  const [location, setLocation] = useState<WorkspaceLocation>({ selected: "", section: "materials", scenario: "normal", level: "list" });
  const [vacancies, setVacancies] = useState<WorkspaceVacancy[]>([]);
  const [detail, setDetail] = useState<VacancyDetail | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [query, setQuery] = useState("");
  const [inspector, setInspector] = useState(false);
  const [listOpen, setListOpen] = useState(false);
  const [pinned, setPinned] = useState(false);
  const [accepted, setAccepted] = useState(false);
  const [approved, setApproved] = useState(false);
  const [rejected, setRejected] = useState(false);
  const [proposal, setProposal] = useState("");
  const [editing, setEditing] = useState(false);
  const [dirtyDrafts, setDirtyDrafts] = useState<Record<string, boolean>>({});
  const [editText, setEditText] = useState("");
  const [status, setStatus] = useState("");
  const [alert, setAlert] = useState(false);
  const [announcement, announce] = useState("");
  const root = useRef<HTMLDivElement>(null);
  const evidenceTrigger = useRef<HTMLElement | null>(null);
  const locationRef = useRef(location);
  const editingRef = useRef(editing);
  const version = useRef(0);
  const dirty = editing || Object.values(dirtyDrafts).some(Boolean);
  useEffect(() => { locationRef.current = location; editingRef.current = dirty; }, [location, dirty]);
  const setDraftDirty = useCallback((id: string, value: boolean) => {
    if (!value) setStatus("");
    setDirtyDrafts(current => Boolean(current[id]) === value ? current : { ...current, [id]: value });
  }, []);
  const focus = useCallback((id: string) => { requestAnimationFrame(() => (root.current?.querySelector<HTMLElement>(`#${id}`) ?? (id === "proposal-edit" ? root.current?.querySelector<HTMLElement>("[data-draft-edit]") : null))?.focus()); }, []);

  const loadDetail = useCallback(async (id: string, preserve = false) => {
    const current = ++version.current;
    if (!preserve) { setDetail(null); setLoading(true); }
    setError("");
    try {
      const result = await api(`/api/v1/vacancies/${encodeURIComponent(id)}`);
      if (current === version.current) setDetail(result.data);
    } catch (caught) { if (current === version.current) setError(caught instanceof Error ? caught.message : "Не удалось открыть вакансию."); }
    finally { if (current === version.current) setLoading(false); }
  }, []);

  useEffect(() => {
    let active = true;
    const initial = readLocation();
    function initialize(next: WorkspaceLocation, demoMode: boolean) {
      const params = new URLSearchParams(window.location.search);
      params.set("opportunity", next.selected); params.set("level", next.level);
      if (demoMode) params.set("scenario", next.scenario); else params.delete("scenario");
      window.history.replaceState({ workspace: next }, "", `${window.location.pathname}?${params}#${next.section}`);
      setLocation(next); setInspector(next.level === "context");
    }
    if (demoAllowed()) {
      void import("./demo").then(module => {
        if (!active) return;
        const selected = initial.selected === "cedar" ? "cedar" : "northstar";
        setDemo(module); setVacancies(module.demoVacancies); initialize({ ...initial, selected }, true);
        setProposal(selected === "cedar" ? "Готов к гибридной работе в Москве." : module.defaultProposal);
        setAccepted(initial.scenario === "approval" && selected === "northstar"); setLoading(false); setInitialized(true);
      });
    } else {
      void api("/api/v1/vacancies").then(result => {
        if (!active) return;
        const items = result.data as VacancySummary[];
        setVacancies(items.map(vacancyView)); setInitialized(true);
        const selected = (initialVacancyId && items.some(item => item.id === initialVacancyId))
          ? initialVacancyId
          : items.some(item => item.id === initial.selected)
          ? initial.selected
          : (initialVacancyId || items[0]?.id) ?? "";
        const section = initialVacancyId && !window.location.hash ? "split" : initial.section;
        initialize({ ...initial, selected, section, scenario: "normal" }, false);
        if (selected) void loadDetail(selected); else setLoading(false);
      }).catch(caught => { if (active) { setError(caught instanceof Error ? caught.message : "Не удалось загрузить вакансии."); setLoading(false); setInitialized(true); } });
    }
    return () => { active = false; };
  }, [loadDetail, initialVacancyId]);

  function remember(next: WorkspaceLocation, replace = false) {
    const params = new URLSearchParams(window.location.search);
    params.set("opportunity", next.selected); params.set("level", next.level);
    if (demo) params.set("scenario", next.scenario); else params.delete("scenario");
    window.history[replace ? "replaceState" : "pushState"]({ workspace: next }, "", `${window.location.pathname}?${params}#${next.section}`);
    setLocation(next);
  }
  function allowNavigation() {
    if (!editingRef.current) return true;
    setStatus(demo ? "Есть несохранённая правка. Сохраните текст с подтверждениями или отмените правку, прежде чем менять вакансию или состояние макета." : "Есть несохранённая правка. Сохраните или отмените её перед переходом.");
    focus("proposal-edit"); announce("Текст остался в поле ввода. Сохраните или отмените правку перед переходом."); return false;
  }
  function resetReview(id: string, scenario: Scenario) {
    setAccepted(Boolean(demo && scenario === "approval" && id === "northstar")); setApproved(false); setRejected(false);
    setProposal(id === "cedar" ? "Готов к гибридной работе в Москве." : demo?.defaultProposal ?? "");
    setEditing(false); setStatus(""); setAlert(false); setPinned(false); setInspector(false); setListOpen(false);
  }
  function select(id: string) {
    if (!allowNavigation() || !vacancies.some(item => item.id === id)) return;
    resetReview(id, location.scenario); remember({ ...location, selected: id, level: "work" });
    if (!demo) void loadDetail(id);
    if (size === "mobile") focus("workspace-title");
    announce(`Выбрана компания ${vacancies.find(item => item.id === id)?.company}. Предыдущие подтверждения закрыты, закрепление сброшено.`);
  }
  function setSection(section: Section) {
    if (!sections.includes(section) || (!demo && !allowNavigation())) return;
    remember({ ...location, section });
    announce(`Раздел «${sectionLabels[sections.indexOf(section)]}», компания ${item?.company}.`);
  }
  function setScenario(scenario: Scenario) {
    if (!demo || !scenarios.includes(scenario) || !allowNavigation()) return;
    resetReview(location.selected, scenario); remember({ ...location, scenario, level: "work" });
    announce(`Состояние макета: ${scenarioLabels[scenarios.indexOf(scenario)]}. Запросы к API и ИИ не отправляются.`);
  }
  function closeEvidence(returnFocus = true) {
    setInspector(false);
    if (size === "mobile") remember({ ...location, level: "work" });
    if (returnFocus) requestAnimationFrame(() => evidenceTrigger.current?.focus());
    announce("Панель подтверждений закрыта.");
  }
  function openEvidence(trigger: HTMLElement) {
    evidenceTrigger.current = trigger; setListOpen(false); setInspector(true);
    if (size === "mobile") remember({ ...location, level: "context" });
    focus("inspector-title"); announce(demo ? "Открыты подтверждения для выбранной вымышленной вакансии." : "Открыты подтверждения для выбранной вакансии.");
  }
  function setLevel(level: Level) {
    if (!allowNavigation()) return;
    remember({ ...location, level });
    if (size !== "mobile") return;
    requestAnimationFrame(() => {
      const selected = [...(root.current?.querySelectorAll<HTMLButtonElement>("[data-opportunity]:not([hidden])") ?? [])].find(element => element.dataset.opportunity === location.selected);
      (level === "list" ? selected ?? root.current?.querySelector<HTMLElement>("#list-title") : root.current?.querySelector<HTMLElement>(level === "context" ? "#inspector-title" : "#workspace-title"))?.focus();
    });
  }
  function closeList(returnFocus = true) { setListOpen(false); if (returnFocus) focus("open-list"); }
  function accept() { if (safe && !accepted && !rejected && !approved) { setAccepted(true); focus("approve"); announce("Правка добавлена в вымышленный черновик. Текст нужно одобрить отдельно."); } }
  function approve() { if (safe && accepted && !approved && !rejected) { setApproved(true); setStatus("Этот вымышленный текст одобрен в макете. Отклик не отправлен."); focus("review-state"); announce("Одобрение сохранено только в открытом макете. Отклик не отправлен."); } }
  function reject() { if (demo && !editing && !approved && !rejected && !unavailable) { setRejected(true); setAccepted(false); setStatus("Правка отклонена. Прежний текст сохранён; отклик не отправлен."); focus("review-state"); announce("Правка отклонена."); } }
  function edit() { if (safe && !accepted && !approved && !rejected) { setEditing(true); setEditText(proposal); focus("proposal-edit"); } }
  function saveEdit() {
    if (!demo || !editing) return;
    if (!demo.supportedProposals.includes(editText)) { setAlert(true); setStatus("Правка заблокирована: для этого текста в примере нет подтверждений. Верните подтверждённую формулировку."); return; }
    setProposal(editText); setAccepted(false); setEditing(false); setAlert(false);
    setStatus("Текст с подтверждениями сохранён для проверки. Он ещё не одобрен."); focus("accept");
  }
  const effective: Scenario = demo ? location.selected === "cedar" && ["normal", "approval"].includes(location.scenario) ? "blocked" : location.scenario
    : loading || (detail && ["PENDING", "RUNNING"].includes(detail.analysis_status)) ? "loading" : error || detail?.analysis_status === "FAILED" ? "failed" : detail?.analysis_stale || detail?.analysis_run_stale || detail?.analysis?.stale ? "stale" : !detail?.analysis ? "empty" : "normal";
  const unavailable = ["loading", "empty", "failed"].includes(effective);
  const eligible = Boolean(demo && location.selected === "northstar" && ["normal", "approval"].includes(effective));
  const safe = eligible && !editing;
  const item = vacancies.find(item => item.id === location.selected);

  useEffect(() => {
    if (demo || !detail || !["PENDING", "RUNNING"].includes(detail.analysis_status)) return;
    const timer = window.setInterval(() => void loadDetail(location.selected, true), 1500);
    return () => window.clearInterval(timer);
  }, [demo, detail, location.selected, loadDetail]);

  useEffect(() => {
    const restore = () => {
      if (editingRef.current) {
        const params = new URLSearchParams(window.location.search); const current = locationRef.current;
        params.set("opportunity", current.selected); params.set("level", current.level); if (demo) params.set("scenario", current.scenario);
        window.history.pushState({ workspace: current }, "", `${window.location.pathname}?${params}#${current.section}`);
        setStatus("Есть несохранённая правка. Сохраните или отмените её перед переходом."); focus("proposal-edit"); return;
      }
      const next = readLocation();
      if (!vacancies.some(item => item.id === next.selected)) return;
      const changed = next.selected !== locationRef.current.selected || next.scenario !== locationRef.current.scenario;
      if (changed) {
        setPinned(false); setAccepted(Boolean(demo && next.scenario === "approval" && next.selected === "northstar")); setApproved(false); setRejected(false);
        setProposal(next.selected === "cedar" ? "Готов к гибридной работе в Москве." : demo?.defaultProposal ?? "");
        setStatus(""); if (!demo) void loadDetail(next.selected);
      }
      setListOpen(false); setInspector(next.level === "context"); setLocation(next);
    };
    window.addEventListener("popstate", restore); window.addEventListener("hashchange", restore);
    return () => { window.removeEventListener("popstate", restore); window.removeEventListener("hashchange", restore); };
  }, [demo, vacancies, focus, loadDetail]);

  useEffect(() => {
    const beforeUnload = (event: BeforeUnloadEvent) => { if (editingRef.current) event.preventDefault(); };
    window.addEventListener("beforeunload", beforeUnload); return () => window.removeEventListener("beforeunload", beforeUnload);
  }, []);

  return { root, demo, initialized, size, location, vacancies, item, detail, loading, error, query, setQuery, inspector, listOpen, pinned,
    setPinned, accepted, approved, rejected, proposal, editing, editText, setEditText, status, alert, announcement,
    effective, unavailable, eligible, safe, focus, select, setSection, setScenario, openEvidence, closeEvidence, closeList, setDraftDirty,
    openList: () => { setInspector(false); setListOpen(true); focus("list-title"); }, setLevel, accept, approve, reject, edit, saveEdit,
    cancelEdit: () => { setEditing(false); focus("edit"); }, allowNavigation };
}

export function WorkspaceProvider({ children, initialVacancyId }: { children: ReactNode; initialVacancyId?: string }) {
  const value = useController(initialVacancyId);
  return <WorkspaceContext.Provider value={value}>{children}</WorkspaceContext.Provider>;
}
export function useWorkspace() { const context = useContext(WorkspaceContext); if (!context) throw new Error("Workspace provider is missing"); return context; }

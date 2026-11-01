"use client";

import { FormEvent, useCallback, useEffect, useRef, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { api } from "./access-shell";

export type DiscoveredVacancyItem = {
  id: string;
  portal: "freehire" | "headhunter" | "habr" | "linkedin" | string;
  external_id: string | null;
  title: string;
  company: string;
  location: string | null;
  source_url: string;
  salary_text: string | null;
  snippet: string | null;
  description: string | null;
  fit_score: number;
  fit_level: "HIGH" | "MEDIUM" | "LOW" | "UNKNOWN";
  fit_reasons: {
    matched_skills?: string[];
    missing_skills?: string[];
    title_match?: boolean;
    summary?: string;
  } | null;
  status: "INBOX" | "ACCEPTED" | "DISMISSED";
  security_threat_level: "CLEAN" | "SUSPICIOUS" | "MALICIOUS";
  published_at: string | null;
  created_at: string;
};

const PORTAL_LABELS: Record<string, { name: string; color: string; badgeClass: string }> = {
  headhunter: { name: "HeadHunter", color: "#e1011c", badgeClass: "portal-hh" },
  hh: { name: "HeadHunter", color: "#e1011c", badgeClass: "portal-hh" },
  habr: { name: "Хабр Карьера", color: "#65a3be", badgeClass: "portal-habr" },
  linkedin: { name: "LinkedIn", color: "#0077b5", badgeClass: "portal-linkedin" },
  freehire: { name: "FreeHire (50+ ATS)", color: "#10b981", badgeClass: "portal-freehire" },
};

export default function DiscoverInbox() {
  const router = useRouter();
  const [query, setQuery] = useState("Senior Laravel");
  const [location, setLocation] = useState("");
  const [remoteOnly, setRemoteOnly] = useState(true);
  const [portals, setPortals] = useState<string[]>(["freehire", "habr", "headhunter", "linkedin"]);
  const [searching, setSearching] = useState(false);
  const [loadingList, setLoadingList] = useState(false);
  const [activeTab, setActiveTab] = useState<"INBOX" | "ACCEPTED" | "DISMISSED">("INBOX");
  const [portalFilter, setPortalFilter] = useState("");
  const [items, setItems] = useState<DiscoveredVacancyItem[]>([]);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");
  const [actionId, setActionId] = useState<string | null>(null);
  const [acceptedMap, setAcceptedMap] = useState<Record<string, string>>({});
  const [focusedIndex, setFocusedIndex] = useState<number>(0);
  const activeAbortControllerRef = useRef<AbortController | null>(null);

  const safeFocusedIndex = items.length > 0 ? Math.min(Math.max(0, focusedIndex), items.length - 1) : 0;

  const loadList = useCallback(async (tab = activeTab, portal = portalFilter) => {
    if (activeAbortControllerRef.current) {
      activeAbortControllerRef.current.abort();
    }
    const controller = new AbortController();
    activeAbortControllerRef.current = controller;

    setLoadingList(true);
    setError("");
    try {
      const params = new URLSearchParams();
      params.set("status", tab);
      if (portal) params.set("portal", portal);

      const res = await api(`/api/v1/discovery?${params.toString()}`, {
        signal: controller.signal,
      });

      if (!controller.signal.aborted) {
        setItems(res.items || []);
      }
    } catch (caught) {
      if (caught instanceof Error && caught.name === "AbortError") {
        return;
      }
      if (!controller.signal.aborted) {
        setError(caught instanceof Error ? caught.message : "Не удалось загрузить вакансии");
      }
    } finally {
      if (activeAbortControllerRef.current === controller) {
        activeAbortControllerRef.current = null;
        setLoadingList(false);
      }
    }
  }, [activeTab, portalFilter]);

  useEffect(() => {
    // eslint-disable-next-line react-hooks/set-state-in-effect
    void loadList(activeTab, portalFilter);
  }, [loadList, activeTab, portalFilter]);

  useEffect(() => {
    return () => {
      if (activeAbortControllerRef.current) {
        activeAbortControllerRef.current.abort();
      }
    };
  }, []);

  const togglePortal = (portalName: string) => {
    setPortals((prev) =>
      prev.includes(portalName) ? prev.filter((p) => p !== portalName) : [...prev, portalName]
    );
  };

  const handleSearch = async (e: FormEvent) => {
    e.preventDefault();
    if (!query.trim()) return;

    setSearching(true);
    setError("");
    setSuccess("");

    try {
      const res = await api("/api/v1/discovery/search", {
        method: "POST",
        body: JSON.stringify({
          query: query.trim(),
          location: location.trim() || undefined,
          remote: remoteOnly,
          portals: portals.length > 0 ? portals : ["freehire", "habr", "headhunter", "linkedin"],
          limit: 25,
        }),
      });

      const count = res.count ?? 0;
      setSuccess(`Поиск завершён: обнаружено ${count} подходящих вакансий!`);
      if (activeTab === "INBOX") {
        await loadList("INBOX", portalFilter);
      } else {
        setActiveTab("INBOX");
      }
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Ошибка при поиске вакансий на порталах");
    } finally {
      setSearching(false);
    }
  };

  const handleAccept = async (item: DiscoveredVacancyItem) => {
    setActionId(item.id);
    setError("");
    try {
      const res = await api(`/api/v1/discovery/${item.id}/accept`, { method: "POST" });
      const vacancyId = res.vacancy?.id;
      if (vacancyId) {
        setAcceptedMap((prev) => ({ ...prev, [item.id]: vacancyId }));
      }
      setSuccess(`Вакансия «${item.title}» добавлена в пайплайн!`);
      // Update item in list
      setItems((prev) =>
        prev.map((i) => (i.id === item.id ? { ...i, status: "ACCEPTED" } : i))
      );
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Не удалось принять вакансию в пайплайн");
    } finally {
      setActionId(null);
    }
  };

  const handleDismiss = async (item: DiscoveredVacancyItem) => {
    setActionId(item.id);
    setError("");
    try {
      await api(`/api/v1/discovery/${item.id}/dismiss`, { method: "POST" });
      // Remove from list if in INBOX
      setItems((prev) => prev.filter((i) => i.id !== item.id));
    } catch (caught) {
      setError(caught instanceof Error ? caught.message : "Не удалось отклонить вакансию");
    } finally {
      setActionId(null);
    }
  };

  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      if (e.metaKey || e.ctrlKey || e.altKey) return;

      const target = e.target as HTMLElement | null;
      const isElement = typeof Element !== "undefined" && target instanceof Element;
      const isInput =
        isElement &&
        (target.tagName === "INPUT" ||
          target.tagName === "TEXTAREA" ||
          target.tagName === "SELECT" ||
          target.isContentEditable);

      if (isInput) return;

      const isInteractive =
        isElement &&
        (target.tagName === "BUTTON" ||
          target.tagName === "A" ||
          target.tagName === "SUMMARY" ||
          (typeof target.getAttribute === "function" && target.getAttribute("role") === "button"));

      if (e.key === "ArrowDown" || e.key === "j" || e.key === "J") {
        e.preventDefault();
        setFocusedIndex((prev) => Math.min(prev + 1, Math.max(0, items.length - 1)));
      } else if (e.key === "ArrowUp" || e.key === "k" || e.key === "K") {
        e.preventDefault();
        setFocusedIndex((prev) => Math.max(prev - 1, 0));
      } else if (e.key === "a" || e.key === "A" || e.key === "ф" || e.key === "Ф") {
        const item = items[safeFocusedIndex];
        if (item && item.status === "INBOX") {
          e.preventDefault();
          void handleAccept(item);
        }
      } else if (e.key === "s" || e.key === "S" || e.key === "ы" || e.key === "Ы") {
        const item = items[safeFocusedIndex];
        if (item && item.status === "INBOX") {
          e.preventDefault();
          void handleDismiss(item);
        }
      } else if (e.key === "Enter") {
        if (isInteractive) return;
        const item = items[safeFocusedIndex];
        if (item) {
          e.preventDefault();
          const targetId = acceptedMap[item.id] || item.id;
          router.push(`/vacancies/${targetId}#split`);
        }
      }
    };

    window.addEventListener("keydown", handleKeyDown);
    return () => window.removeEventListener("keydown", handleKeyDown);
  }, [items, safeFocusedIndex, acceptedMap, router]);

  useEffect(() => {
    if (typeof document === "undefined") return;
    const activeCard = document.querySelector<HTMLElement>(".discover-card.is-active-card");
    if (activeCard && typeof activeCard.scrollIntoView === "function") {
      activeCard.scrollIntoView({ block: "nearest", behavior: "smooth" });
    }
  }, [safeFocusedIndex]);

  const getFitBadge = (score: number, level: string) => {
    if (level === "HIGH" || score >= 70) {
      return <span className="badge badge-fit-high">★ Match {score}%</span>;
    }
    if (level === "MEDIUM" || score >= 40) {
      return <span className="badge badge-fit-medium">Match {score}%</span>;
    }
    return <span className="badge badge-fit-low">Match {score}%</span>;
  };

  return (
    <section className="discover-workspace" aria-labelledby="discover-title">
      <div className="section-title">
        <div>
          <p className="eyebrow">Smart Discovery & Portal Scrapers</p>
          <h2 id="discover-title">Входящие вакансии (Discover Inbox)</h2>
        </div>
        <Link href="/" className="secondary" style={{ padding: "8px 16px", borderRadius: "8px", textDecoration: "none" }}>
          ← К пайплайну анализа
        </Link>
      </div>

      <p className="muted">
        Параллельный поиск по карьерным порталам (Habr Career, FreeHire, HeadHunter, LinkedIn) с автоматической очисткой через <strong>Posting Guard</strong> и вычислением соответствия (Fast Fit) на основе ваших карьерных фактов.
      </p>

      {error && (
        <div className="alert error" role="alert">
          <strong>Ошибка:</strong> {error}
        </div>
      )}

      {success && (
        <div className="alert success" role="alert" style={{ background: "rgba(16, 185, 129, 0.15)", border: "1px solid #10b981", color: "#10b981", padding: "12px 16px", borderRadius: "8px", marginBottom: "16px" }}>
          {success}
        </div>
      )}

      {/* SEARCH CONTROL PANEL */}
      <div className="panel discover-search-panel" style={{ background: "var(--surface-elevated)", border: "1px solid var(--border-subtle)", borderRadius: "12px", padding: "20px", marginBottom: "28px" }}>
        <h3 style={{ marginTop: 0, marginBottom: "16px" }}>Поиск вакансий по порталам</h3>
        <form onSubmit={(e) => void handleSearch(e)}>
          <div style={{ display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(260px, 1fr))", gap: "16px", marginBottom: "16px" }}>
            <label style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
              <span style={{ fontSize: "13px", fontWeight: 600 }}>Ключевые слова / Должность</span>
              <input
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                placeholder="Например: Senior Laravel Developer, React Lead"
                required
                style={{ padding: "10px 14px", borderRadius: "8px", border: "1px solid var(--border-default)", background: "var(--surface-default)", color: "var(--text-primary)" }}
              />
            </label>

            <label style={{ display: "flex", flexDirection: "column", gap: "6px" }}>
              <span style={{ fontSize: "13px", fontWeight: 600 }}>Локация (опционально)</span>
              <input
                type="text"
                value={location}
                onChange={(e) => setLocation(e.target.value)}
                placeholder="Москва, Remote, Global"
                style={{ padding: "10px 14px", borderRadius: "8px", border: "1px solid var(--border-default)", background: "var(--surface-default)", color: "var(--text-primary)" }}
              />
            </label>
          </div>

          <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "16px", marginTop: "12px" }}>
            <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: "14px" }}>
              <span style={{ fontSize: "13px", fontWeight: 600, color: "var(--text-secondary)" }}>Источники:</span>
              {[
                { id: "freehire", name: "FreeHire (50+ ATS)" },
                { id: "habr", name: "Habr Career" },
                { id: "headhunter", name: "HeadHunter" },
                { id: "linkedin", name: "LinkedIn" },
              ].map((p) => (
                <label key={p.id} style={{ display: "flex", alignItems: "center", gap: "6px", cursor: "pointer", fontSize: "13px" }}>
                  <input
                    type="checkbox"
                    checked={portals.includes(p.id)}
                    onChange={() => togglePortal(p.id)}
                  />
                  <span>{p.name}</span>
                </label>
              ))}

              <label style={{ display: "flex", alignItems: "center", gap: "6px", cursor: "pointer", fontSize: "13px", marginLeft: "8px" }}>
                <input
                  type="checkbox"
                  checked={remoteOnly}
                  onChange={(e) => setRemoteOnly(e.target.checked)}
                />
                <span style={{ color: "var(--accent-primary)", fontWeight: 600 }}>Только Remote</span>
              </label>
            </div>

            <button
              type="submit"
              disabled={searching}
              style={{
                minWidth: "180px",
                padding: "10px 24px",
                borderRadius: "8px",
                fontWeight: 600,
                cursor: searching ? "not-allowed" : "pointer",
              }}
            >
              {searching ? "Сканирование..." : "Найти вакансии"}
            </button>
          </div>
        </form>
      </div>

      {/* INBOX TABS & FILTERS */}
      <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", justifyContent: "space-between", gap: "16px", borderBottom: "1px solid var(--border-subtle)", paddingBottom: "12px", marginBottom: "20px" }}>
        <div style={{ display: "flex", gap: "8px" }}>
          {[
            { id: "INBOX", label: "📥 Входящие (Inbox)" },
            { id: "ACCEPTED", label: "✓ В работе (Accepted)" },
            { id: "DISMISSED", label: "✕ Пропущенные" },
          ].map((tab) => (
            <button
              key={tab.id}
              type="button"
              className={activeTab === tab.id ? "primary" : "secondary"}
              onClick={() => setActiveTab(tab.id as "INBOX" | "ACCEPTED" | "DISMISSED")}
              style={{ padding: "8px 16px", borderRadius: "8px", fontSize: "14px", fontWeight: 600 }}
            >
              {tab.label}
            </button>
          ))}
        </div>

        <div style={{ display: "flex", alignItems: "center", gap: "8px" }}>
          <span style={{ fontSize: "13px", color: "var(--text-secondary)" }}>Портал:</span>
          <select
            value={portalFilter}
            onChange={(e) => setPortalFilter(e.target.value)}
            style={{ padding: "6px 12px", borderRadius: "6px", border: "1px solid var(--border-default)", background: "var(--surface-default)", color: "var(--text-primary)", fontSize: "13px" }}
          >
            <option value="">Все порталы</option>
            <option value="freehire">FreeHire</option>
            <option value="habr">Habr Career</option>
            <option value="headhunter">HeadHunter</option>
            <option value="linkedin">LinkedIn</option>
          </select>
        </div>
      </div>

      {/* VACANCY CARDS LIST */}
      {items.length > 0 && (
        <div className="keyboard-shortcuts-bar font-mono" role="region" aria-label="Горячие клавиши ленты">
          <span style={{ fontWeight: 600, color: "var(--cv-text-pure, #ffffff)" }}>⌨️ Горячие клавиши:</span>
          <span><kbd className="kbd-badge">[ A ]</kbd> В работу</span>
          <span><kbd className="kbd-badge">[ S ]</kbd> Пропустить</span>
          <span><kbd className="kbd-badge">[ ↵ Enter ]</kbd> Открыть сплит</span>
          <span><kbd className="kbd-badge">[ ↑ / ↓ ]</kbd> Навигация по вакансиям</span>
        </div>
      )}

      {loadingList ? (
        <p className="empty" style={{ textAlign: "center", padding: "40px" }}>Загрузка вакансий...</p>
      ) : items.length === 0 ? (
        <div className="panel" style={{ textAlign: "center", padding: "48px 24px", background: "var(--surface-elevated)", border: "1px solid var(--border-subtle)", borderRadius: "12px" }}>
          <p style={{ fontSize: "16px", color: "var(--text-secondary)", marginBottom: "12px" }}>
            {activeTab === "INBOX" ? "Входящих вакансий пока нет." : activeTab === "ACCEPTED" ? "Нет принятых вакансий в работе." : "Список пропущенных вакансий пуст."}
          </p>
          {activeTab === "INBOX" && (
            <p className="muted" style={{ fontSize: "14px" }}>
              Запустите поиск по порталам выше, чтобы наполнить входящую ленту свежими релевантными позициями.
            </p>
          )}
        </div>
      ) : (
        <div style={{ display: "grid", gap: "16px" }}>
          {items.map((item, index) => {
            const portalMeta = PORTAL_LABELS[item.portal] || { name: item.portal, color: "#888", badgeClass: "" };
            const isBusy = actionId === item.id;
            const acceptedCoreId = acceptedMap[item.id];
            const isFocused = index === safeFocusedIndex;

            return (
              <article
                key={item.id}
                className={`fact-card discover-card ${isFocused ? "is-active-card" : ""}`}
                onClick={() => setFocusedIndex(index)}
                onFocus={() => setFocusedIndex(index)}
                tabIndex={0}
                style={{
                  display: "grid",
                  gap: "12px",
                  padding: "20px",
                  border: isFocused ? "1px solid var(--cv-accent-primary, #FF5533)" : "1px solid var(--border-subtle)",
                  borderRadius: "12px",
                  background: "var(--surface-elevated)",
                  outline: "none",
                  cursor: "pointer",
                }}
              >
                {/* Header row */}
                <div style={{ display: "flex", flexWrap: "wrap", alignItems: "flex-start", justifyContent: "space-between", gap: "12px" }}>
                  <div>
                    <div style={{ display: "flex", alignItems: "center", gap: "8px", marginBottom: "6px", flexWrap: "wrap" }}>
                      <span
                        className="badge"
                        style={{
                          fontSize: "11px",
                          fontWeight: 700,
                          borderColor: portalMeta.color,
                          color: portalMeta.color,
                          padding: "2px 8px",
                          borderRadius: "4px",
                        }}
                      >
                        {portalMeta.name}
                      </span>
                      {getFitBadge(item.fit_score, item.fit_level)}
                      {item.security_threat_level === "CLEAN" ? (
                        <span className="postingguard-telemetry-pill" title="PostingGuard: Чисто, угроз не обнаружено">
                          <span className="badge confirmed-badge" style={{ fontSize: "11px", padding: "2px 8px" }}>🛡️ Clean</span>
                          <span
                            className="badge font-mono telemetry-pill-code"
                            style={{
                              fontSize: "10.5px",
                              letterSpacing: "0.02em",
                              background: "rgba(34, 197, 94, 0.08)",
                              borderColor: "rgba(34, 197, 94, 0.3)",
                              color: "#22c55e",
                              padding: "2px 6px",
                            }}
                          >
                            [ CLEAN: 0 threats · SSRF &amp; Injection safe ]
                          </span>
                        </span>
                      ) : (
                        <span className="postingguard-telemetry-pill" title="PostingGuard: Обнаружены потенциальные риски">
                          <span className="badge blocked-badge" style={{ fontSize: "11px", padding: "2px 8px" }}>⚠️ {item.security_threat_level}</span>
                          <span
                            className="badge font-mono telemetry-pill-code"
                            style={{
                              fontSize: "10.5px",
                              letterSpacing: "0.02em",
                              background: "rgba(239, 68, 68, 0.12)",
                              borderColor: "rgba(239, 68, 68, 0.4)",
                              color: "#ef4444",
                              padding: "2px 6px",
                            }}
                          >
                            [ SUSPICIOUS: Flagged by PostingGuard ]
                          </span>
                        </span>
                      )}
                      {isFocused && (
                        <span className="badge font-mono" style={{ fontSize: "10px", borderColor: "var(--cv-accent-primary, #FF5533)", color: "var(--cv-accent-primary, #FF5533)" }}>
                          [ Активная ]
                        </span>
                      )}
                    </div>

                    <h3 style={{ margin: "0 0 4px 0", fontSize: "18px" }}>
                      <a
                        href={item.source_url}
                        target="_blank"
                        rel="noopener noreferrer"
                        style={{ color: "var(--text-primary)", textDecoration: "none" }}
                        title="Открыть оригинальную страницу"
                      >
                        {item.title} ↗
                      </a>
                    </h3>

                    <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: "12px", color: "var(--text-secondary)", fontSize: "14px" }}>
                      <strong>{item.company}</strong>
                      {item.location && <span>📍 {item.location}</span>}
                      {item.salary_text && <span style={{ color: "var(--state-success)", fontWeight: 600 }}>💰 {item.salary_text}</span>}
                    </div>
                  </div>

                  {/* Actions */}
                  <div style={{ display: "flex", alignItems: "center", gap: "8px", flexWrap: "wrap" }}>
                    {item.status === "INBOX" && (
                      <>
                        <button
                          type="button"
                          disabled={isBusy}
                          onClick={(e) => {
                            e.stopPropagation();
                            void handleAccept(item);
                          }}
                          style={{
                            background: "var(--state-success)",
                            color: "#fff",
                            border: "none",
                            padding: "8px 16px",
                            borderRadius: "8px",
                            fontWeight: 600,
                            cursor: isBusy ? "not-allowed" : "pointer",
                          }}
                        >
                          {isBusy ? "..." : "✓ В работу"} <span className="kbd-badge" style={{ fontSize: "10px", marginLeft: "4px" }}>A</span>
                        </button>

                        <button
                          type="button"
                          className="secondary"
                          disabled={isBusy}
                          onClick={(e) => {
                            e.stopPropagation();
                            void handleDismiss(item);
                          }}
                          style={{ padding: "8px 16px", borderRadius: "8px", cursor: isBusy ? "not-allowed" : "pointer" }}
                        >
                          ✕ Пропустить <span className="kbd-badge" style={{ fontSize: "10px", marginLeft: "4px" }}>S</span>
                        </button>
                      </>
                    )}

                    {item.status === "ACCEPTED" && (
                      <span className="badge confirmed-badge" style={{ padding: "6px 12px" }}>
                        ✓ В работе
                      </span>
                    )}

                    {item.status === "DISMISSED" && (
                      <span className="badge" style={{ color: "var(--text-muted)", padding: "6px 12px" }}>
                        Отклонена
                      </span>
                    )}

                    <Link
                      href={`/vacancies/${acceptedCoreId || item.id}#split`}
                      onClick={(e) => e.stopPropagation()}
                      className="split-jump-pill"
                      title="Открыть вакансию в сплит-редакторе"
                    >
                      ↔ Сплит <span className="kbd-badge" style={{ fontSize: "10px", marginLeft: "2px" }}>↵</span>
                    </Link>
                  </div>
                </div>

                {/* Match Details & Reasons */}
                {item.fit_reasons && (
                  <div style={{ background: "rgba(255, 255, 255, 0.03)", padding: "10px 14px", borderRadius: "8px", fontSize: "13px" }}>
                    {item.fit_reasons.summary && (
                      <p style={{ margin: "0 0 6px 0", color: "var(--text-secondary)" }}>
                        <strong>Матчинг:</strong> {item.fit_reasons.summary}
                      </p>
                    )}

                    <div style={{ display: "flex", flexWrap: "wrap", alignItems: "center", gap: "6px" }}>
                      {item.fit_reasons.matched_skills && item.fit_reasons.matched_skills.length > 0 && (
                        <>
                          <span style={{ color: "var(--state-success)", fontWeight: 600 }}>Совпало:</span>
                          {item.fit_reasons.matched_skills.map((s) => (
                            <span
                              key={s}
                              style={{
                                background: "rgba(16, 185, 129, 0.15)",
                                color: "#10b981",
                                padding: "2px 8px",
                                borderRadius: "4px",
                                fontSize: "12px",
                              }}
                            >
                              {s}
                            </span>
                          ))}
                        </>
                      )}

                      {item.fit_reasons.missing_skills && item.fit_reasons.missing_skills.length > 0 && (
                        <>
                          <span style={{ color: "var(--text-muted)", marginLeft: "8px" }}>В вакансии также:</span>
                          {item.fit_reasons.missing_skills.slice(0, 4).map((s) => (
                            <span
                              key={s}
                              style={{
                                background: "rgba(255, 255, 255, 0.05)",
                                color: "var(--text-secondary)",
                                padding: "2px 8px",
                                borderRadius: "4px",
                                fontSize: "12px",
                              }}
                            >
                              {s}
                            </span>
                          ))}
                        </>
                      )}
                    </div>
                  </div>
                )}

                {/* Snippet / Description Preview */}
                {(item.snippet || item.description) && (
                  <p style={{ margin: "4px 0 0 0", color: "var(--text-secondary)", fontSize: "14px", lineHeight: "1.5" }}>
                    {item.snippet ? item.snippet.slice(0, 260) : item.description?.slice(0, 260)}...
                  </p>
                )}

                {/* Direct link to core analysis pipeline if accepted */}
                {acceptedCoreId && (
                  <div style={{ marginTop: "4px" }}>
                    <Link
                      href={`/?selected=${acceptedCoreId}`}
                      style={{ color: "var(--accent-primary)", fontSize: "13px", fontWeight: 600 }}
                    >
                      Перейти к анализу вакансии в пайплайне CVortex →
                    </Link>
                  </div>
                )}
              </article>
            );
          })}
        </div>
      )}
    </section>
  );
}

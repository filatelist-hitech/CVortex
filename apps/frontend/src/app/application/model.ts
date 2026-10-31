import type { VacancySummary } from "../vacancy-workspace";

export const sections = ["split", "overview", "requirements", "materials", "employer", "history"] as const;
export const sectionLabels = ["Сплит-редактор ↔", "Обзор", "Требования и мой опыт", "Материалы", "Работодатель", "История"];
export const scenarios = ["normal", "loading", "empty", "failed", "stale", "blocked", "pending", "approval"] as const;
export const scenarioLabels = ["Обычное", "Загрузка", "Нет анализа", "Ошибка анализа", "Данные устарели", "Нет подтверждений", "Факт не подтверждён", "Правка принята, текст не одобрен"];
export type Section = typeof sections[number];
export type Scenario = typeof scenarios[number];
export type Level = "list" | "work" | "context";
export type WorkspaceVacancy = {
  id: string; company: string; role: string; initial: string; status: string;
  salary?: { min: number; max: number }; metadata: string; note: string;
};
export type WorkspaceLocation = { selected: string; section: Section; scenario: Scenario; level: Level };

export function readLocation(): WorkspaceLocation {
  const params = new URLSearchParams(window.location.search);
  const hash = window.location.hash.slice(1);
  const selected = params.get("opportunity") ?? "";
  const scenario = params.get("scenario");
  return { selected, section: sections.includes(hash as Section) ? hash as Section : "overview",
    scenario: scenarios.includes(scenario as Scenario) ? scenario as Scenario : "normal",
    level: params.get("level") === "context" && selected ? "context" : params.get("level") === "list" || !selected ? "list" : "work" };
}

export function vacancyView(detail: VacancySummary): WorkspaceVacancy {
  return { id: detail.id, company: detail.company ?? "Компания не указана", role: detail.title ?? "Название не указано",
    initial: (detail.company ?? "?").charAt(0), status: "Отправка отклика не подтверждена",
    metadata: "Формат и местоположение не указаны", note: detail.analysis_stale ? "Данные устарели" : detail.analysis_status };
}

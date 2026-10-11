import { Icon } from "./icons";
import { useWorkspace } from "./use-workspace";

export function VacancySalary() {
  const w = useWorkspace();
  const salary = w.item?.salary;
  const missing = typeof window !== "undefined" && new URLSearchParams(window.location.search).get("salary") === "missing";
  return <div className="vacancy-finance">
    {/* Licensed/local decorative B01 artwork; never an employer logo. */}
    {/* eslint-disable-next-line @next/next/no-img-element */}
    <img className="career-art" src="/application-assets/career-vortex-v1.png" alt="" aria-hidden="true" width={240} height={160} />
    <div className="salary-card"><span className="salary-label type-caption">Зарплата</span><div className="salary-value" id="vacancy-salary" data-min={missing ? undefined : salary?.min} data-max={missing ? undefined : salary?.max} aria-label={salary && !missing ? `${new Intl.NumberFormat("ru-RU").format(salary.min)}–${new Intl.NumberFormat("ru-RU").format(salary.max)} ₽ в месяц, до налогов` : undefined}>{salary && !missing ? <><span className="type-number">{salary.min / 1000}–{salary.max / 1000}</span>{" "}<span className="salary-unit type-secondary">тыс. <span className="salary-currency">₽</span></span></> : <span className="type-subsection">Не указана</span>}</div><span className="salary-basis type-support">{salary && !missing ? "В месяц, до налогов" : w.demo ? "В этом примере данных о зарплате нет" : "В текущих данных зарплаты нет"}</span></div>
  </div>;
}

export function VacancyHeader() {
  const w = useWorkspace();
  const Heading = w.size === "mobile" && w.location.level !== "work" ? "h2" : "h1";
  return <>
    <div className="eyebrow-row type-caption"><span>Вакансии / <span id="crumb-company">{w.item?.company}</span></span><span className="badge neutral" id="domain-status">{w.item?.status ?? "Отклик не отправлен"}</span></div>
    <div className="vacancy-summary"><div className="vacancy-copy"><div className="workspace-heading"><div className="identity" aria-hidden="true" id="identity-initial">{w.item?.initial ?? "?"}</div><p className="type-secondary" id="company-name">{w.item?.company ?? "Выберите вакансию"}</p></div><Heading className="type-page" id="workspace-title" tabIndex={-1}>{w.item?.role ?? "Рабочее пространство вакансии"}</Heading><p className="metadata type-secondary"><Icon name="map-pin" /><span id="vacancy-metadata">{w.item?.metadata ?? "Данные ещё не загружены"}</span></p><span className="vacancy-context type-caption">Подготовка резюме</span></div><VacancySalary /></div>
  </>;
}

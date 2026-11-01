export type NavItem = {
  id: string;
  label: string;
  icon: "sun" | "briefcase" | "plus" | "search" | "user" | "building" | "settings";
  href?: string;
  disabled?: boolean;
  disabledReason?: string;
  badge?: string;
  description?: string;
};

export const GLOBAL_NAV_ITEMS: readonly NavItem[] = [
  { id: "today", label: "Сегодня", icon: "sun", href: "/" },
  { id: "vacancies", label: "Вакансии", icon: "briefcase", href: "/vacancies" },
  { id: "import", label: "Импорт вакансии", icon: "plus", href: "/vacancies/import" },
  { id: "discover", label: "Поиск (Discover)", icon: "search", href: "/vacancies/discover" },
  { id: "career", label: "Карьера", icon: "user", href: "/career" },
  {
    id: "employers",
    label: "Работодатели",
    icon: "building",
    disabled: true,
    disabledReason: "Недоступно",
    description: "В приложении ещё нет подтверждённой связи с работодателем",
  },
  { id: "settings", label: "Настройки", icon: "settings", href: "/settings" },
] as const;

export type Destination =
  | "today"
  | "career"
  | "diagnostics"
  | "vacancies"
  | "vacancy-import"
  | "admin-settings"
  | "settings"
  | "discover";

export function resolveActiveNavId(destination: Destination, hash: string): string | null {
  if (destination === "today") return "today";
  if (destination === "vacancies") return "vacancies";
  if (destination === "vacancy-import") return "import";
  if (destination === "discover") return "discover";
  if (destination === "career") {
    return hash === "#vacancy-title" ? "vacancies" : "career";
  }
  if (destination === "settings") return "settings";
  return null;
}

export const DESTINATION_META: Record<
  Destination,
  { label: string; subtitle: string }
> = {
  today: {
    label: "Сегодня",
    subtitle: "Командный центр поиска работы и подготовки откликов",
  },
  vacancies: {
    label: "Вакансии",
    subtitle: "Подготовка отклика на основе подтверждённых фактов",
  },
  "vacancy-import": {
    label: "Импорт вакансии",
    subtitle: "Вставка текста и автоматический разбор требований",
  },
  discover: {
    label: "Поиск вакансий",
    subtitle: "Агрегация вакансий с порталов и оценка соответствия",
  },
  career: {
    label: "Карьера",
    subtitle: "Факты, источники и подтверждения",
  },
  settings: {
    label: "Настройки AI",
    subtitle: "Конфигурация AI-провайдеров и ключей",
  },
  "admin-settings": {
    label: "Настройки системы",
    subtitle: "Конфигурация системы и интеграций",
  },
  diagnostics: {
    label: "Диагностика",
    subtitle: "Ошибки и состояние операций",
  },
};

export type SettingsTab = {
  id: string;
  label: string;
  href: string;
  icon: string;
  adminOnly?: boolean;
};

export const SETTINGS_TABS: readonly SettingsTab[] = [
  {
    id: "ai",
    label: "AI-провайдеры",
    href: "/settings",
    icon: "cpu",
  },
  {
    id: "system",
    label: "Системные настройки",
    href: "/admin/settings",
    icon: "sliders",
    adminOnly: true,
  },
  {
    id: "diagnostics",
    label: "Диагностика",
    href: "/diagnostics",
    icon: "activity",
    adminOnly: true,
  },
] as const;

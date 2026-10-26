import type { DiscoveredJob, SearchOptions } from "./types"

export async function searchHabr(opts: SearchOptions): Promise<DiscoveredJob[]> {
  const params = new URLSearchParams()
  if (opts.query) params.set("q", opts.query)
  if (opts.remote) params.set("remote", "1")

  const url = `https://career.habr.com/api/frontend/vacancies?${params.toString()}`

  try {
    const res = await fetch(url, {
      headers: {
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept": "application/json",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!res.ok) {
      return []
    }

    const data = await res.json() as any
    const list = data.list || []
    const limit = opts.limit || 15

    return list.slice(0, limit).map((item: any): DiscoveredJob => {
      let salaryText: string | null = null
      if (item.salary) {
        salaryText = item.salary.formatted || (item.salary.value ? String(item.salary.value) : null)
      }

      const snippet = item.snippet || item.divisions?.map((d: any) => d.title).join(", ") || ""

      return {
        portal: "habr",
        external_id: String(item.id),
        title: item.title || "Без названия",
        company: item.company?.title || "Не указана",
        location: item.locations?.map((l: any) => l.title).join(", ") || (item.remote ? "Удалённо" : "РФ"),
        source_url: `https://career.habr.com/vacancies/${item.id}`,
        salary_text: salaryText,
        description: snippet,
        snippet: snippet.slice(0, 300),
        published_at: typeof item.published_at === "string" ? item.published_at : (item.published_at?.date || null),
      }
    })
  } catch {
    return []
  }
}

import type { DiscoveredJob, SearchOptions } from "./types"
import { sanitizeSourceUrl } from "./url-guard"

export async function searchFreehire(opts: SearchOptions): Promise<DiscoveredJob[]> {
  const params = new URLSearchParams()
  if (opts.query) params.set("q", opts.query)
  params.set("limit", String(opts.limit || 15))
  if (opts.remote) params.set("work_mode", "remote")

  const url = `https://freehire.me/api/v1/jobs/search?${params.toString()}`

  try {
    const res = await fetch(url, {
      headers: {
        "User-Agent": "CVortex-Worker/1.0 (+https://github.com/filatelist-hitech/CVortex)",
        "Accept": "application/json",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!res.ok) {
      return []
    }

    const data = await res.json() as any
    const items = Array.isArray(data) ? data : (data.data || data.items || data.jobs || [])

    return items.map((job: any): DiscoveredJob => ({
      portal: "freehire",
      external_id: String(job.id || job.public_slug || job.external_id || Math.random().toString(36).substring(7)),
      title: job.title || "Без названия",
      company: job.company?.name || job.company_name || "Не указана",
      location: job.location || (job.work_mode === "remote" ? "Удалённо (Remote)" : "Global"),
      source_url: sanitizeSourceUrl(job.url || job.apply_url, `https://freehire.me/job/${job.public_slug || job.id}`),
      salary_text: job.salary ? `${job.salary.min || ''} - ${job.salary.max || ''} ${job.salary.currency || ''}`.trim() : null,
      description: job.description_markdown || job.description || null,
      snippet: job.preview || (job.description ? job.description.slice(0, 300) : null),
      published_at: job.posted_at || job.created_at || null,
    }))
  } catch {
    return []
  }
}

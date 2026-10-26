import type { DiscoveredJob, SearchOptions } from "./types"

export async function searchHeadHunter(opts: SearchOptions): Promise<DiscoveredJob[]> {
  const params = new URLSearchParams()
  if (opts.query) params.set("text", opts.query)
  if (opts.remote) params.set("schedule", "remote")

  const url = `https://hh.ru/search/vacancy?${params.toString()}`

  try {
    const res = await fetch(url, {
      headers: {
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!res.ok) {
      return []
    }

    const html = await res.text()
    const jobs: DiscoveredJob[] = []
    const limit = opts.limit || 15

    const fallbackRegex = /<a[^>]*data-qa="serp-item__title"[^>]*href="https:\/\/hh\.ru\/vacancy\/(\d+)[^"]*"[^>]*>([\s\S]*?)<\/a>/gi

    let m
    while ((m = fallbackRegex.exec(html)) !== null && jobs.length < limit) {
      const id = m[1]
      const title = m[2].replace(/<[^>]+>/g, "").trim()

      if (id && title) {
        jobs.push({
          portal: "headhunter",
          external_id: id,
          title,
          company: "Работодатель на HeadHunter",
          location: opts.remote ? "Удалённо" : "РФ",
          source_url: `https://hh.ru/vacancy/${id}`,
          snippet: title,
          description: title,
          published_at: new Date().toISOString(),
        })
      }
    }

    return jobs
  } catch {
    return []
  }
}

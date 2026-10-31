import type { DiscoveredJob, SearchOptions } from "./types"
import { isSafeExternalUrl } from "./url-guard"

export async function searchLinkedIn(opts: SearchOptions): Promise<DiscoveredJob[]> {
  const params = new URLSearchParams()
  if (opts.query) params.set("keywords", opts.query)
  if (opts.location) params.set("location", opts.location)
  if (opts.remote) params.set("f_WT", "2") // 2 is Remote in LinkedIn facet

  const url = `https://www.linkedin.com/jobs-guest/jobs/api/seeMoreJobPostings/search?${params.toString()}`

  try {
    const res = await fetch(url, {
      headers: {
        "User-Agent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "Accept": "text/html,application/xhtml+xml",
      },
      signal: AbortSignal.timeout(10000),
    })

    if (!res.ok) {
      return []
    }

    const html = await res.text()
    const jobs: DiscoveredJob[] = []
    const cardRegex = /<li[^>]*>([\s\S]*?)<\/li>/gi
    const titleRegex = /<h3[^>]*class="[^"]*base-search-card__title[^"]*"[^>]*>([\s\S]*?)<\/h3>/i
    const companyRegex = /<h4[^>]*class="[^"]*base-search-card__subtitle[^"]*"[^>]*>[\s\S]*?<a[^>]*>([\s\S]*?)<\/a>|<\/h4>/i
    const locationRegex = /<span[^>]*class="[^"]*job-search-card__location[^"]*"[^>]*>([\s\S]*?)<\/span>/i
    const linkRegex = /<a[^>]*class="[^"]*base-card__full-link[^"]*"[^>]*href="([^"]*)"/i

    let match
    const limit = opts.limit || 15

    while ((match = cardRegex.exec(html)) !== null && jobs.length < limit) {
      const cardHtml = match[1]
      const titleMatch = titleRegex.exec(cardHtml)
      const companyMatch = companyRegex.exec(cardHtml)
      const locationMatch = locationRegex.exec(cardHtml)
      const linkMatch = linkRegex.exec(cardHtml)

      const title = titleMatch ? titleMatch[1].trim() : ""
      let link = linkMatch ? linkMatch[1].trim() : ""
      if (link.includes("?")) {
        link = link.split("?")[0]
      }

      if (title && link && isSafeExternalUrl(link)) {
        jobs.push({
          portal: "linkedin",
          external_id: link.split("/").pop() || Math.random().toString(36).substring(7),
          title,
          company: companyMatch ? companyMatch[1].trim() : "LinkedIn Employer",
          location: locationMatch ? locationMatch[1].trim() : (opts.remote ? "Remote" : "Global"),
          source_url: link,
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

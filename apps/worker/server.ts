import { searchFreehire } from "./scrapers/freehire"
import { searchHeadHunter } from "./scrapers/hh"
import { searchHabr } from "./scrapers/habr"
import { searchLinkedIn } from "./scrapers/linkedin"
import { isSafeExternalUrl } from "./scrapers/url-guard"
import type { DiscoveredJob, SearchOptions } from "./scrapers/types"

const PORT = parseInt(process.env.PORT || "3001", 10)

const server = Bun.serve({
  port: PORT,
  async fetch(req) {
    const url = new URL(req.url)

    if (url.pathname === "/health" || url.pathname === "/") {
      return Response.json({ status: "ok", service: "cvortex-worker" })
    }

    if (url.pathname === "/scrapers/search" && req.method === "POST") {
      try {
        const body = await req.json() as Partial<SearchOptions>
        const options: SearchOptions = {
          query: body.query || "",
          location: body.location || "",
          limit: typeof body.limit === "number" ? body.limit : 15,
          remote: Boolean(body.remote),
          portals: Array.isArray(body.portals) && body.portals.length > 0
            ? body.portals.map(p => String(p).toLowerCase().trim())
            : ["freehire", "headhunter", "habr", "linkedin"],
        }

        if (!options.query.trim()) {
          return Response.json({ error: "Missing required 'query' parameter" }, { status: 422 })
        }

        const tasks: Promise<DiscoveredJob[]>[] = []

        if (options.portals?.includes("freehire")) {
          tasks.push(searchFreehire(options))
        }
        if (options.portals?.includes("headhunter") || options.portals?.includes("hh")) {
          tasks.push(searchHeadHunter(options))
        }
        if (options.portals?.includes("habr")) {
          tasks.push(searchHabr(options))
        }
        if (options.portals?.includes("linkedin")) {
          tasks.push(searchLinkedIn(options))
        }

        const results = await Promise.allSettled(tasks)
        const allJobs: DiscoveredJob[] = []
        const seenUrls = new Set<string>()

        for (const res of results) {
          if (res.status === "fulfilled") {
            for (const job of res.value) {
              if (!seenUrls.has(job.source_url) && isSafeExternalUrl(job.source_url)) {
                seenUrls.add(job.source_url)
                allJobs.push(job)
              }
            }
          }
        }

        return Response.json({
          success: true,
          query: options.query,
          count: allJobs.length,
          jobs: allJobs,
        })
      } catch (err) {
        return Response.json({
          error: err instanceof Error ? err.message : String(err),
        }, { status: 500 })
      }
    }

    return Response.json({ error: "Not Found" }, { status: 404 })
  },
})

console.log(`CVortex Worker HTTP Server listening on port ${server.port}`)

process.on("SIGTERM", () => {
  server.stop()
  process.exit(0)
})

#!/usr/bin/env bun
import { searchFreehire } from "./freehire"
import { searchHeadHunter } from "./hh"
import { searchHabr } from "./habr"
import { searchLinkedIn } from "./linkedin"
import { isSafeExternalUrl } from "./url-guard"
import type { DiscoveredJob, SearchOptions } from "./types"

async function main() {
  const args = process.argv.slice(2)
  const options: SearchOptions = {
    query: "",
    location: "",
    limit: 15,
    remote: false,
    portals: ["freehire", "headhunter", "habr", "linkedin"],
  }

  for (let i = 0; i < args.length; i++) {
    const arg = args[i]
    if (arg === "--query" || arg === "-q") {
      options.query = args[++i] || ""
    } else if (arg === "--location" || arg === "-l") {
      options.location = args[++i] || ""
    } else if (arg === "--limit") {
      options.limit = parseInt(args[++i] || "15", 10)
    } else if (arg === "--remote") {
      options.remote = true
    } else if (arg === "--portals") {
      options.portals = (args[++i] || "").split(",").map((s) => s.trim().toLowerCase())
    }
  }

  if (!options.query) {
    console.error(JSON.stringify({ error: "Missing --query parameter" }))
    process.exit(1)
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

  console.log(JSON.stringify(allJobs))
}

main().catch((err) => {
  console.error(JSON.stringify({ error: err instanceof Error ? err.message : String(err) }))
  process.exit(1)
})

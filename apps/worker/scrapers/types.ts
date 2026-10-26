export interface DiscoveredJob {
  portal: "freehire" | "linkedin" | "headhunter" | "habr" | string
  external_id: string
  title: string
  company: string
  location: string
  source_url: string
  salary_text?: string | null
  description?: string | null
  snippet?: string | null
  published_at?: string | null
}

export interface SearchOptions {
  query: string
  location?: string
  limit?: number
  remote?: boolean
  portals?: string[]
}

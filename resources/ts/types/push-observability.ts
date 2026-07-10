export interface PushEventRow {
  id: number
  received_at: string
  region_code: string | null
  endpoint: string
  method: string | null
  status: 'OK' | 'ERROR'
  http_status: number | null
  duration_ms: number | null
  correlation_id: string | null
  mandats_count: number | null
  recettes_count: number | null
  banques_count: number | null
  message: string | null
  remote_ip: string | null
  user_agent: string | null
}

export interface PushEventsMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface PushRegionOverviewRow {
  region: {
    code: string
    nom: string
  }
  last_received_at: string | null
  age_minutes: number | null
  state: 'ok' | 'late' | 'error' | 'no_data'
  last_status: 'OK' | 'ERROR' | null
  last_http_status: number | null
  last_endpoint: string | null
  last_message: string | null
  mandats_count: number | null
  recettes_count: number | null
  banques_count: number | null
  error_count: number
  success_count: number
}

export interface PushTopErrorRow {
  http_status: number
  endpoint: string
  message_short: string
  occurrences: number
  last_seen: string
}

export interface PushObservabilitySummary {
  total_events: number
  errors_count: number
  success_count: number
  regions_count: number
  last_received_at: string | null
  mandats_count: number
  recettes_count: number
  banques_count: number
}

export interface PushRegionsResponse {
  summary: PushObservabilitySummary
  regions: PushRegionOverviewRow[]
  top_errors: PushTopErrorRow[]
}

import type { LocationQuery, RouteLocationRaw } from 'vue-router/auto'
import { endOfMonth, formatDateRange, startOfMonth } from '@/composables/useFormat'

const FILTER_STORAGE_KEY = 'aice-dashboard-filters'

interface StoredDashboardFilters {
  region_code?: string | null
  date_debut?: string
  date_fin?: string
}

const regionCode = ref<string | null>(null)
const dateDebut = ref(startOfMonth())
const dateFin = ref(endOfMonth())

function readStoredFilters(): StoredDashboardFilters {
  if (typeof localStorage === 'undefined')
    return {}

  try {
    const raw = localStorage.getItem(FILTER_STORAGE_KEY)

    return raw ? JSON.parse(raw) as StoredDashboardFilters : {}
  }
  catch {
    return {}
  }
}

function writeStoredFilters() {
  if (typeof localStorage === 'undefined')
    return

  const payload: StoredDashboardFilters = {
    region_code: regionCode.value,
    date_debut: dateDebut.value,
    date_fin: dateFin.value,
  }

  localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify(payload))
}

function restoreFiltersFromStorage() {
  const stored = readStoredFilters()

  if (stored.date_debut)
    dateDebut.value = stored.date_debut
  if (stored.date_fin)
    dateFin.value = stored.date_fin
  if (stored.region_code !== undefined)
    regionCode.value = stored.region_code
}

restoreFiltersFromStorage()

export type ExplorerExtraQuery = Record<string, string | number | null | undefined>

export function queryParam(value: LocationQuery[string]): string | undefined {
  if (Array.isArray(value))
    return value[0] != null && value[0] !== '' ? String(value[0]) : undefined

  if (value == null || value === '')
    return undefined

  return String(value)
}

export function useDetailExplorerContext() {
  const periodLabel = computed(() => formatDateRange(dateDebut.value, dateFin.value))

  function periodQuery() {
    return {
      date_debut: dateDebut.value,
      date_fin: dateFin.value,
    }
  }

  function baseQuery(extra: ExplorerExtraQuery = {}): Record<string, string> {
    const query: Record<string, string> = {
      ...periodQuery(),
    }

    if (regionCode.value)
      query.region_code = regionCode.value

    for (const [key, value] of Object.entries(extra)) {
      if (value != null && value !== '')
        query[key] = String(value)
    }

    return query
  }

  function applyBaseQuery(query: LocationQuery, options: { useStorageFallback?: boolean } = {}) {
    const rc = queryParam(query.region_code)
    const dd = queryParam(query.date_debut)
    const df = queryParam(query.date_fin)
    const stored = options.useStorageFallback ? readStoredFilters() : {}

    if (rc) {
      regionCode.value = rc
    }
    else if (options.useStorageFallback && stored.region_code !== undefined) {
      regionCode.value = stored.region_code
    }

    if (dd) {
      dateDebut.value = dd
    }
    else if (options.useStorageFallback && stored.date_debut) {
      dateDebut.value = stored.date_debut
    }

    if (df) {
      dateFin.value = df
    }
    else if (options.useStorageFallback && stored.date_fin) {
      dateFin.value = stored.date_fin
    }
  }

  function detailRoute(name: string, extra: ExplorerExtraQuery = {}): RouteLocationRaw {
    return {
      name: name as any,
      query: baseQuery(extra),
    } as RouteLocationRaw
  }

  function dashboardRoute(name: string): RouteLocationRaw {
    return {
      name: name as any,
      query: baseQuery(),
    } as RouteLocationRaw
  }

  function isValidPeriod(): boolean {
    return !(dateDebut.value && dateFin.value && dateDebut.value > dateFin.value)
  }

  return {
    regionCode,
    dateDebut,
    dateFin,
    periodLabel,
    periodQuery,
    baseQuery,
    applyBaseQuery,
    detailRoute,
    dashboardRoute,
    isValidPeriod,
  }
}

/** Filtres partagés entre tableaux de bord (région + période), synchronisés avec l’URL. */
export function useDashboardFilterSync() {
  const route = useRoute()
  const router = useRouter()
  const ctx = useDetailExplorerContext()
  let hydrating = false

  function syncRoute() {
    if (hydrating || !route.name)
      return

    router.replace({ query: ctx.baseQuery() })
  }

  function hydrateFromRoute() {
    hydrating = true
    ctx.applyBaseQuery(route.query, { useStorageFallback: true })
    nextTick(() => {
      hydrating = false
      syncRoute()
    })
  }

  watch([ctx.regionCode, ctx.dateDebut, ctx.dateFin], () => {
    writeStoredFilters()
    syncRoute()
  })

  return {
    ...ctx,
    syncRoute,
    hydrateFromRoute,
  }
}

export function useExplorerRouteSync(
  getPageQuery: () => ExplorerExtraQuery,
  applyPageQuery: (query: LocationQuery) => void,
) {
  const route = useRoute()
  const router = useRouter()
  const ctx = useDetailExplorerContext()
  let hydrating = false

  function syncRoute() {
    if (hydrating || !route.name)
      return

    router.replace({ query: ctx.baseQuery(getPageQuery()) })
  }

  function hydrateFromRoute() {
    hydrating = true
    ctx.applyBaseQuery(route.query, { useStorageFallback: true })
    applyPageQuery(route.query)
    nextTick(() => {
      hydrating = false
    })
  }

  watch([ctx.regionCode, ctx.dateDebut, ctx.dateFin], () => {
    writeStoredFilters()
    syncRoute()
  })

  return {
    ...ctx,
    syncRoute,
    hydrateFromRoute,
  }
}

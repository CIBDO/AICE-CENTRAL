import type { LocationQuery, RouteLocationRaw } from 'vue-router'
import { endOfMonth, formatDateRange, startOfMonth } from '@/composables/useFormat'

const regionCode = ref<string | null>(null)
const dateDebut = ref(startOfMonth())
const dateFin = ref(endOfMonth())

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

  function applyBaseQuery(query: LocationQuery) {
    const rc = queryParam(query.region_code)
    const dd = queryParam(query.date_debut)
    const df = queryParam(query.date_fin)

    if (rc)
      regionCode.value = rc
    if (dd)
      dateDebut.value = dd
    if (df)
      dateFin.value = df
  }

  function detailRoute(name: string, extra: ExplorerExtraQuery = {}): RouteLocationRaw {
    return {
      name,
      query: baseQuery(extra),
    }
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
    isValidPeriod,
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

    router.replace({
      name: route.name,
      query: ctx.baseQuery(getPageQuery()),
    })
  }

  function hydrateFromRoute() {
    hydrating = true
    ctx.applyBaseQuery(route.query)
    applyPageQuery(route.query)
    nextTick(() => {
      hydrating = false
    })
  }

  return {
    ...ctx,
    syncRoute,
    hydrateFromRoute,
  }
}

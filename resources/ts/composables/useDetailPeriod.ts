import { useDetailExplorerContext } from '@/composables/useDetailExplorerContext'

export function useDetailPeriod() {
  const ctx = useDetailExplorerContext()

  return {
    dateDebut: ctx.dateDebut,
    dateFin: ctx.dateFin,
    periodLabel: ctx.periodLabel,
    periodQuery: ctx.periodQuery,
    isValidPeriod: ctx.isValidPeriod,
  }
}

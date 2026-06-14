export function formatFcfa(value: number): string {
  const formatted = new Intl.NumberFormat('fr-FR', {
    maximumFractionDigits: 0,
  }).format(value)

  return `${formatted} FCFA`
}

export function formatDateFr(iso: string | null | undefined): string {
  if (!iso)
    return '—'

  return new Intl.DateTimeFormat('fr-FR', {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(new Date(iso))
}

/** Date calendaire sans heure (évite le décalage fuseau sur les champs date-only). */
export function formatDateOnly(iso: string | null | undefined): string {
  if (!iso)
    return '—'

  const match = iso.slice(0, 10).match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!match)
    return '—'

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])

  return new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' }).format(new Date(year, month - 1, day))
}

/** Libellé court pour axes de graphiques (ex. 17/10). */
export function formatDayLabel(ymd: string): string {
  const match = ymd.match(/^(\d{4})-(\d{2})-(\d{2})$/)
  if (!match)
    return ymd

  const year = Number(match[1])
  const month = Number(match[2])
  const day = Number(match[3])

  return new Intl.DateTimeFormat('fr-FR', { day: '2-digit', month: '2-digit' }).format(new Date(year, month - 1, day))
}

export function formatMonthYear(annee: number | null, mois: number | null): string {
  if (!annee)
    return 'Période non définie'

  if (!mois)
    return String(annee)

  const label = new Intl.DateTimeFormat('fr-FR', { month: 'long' }).format(new Date(annee, mois - 1, 1))

  return `${label} ${annee}`
}

export function formatDateRange(debut: string | null, fin: string | null): string {
  if (!debut)
    return 'Période non définie'

  const formatter = new Intl.DateTimeFormat('fr-FR', { dateStyle: 'medium' })
  const start = formatter.format(new Date(`${debut}T00:00:00`))

  if (!fin || fin === debut)
    return start

  return `${start} — ${formatter.format(new Date(`${fin}T00:00:00`))}`
}

export function toIsoDate(date: Date): string {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')

  return `${year}-${month}-${day}`
}

export function startOfMonth(date = new Date()): string {
  return toIsoDate(new Date(date.getFullYear(), date.getMonth(), 1))
}

export function endOfMonth(date = new Date()): string {
  return toIsoDate(new Date(date.getFullYear(), date.getMonth() + 1, 0))
}

export function formatPercent(value: number): string {
  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1 }).format(value)} %`
}

export function formatEvolutionPct(value: number | null | undefined): string {
  if (value === null || value === undefined)
    return '—'

  return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1, signDisplay: 'exceptZero' }).format(value)} %`
}

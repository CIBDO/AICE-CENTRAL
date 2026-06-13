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

export function formatMonthYear(annee: number | null, mois: number | null): string {
  if (!annee)
    return 'Période non définie'

  if (!mois)
    return String(annee)

  const label = new Intl.DateTimeFormat('fr-FR', { month: 'long' }).format(new Date(annee, mois - 1, 1))

  return `${label} ${annee}`
}

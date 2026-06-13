export interface DashboardKpis {
  total_recettes: number
  total_depenses: number
  solde: number
  encaisse: number
}

export interface MandatTypeRow {
  code: string
  libelle: string
  count: number
  montant: number
}

export interface MandatStatutRow {
  statut: string
  count: number
  montant: number
}

export interface DashboardSummary {
  region: {
    code: string
    nom: string
  }
  periode: {
    annee: number | null
    mois: number | null
    date_debut: string | null
    date_fin: string | null
  }
  kpis: DashboardKpis
  mandats_par_type: MandatTypeRow[]
  statuts_mandats: MandatStatutRow[]
  meta: {
    dashboard_id: number | null
    regional_id: string | null
    derniere_mise_a_jour: string | null
    mouvements_count: number
  }
}

export interface RegionOption {
  id: number
  code: string
  nom: string
  ordre: number
  derniere_connexion: string | null
}

export type KpiAccent = 'recettes' | 'depenses' | 'solde' | 'encaisse' | 'neutral'

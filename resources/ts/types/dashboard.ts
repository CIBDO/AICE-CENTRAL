export interface DashboardKpis {
  total_ordonnance: number
  total_recouvrements_4121: number
  total_montant_paye: number
  tresorerie_reelle: number
  solde: number
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

export type KpiAccent = 'recouvrements' | 'ordonnance' | 'paye' | 'solde' | 'tresorerie' | 'neutral'

export interface CentralRegionRow {
  region: {
    code: string
    nom: string
  }
  kpis: DashboardKpis
  meta: {
    has_data: boolean
    mouvements_count: number
    derniere_mise_a_jour: string | null
  }
}

export interface CentralSummary {
  periode: {
    annee: number | null
    mois: number | null
    date_debut?: string | null
    date_fin?: string | null
  }
  global: DashboardKpis
  regions: CentralRegionRow[]
  meta: {
    regions_actives: number
    regions_avec_donnees: number
    derniere_mise_a_jour: string | null
  }
}

export interface ExecutiveAlert {
  id: string
  priorite: 'critique' | 'warning' | 'info'
  categorie: string
  titre: string
  message: string
  action_recommandee: string
  timestamp: string
}

export interface ExecutiveAnomaly {
  type: string
  region_code: string
  region_nom: string
  description: string
  valeur: number
  severite: 'elevee' | 'moderee'
}

export interface ExecutiveKpis {
  periode: { annee: number; mois: number | null; date_debut?: string | null; date_fin?: string | null }
  indicateurs: {
    taux_execution: number
    taux_rejet: number
    mandats_total: number
    mandats_admis: number
    mandats_rejetes: number
    tresorerie_reelle_total: number
    recouvrements_4121_total: number
    ordonnance_total: number
    montant_paye_total: number
    solde_total: number
  }
  comparaison_mois_precedent: {
    ordonnance_evolution_pct: number | null
    recouvrements_evolution_pct: number | null
    mandats_evolution_pct: number | null
  }
  performance_regions: Array<{
    region: { code: string; nom: string }
    taux_execution: number
    taux_rejet: number
    mandats_total: number
    score: number
  }>
  meta: {
    regions_actives: number
    regions_avec_donnees: number
    derniere_mise_a_jour: string | null
  }
}

export interface ExecutivePredictions {
  tendance_depenses: {
    type: 'stable' | 'hausse' | 'baisse'
    evolution_pct: number | null
    description: string
  }
  projection_depenses_fin_mois: number
  depenses_mois_courant: number
}

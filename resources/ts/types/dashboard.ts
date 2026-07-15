export interface DashboardKpis {
  total_ordonnance: number
  total_recouvrements_4121: number
  total_montant_paye: number
  tresorerie_reelle: number
  solde: number
}

export interface WorkflowBucket {
  count: number
  montant: number
}

export interface WorkflowBacklog {
  admis: WorkflowBucket
  autres_non_payes: WorkflowBucket
  total_hors_rejet: WorkflowBucket
}

export interface WorkflowStatusAgingRow {
  statut: string
  count: number
  average_days: number
  max_days: number
}

export interface WorkflowConversionRow {
  key: string
  label: string
  base_count: number
  converted_count: number
  taux_pct: number
}

export interface WorkflowInsights {
  temps_par_statut: WorkflowStatusAgingRow[]
  conversions: WorkflowConversionRow[]
  reprise_rejets: {
    rejetes_count: number
    repris_count: number
    taux_pct: number
  }
  immobilises_par_statut: Array<{
    statut: string
    count: number
    montant: number
  }>
  aging_admis: {
    count: number
    montant: number
    average_days: number
    max_days: number
    buckets: Array<{
      label: string
      count: number
    }>
  }
}

export interface BankOverview {
  pont_tresorerie: {
    solde_debut: number
    encaissements: number
    decaissements: number
    solde_fin: number
  }
  evolution: Array<{
    date: string
    encaissements: number
    decaissements: number
    flux_net: number
    count: number
    solde: number
  }>
  top_variations: Array<{
    numero_compte: string
    libelle: string
    count: number
    encaissements: number
    decaissements: number
    solde_debut: number
    solde_fin: number
    variation: number
    derniere_date_mouvement: string | null
  }>
  anomalies: Array<{
    type: string
    priorite: 'critique' | 'warning' | 'info'
    titre: string
    detail: string
  }>
  confiance: {
    derniere_date_mouvement: string | null
    comptes_inclus: number
    lignes_incluses: number
    lignes_exclues: number
  }
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
  workflow: WorkflowBacklog
  workflow_insights: WorkflowInsights
  banques: BankOverview
  mandats_par_type: MandatTypeRow[]
  statuts_mandats: MandatStatutRow[]
  meta: {
    dashboard_id: number | null
    regional_id: string | null
    derniere_mise_a_jour: string | null
    mouvements_count: number
    mandats_count: number
    recettes_count: number
  }
}

export interface RegionOption {
  id: number
  code: string
  nom: string
  ordre: number
  derniere_connexion: string | null
}

export type KpiAccent = 'recouvrements' | 'recettes' | 'ordonnance' | 'depenses' | 'paye' | 'solde' | 'tresorerie' | 'encaisse' | 'neutral'

export interface CentralRegionRow {
  region: {
    code: string
    nom: string
  }
  kpis: DashboardKpis
  workflow: WorkflowBacklog
  meta: {
    has_data: boolean
    mouvements_count: number
    mandats_count: number
    recettes_count: number
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
  workflow: WorkflowBacklog
  regions: CentralRegionRow[]
  meta: {
    regions_actives: number
    regions_avec_donnees: number
    mandats_count: number
    recettes_count: number
    mouvements_count: number
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
  parametres: {
    compare_mode: 'mois_precedent' | 'periode_precedente'
    compare_label: string
    sla_warning_days: number
    sla_critical_days: number
  }
  workflow: WorkflowBacklog
  workflow_aging: {
    count: number
    average_days: number
    max_days: number
    warning_days: number
    critical_days: number
    over_warning_count: number
    over_critical_count: number
    reference_date: string
  }
  comparaison_reference: {
    mode: 'mois_precedent' | 'periode_precedente'
    label: string
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
    mandats_count: number
    recettes_count: number
    mouvements_count: number
    derniere_mise_a_jour: string | null
  }
}

export interface ExecutivePredictions {
  reference_label: string
  tendance_depenses: {
    type: 'stable' | 'hausse' | 'baisse'
    evolution_pct: number | null
    description: string
  }
  projection_depenses_fin_mois: number
  depenses_mois_courant: number
}

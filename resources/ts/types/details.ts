export interface PaginationMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface GroupStatRow {
  label: string
  count: number
  montant: number
}

export interface DayStatRow {
  date: string
  count?: number
  montant?: number
  debit?: number
  credit?: number
}

export interface MouvementRow {
  id: number
  libelle: string
  montant: number
  type: string
  statut: string | null
  date_mouvement: string | null
  code_programme: string | null
  chapitre: string | null
  beneficiaire: string | null
  type_mandat: string | null
  type_mandat_libelle: string | null
  nature_ce: string | null
  source_numero_mandat: string | null
}

export interface MouvementStats {
  totaux: {
    count: number
    depenses_count: number
    mandats_distincts_count?: number
    recettes_count: number
    montant_ordonnance: number
    montant_recouvrements_4121: number
    montant_total: number
  }
  par_statut: GroupStatRow[]
  par_type_mandat: Array<GroupStatRow & { code: string }>
  par_programme: GroupStatRow[]
  par_jour: DayStatRow[]
}

export interface RecetteRow {
  id: number
  client_no: string
  client_name: string
  montant: number
  date_posting: string | null
  gl_account: string | null
  description: string | null
}

export interface RecetteStats {
  totaux: {
    count: number
    clients_uniques: number
    montant_total: number
    montant_moyen: number
    top_client_part_pct: number
  }
  top_clients: Array<{
    client_no: string
    client_name: string
    count: number
    montant: number
  }>
  par_jour: DayStatRow[]
}

export interface BanqueRow {
  id: number
  numero_compte: string
  libelle: string
  debit: number
  credit: number
  solde: number
  date_mouvement: string | null
  reference: string | null
  type_document: string | null
  description: string | null
}

export interface BanqueStats {
  totaux: {
    count: number
    comptes_uniques: number
    total_debit: number
    total_credit: number
    flux_net: number
  }
  par_compte: Array<{
    numero_compte: string
    libelle: string
    count: number
    debit: number
    credit: number
    solde: number
  }>
  par_jour: DayStatRow[]
}

export interface ProgrammeRow {
  code: string
  libelle: string
  count: number
  montant_depenses: number
  paye_count: number
  admis_count: number
  taux_execution_pct: number
}

export interface ProgrammeStats {
  totaux: {
    programmes_count: number
    mandats_count: number
    montant_ordonnance: number
    montant_recouvrements_4121: number
    taux_execution_pct: number
  }
  programmes: ProgrammeRow[]
  par_statut: GroupStatRow[]
  par_chapitre: GroupStatRow[]
  par_type_mandat: Array<GroupStatRow & { code: string }>
  par_jour: DayStatRow[]
}

export interface NatureCeRow {
  code: string
  libelle: string
  count: number
  montant_depenses: number
  paye_count: number
  taux_execution_pct: number
}

export interface NatureCeStats {
  totaux: {
    natures_ce_count: number
    mandats_count: number
    montant_depenses: number
    montant_recettes?: number
    taux_execution_pct: number
  }
  natures_ce: NatureCeRow[]
  par_statut: GroupStatRow[]
  par_chapitre: GroupStatRow[]
  par_jour: DayStatRow[]
}

export interface MouvementDetail extends MouvementRow {
  programme: string | null
  chapitre: string | null
  nature: string | null
  source_id: string | null
  annee: number | null
  mois: number | null
  created_at: string | null
  updated_at: string | null
}

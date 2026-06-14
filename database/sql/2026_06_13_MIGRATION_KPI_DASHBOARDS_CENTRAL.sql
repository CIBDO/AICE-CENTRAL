-- ============================================================================
-- Migration central KPI dashboards — script de reprise (MySQL)
-- Erreur #1060 = colonnes déjà créées (souvent via php artisan migrate)
-- Exécuter CE script au lieu du ALTER TABLE initial.
-- ============================================================================

USE aice;

-- ---------------------------------------------------------------------------
-- A) DIAGNOSTIC — lire le résultat avant de continuer
-- ---------------------------------------------------------------------------
SELECT COLUMN_NAME, COLUMN_TYPE, COLUMN_COMMENT
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'dashboards'
  AND COLUMN_NAME IN (
    'total_recettes', 'total_depenses', 'encaisse',
    'total_ordonnance', 'total_recouvrements_4121', 'total_montant_paye', 'tresorerie_reelle', 'solde'
  )
ORDER BY ORDINAL_POSITION;

SELECT migration, batch
FROM migrations
WHERE migration LIKE '%rename_dashboard_kpi%';

-- ---------------------------------------------------------------------------
-- B) REPRISE — copier les anciennes valeurs (uniquement si legacy encore présent)
--    Exécuter bloc par bloc selon DESCRIBE dashboards
-- ---------------------------------------------------------------------------

-- Si total_depenses / total_recettes / encaisse existent ENCORE :
UPDATE dashboards
SET
    total_ordonnance = total_depenses,
    total_recouvrements_4121 = total_recettes,
    tresorerie_reelle = encaisse;

-- Si ce UPDATE échoue ("Unknown column total_depenses"), passez directement à C)

-- ---------------------------------------------------------------------------
-- C) Supprimer les anciennes colonnes (une par une, sans erreur si déjà fait)
-- ---------------------------------------------------------------------------
SET @db = DATABASE();

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dashboards' AND COLUMN_NAME = 'total_recettes') > 0,
    'ALTER TABLE dashboards DROP COLUMN total_recettes',
    'SELECT ''total_recettes déjà supprimée'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dashboards' AND COLUMN_NAME = 'total_depenses') > 0,
    'ALTER TABLE dashboards DROP COLUMN total_depenses',
    'SELECT ''total_depenses déjà supprimée'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql = IF(
    (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = @db AND TABLE_NAME = 'dashboards' AND COLUMN_NAME = 'encaisse') > 0,
    'ALTER TABLE dashboards DROP COLUMN encaisse',
    'SELECT ''encaisse déjà supprimée'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- D) Enregistrer la migration Laravel si absente
-- ---------------------------------------------------------------------------
INSERT INTO migrations (migration, batch)
SELECT '2026_06_13_000001_rename_dashboard_kpi_columns', COALESCE(MAX(batch), 0) + 1
FROM migrations
WHERE NOT EXISTS (
    SELECT 1 FROM migrations
    WHERE migration = '2026_06_13_000001_rename_dashboard_kpi_columns'
);

-- ---------------------------------------------------------------------------
-- E) CONTRÔLE final
-- ---------------------------------------------------------------------------
SELECT
    id,
    regional_id,
    total_ordonnance,
    total_recouvrements_4121,
    total_montant_paye,
    solde,
    tresorerie_reelle,
    updated_at
FROM dashboards
ORDER BY updated_at DESC
LIMIT 10;

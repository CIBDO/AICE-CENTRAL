-- ============================================================================
-- RESET CENTRAL — vider toutes les données reçues par push régional
-- Base : aice (MySQL)
--
-- IMPORTANT phpMyAdmin : exécuter TOUT le bloc B d'un coup (onglet SQL),
-- ou cocher "Activer les clés étrangères" OFF si disponible.
-- Si TRUNCATE échoue (#1701), utiliser le bloc C (DELETE).
-- ============================================================================

USE aice;

-- ---------------------------------------------------------------------------
-- A) Comptage AVANT
-- ---------------------------------------------------------------------------
SELECT 'dashboards' AS tbl, COUNT(*) AS n FROM dashboards
UNION ALL SELECT 'mouvements', COUNT(*) FROM mouvements
UNION ALL SELECT 'banques_push', COUNT(*) FROM banques_push
UNION ALL SELECT 'recettes_clients_push', COUNT(*) FROM recettes_clients_push;

-- ---------------------------------------------------------------------------
-- B) TRUNCATE — ordre ENFANTS d'abord, puis parent
--    Exécuter ce bloc entier en une seule fois.
-- ---------------------------------------------------------------------------
SET @OLD_FK = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

TRUNCATE TABLE mouvements;
TRUNCATE TABLE banques_push;
TRUNCATE TABLE recettes_clients_push;
TRUNCATE TABLE dashboards;

-- Optionnel
TRUNCATE TABLE regional_sync_metadata;
TRUNCATE TABLE jobs;
TRUNCATE TABLE job_batches;
TRUNCATE TABLE failed_jobs;

SET FOREIGN_KEY_CHECKS = @OLD_FK;

-- ---------------------------------------------------------------------------
-- C) ALTERNATIVE si B échoue encore (#1701) — DELETE + reset AUTO_INCREMENT
-- ---------------------------------------------------------------------------
/*
SET @OLD_FK = @@FOREIGN_KEY_CHECKS;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM mouvements;
DELETE FROM banques_push;
DELETE FROM recettes_clients_push;
DELETE FROM dashboards;
DELETE FROM regional_sync_metadata;
DELETE FROM jobs;
DELETE FROM job_batches;
DELETE FROM failed_jobs;

ALTER TABLE mouvements AUTO_INCREMENT = 1;
ALTER TABLE banques_push AUTO_INCREMENT = 1;
ALTER TABLE recettes_clients_push AUTO_INCREMENT = 1;
ALTER TABLE dashboards AUTO_INCREMENT = 1;

SET FOREIGN_KEY_CHECKS = @OLD_FK;
*/

-- ---------------------------------------------------------------------------
-- D) Comptage APRÈS (tout = 0)
-- ---------------------------------------------------------------------------
SELECT 'dashboards' AS tbl, COUNT(*) AS n FROM dashboards
UNION ALL SELECT 'mouvements', COUNT(*) FROM mouvements
UNION ALL SELECT 'banques_push', COUNT(*) FROM banques_push
UNION ALL SELECT 'recettes_clients_push', COUNT(*) FROM recettes_clients_push;

-- Puis : php artisan aice:push-dashboard --periode=2024 --full-sync --force --clear-cache

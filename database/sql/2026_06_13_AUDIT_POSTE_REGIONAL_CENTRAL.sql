-- ============================================================================
-- AUDIT CENTRAL — identifier à quel poste régional appartiennent les données
-- Base : aice (MySQL)
-- Remplacer 'SKO' ou 'SAN' par le code poste à vérifier
-- ============================================================================

USE aice;

SET @code_poste = 'SKO';  -- ← code attendu (APP_REGION_CODE dans AICE-API/.env)

-- ---------------------------------------------------------------------------
-- 1) Postes enregistrés côté central
-- ---------------------------------------------------------------------------
SELECT
    id,
    code,
    nom,
    actif,
    LEFT(token, 12) AS token_debut,
    derniere_connexion,
    created_at
FROM regions
ORDER BY code;

-- ---------------------------------------------------------------------------
-- 2) Inventaire par poste (dashboards + volumes enfants)
-- ---------------------------------------------------------------------------
SELECT
    r.code AS poste,
    r.nom,
    COUNT(DISTINCT d.id) AS dashboards,
    COUNT(DISTINCT m.id) AS mouvements,
    COUNT(DISTINCT b.id) AS banques,
    COUNT(DISTINCT rc.id) AS recettes_clients,
    MIN(d.created_at) AS premier_push,
    MAX(d.updated_at) AS dernier_push
FROM regions r
LEFT JOIN dashboards d ON d.region_id = r.id
LEFT JOIN mouvements m ON m.dashboard_id = d.id
LEFT JOIN banques_push b ON b.dashboard_id = d.id
LEFT JOIN recettes_clients_push rc ON rc.dashboard_id = d.id
GROUP BY r.id, r.code, r.nom
ORDER BY r.code;

-- ---------------------------------------------------------------------------
-- 3) Détail des dashboards (regional_id = clé du push)
-- ---------------------------------------------------------------------------
SELECT
    r.code AS poste,
    d.id,
    d.local_id,
    d.regional_id,
    d.annee,
    d.date_debut,
    d.date_fin,
    d.total_ordonnance,
    d.total_recouvrements_4121,
    d.total_montant_paye,
    d.tresorerie_reelle,
    d.updated_at
FROM dashboards d
INNER JOIN regions r ON r.id = d.region_id
ORDER BY d.updated_at DESC;

-- ---------------------------------------------------------------------------
-- 4) Vérifier UN poste cible (@code_poste)
-- ---------------------------------------------------------------------------
SELECT
    r.code,
    r.nom,
    d.regional_id,
    d.total_ordonnance,
    d.total_recouvrements_4121,
    d.total_montant_paye,
    d.tresorerie_reelle,
    (SELECT COUNT(*) FROM mouvements m WHERE m.dashboard_id = d.id) AS nb_mouvements,
    d.updated_at
FROM regions r
INNER JOIN dashboards d ON d.region_id = r.id
WHERE r.code = @code_poste
ORDER BY d.updated_at DESC;

-- ---------------------------------------------------------------------------
-- 5) Échantillon mouvements du poste (regional_id commence souvent par MVT-/RECETTE-)
-- ---------------------------------------------------------------------------
SELECT
    r.code AS poste,
    m.regional_id,
    m.type,
    m.type_mandat,
    m.statut,
    m.montant,
    m.date_mouvement
FROM mouvements m
INNER JOIN dashboards d ON d.id = m.dashboard_id
INNER JOIN regions r ON r.id = d.region_id
WHERE r.code = @code_poste
ORDER BY m.id DESC
LIMIT 20;

-- ---------------------------------------------------------------------------
-- 6) Cohérence : local_id doit = regions.code ; regional_id contient souvent le code
-- ---------------------------------------------------------------------------
SELECT
    r.code AS poste_attendu,
    d.local_id,
    d.regional_id,
    CASE
        WHEN d.local_id = r.code THEN 'OK'
        ELSE 'INCOHERENT local_id'
    END AS controle_local_id
FROM dashboards d
INNER JOIN regions r ON r.id = d.region_id;

-- ---------------------------------------------------------------------------
-- 7) PURGE ciblée d'UN seul poste (ex. SKO) — pas toute la base
-- ---------------------------------------------------------------------------
/*
SET @code_poste = 'SKO';

SET FOREIGN_KEY_CHECKS = 0;

DELETE m FROM mouvements m
INNER JOIN dashboards d ON d.id = m.dashboard_id
INNER JOIN regions r ON r.id = d.region_id
WHERE r.code = @code_poste;

DELETE b FROM banques_push b
INNER JOIN dashboards d ON d.id = b.dashboard_id
INNER JOIN regions r ON r.id = d.region_id
WHERE r.code = @code_poste;

DELETE rc FROM recettes_clients_push rc
INNER JOIN dashboards d ON d.id = rc.dashboard_id
INNER JOIN regions r ON r.id = d.region_id
WHERE r.code = @code_poste;

DELETE d FROM dashboards d
INNER JOIN regions r ON r.id = d.region_id
WHERE r.code = @code_poste;

SET FOREIGN_KEY_CHECKS = 1;
*/

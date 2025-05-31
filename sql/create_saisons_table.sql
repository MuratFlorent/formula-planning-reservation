-- Script SQL pour créer la table des saisons et insérer des données de test

-- Création de la table des saisons
CREATE TABLE IF NOT EXISTS `wp_fpr_saisons` (
  `id` mediumint(9) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `tag` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Exemple d'insertion de données (à adapter selon vos besoins)
-- INSERT INTO `wp_fpr_saisons` (`name`, `tag`, `start_date`, `end_date`) VALUES
-- ('2023-2024', 'cours formule basique 2023-2024', '2023-09-01', '2024-07-01'),
-- ('2024-2025', 'cours formule basique 2024-2025', '2024-09-01', '2025-07-01');

-- Requête pour créer des périodes d'événements dans Amelia
-- Cette requête est fournie à titre d'exemple et doit être adaptée à votre environnement
/*
INSERT INTO `wp_amelia_events_periods` (`eventId`, `periodStart`, `periodEnd`)
SELECT 
    e.id,
    CONCAT('2023-09-01', ' ', TIME(p.periodStart)) as periodStart,
    CONCAT('2024-07-01', ' ', TIME(p.periodEnd)) as periodEnd
FROM 
    `wp_amelia_events` e
    INNER JOIN `wp_amelia_events_tags` et ON e.id = et.eventId
    INNER JOIN `wp_amelia_events_periods` p ON e.id = p.eventId
WHERE 
    et.name = 'cours formule basique 2023-2024'
    AND p.id = (
        SELECT MAX(id) 
        FROM `wp_amelia_events_periods` 
        WHERE eventId = e.id
    );
*/
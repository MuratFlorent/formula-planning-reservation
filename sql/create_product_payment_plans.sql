-- Script SQL pour l'installation du plugin
-- Ce script est exécuté lors de l'activation du plugin

-- Note: Les prix des plans de paiement pour les produits sont stockés dans la table wp_postmeta
-- avec la clé '_fpr_payment_plan_prices' et une valeur sérialisée contenant un tableau associatif
-- des ID de plans de paiement et leurs prix correspondants.
-- 
-- Exemple:
-- post_id = ID du produit
-- meta_key = '_fpr_payment_plan_prices'
-- meta_value = a:3:{i:1;s:6:"100.00";i:2;s:6:"280.00";i:3;s:6:"950.00";}
--
-- Où:
-- 1, 2, 3 sont les ID des plans de paiement
-- 100.00, 280.00, 950.00 sont les prix correspondants
--
-- Cette approche utilise la structure existante de WordPress et ne nécessite pas
-- de table supplémentaire.

-- Vérifier si la table des plans de paiement existe, sinon la créer
CREATE TABLE IF NOT EXISTS {prefix}fpr_payment_plans (
    id mediumint(9) NOT NULL AUTO_INCREMENT,
    name varchar(255) NOT NULL,
    frequency varchar(50) NOT NULL,
    installments int(11) NOT NULL DEFAULT 1,
    description text,
    active tinyint(1) NOT NULL DEFAULT 1,
    is_default tinyint(1) NOT NULL DEFAULT 0,
    created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
    PRIMARY KEY (id)
) {charset_collate};

-- Insérer des plans de paiement par défaut si la table est vide
INSERT INTO {prefix}fpr_payment_plans (name, frequency, installments, description, active, is_default)
SELECT 'Paiement mensuel', 'monthly', 10, 'Paiement en 10 mensualités', 1, 1
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM {prefix}fpr_payment_plans LIMIT 1);

INSERT INTO {prefix}fpr_payment_plans (name, frequency, installments, description, active, is_default)
SELECT 'Paiement trimestriel', 'quarterly', 3, 'Paiement en 3 versements trimestriels', 1, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM {prefix}fpr_payment_plans WHERE id = 2);

INSERT INTO {prefix}fpr_payment_plans (name, frequency, installments, description, active, is_default)
SELECT 'Paiement annuel', 'annual', 1, 'Paiement en une seule fois', 1, 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM {prefix}fpr_payment_plans WHERE id = 3);
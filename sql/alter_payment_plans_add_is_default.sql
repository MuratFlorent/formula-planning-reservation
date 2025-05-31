ALTER TABLE {prefix}fpr_payment_plans
ADD COLUMN is_default tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE {prefix}fpr_customers
ADD COLUMN amelia_customer_id bigint(20) DEFAULT NULL,
ADD KEY amelia_customer_id (amelia_customer_id);